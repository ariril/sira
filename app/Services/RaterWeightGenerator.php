<?php

namespace App\Services;

use App\Enums\MedicalStaffReviewRole;
use App\Enums\RaterWeightStatus;
use App\Models\RaterWeight;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class RaterWeightGenerator
{
    /**
     * Sync rater_weights for a given unit+period based on:
     * - unit_criteria_weights (selected criterias for unit+period)
     * - performance_criterias.is_360
     * - criteria_rater_rules (allowed assessor_type per criteria)
     * - professions present in the unit
     *
     * Rules:
     * - If rule count == 1 => that single assessor_type auto weight 100 (draft)
     * - If rule count > 1 => create rows with weight NULL by default
     * - subordinate only for Dokter (perawat tidak punya bawahan)
     * - never duplicate; only create missing and remove obsolete drafts
     *
     * @return array{created:int,updated:int,deleted:int,skipped_locked:int,criteria_count:int}
     */
    public function syncForUnitPeriod(int $unitId, int $periodId): array
    {
        $stats = [
            'created' => 0,
            'updated' => 0,
            'deleted' => 0,
            'skipped_locked' => 0,
            'criteria_count' => 0,
        ];

        if ($unitId <= 0 || $periodId <= 0) {
            return $stats;
        }

        if (
            !Schema::hasTable('unit_criteria_weights') ||
            !Schema::hasTable('performance_criterias') ||
            !Schema::hasTable('criteria_rater_rules') ||
            !Schema::hasTable('users') ||
            !Schema::hasTable('professions')
        ) {
            return $stats;
        }

        $criteriaIds = DB::table('unit_criteria_weights as ucw')
            ->join('performance_criterias as pc', 'pc.id', '=', 'ucw.performance_criteria_id')
            ->join('criteria_rater_rules as crr', 'crr.performance_criteria_id', '=', 'pc.id')
            ->where('ucw.unit_id', $unitId)
            ->where('ucw.assessment_period_id', $periodId)
            ->where('ucw.status', '!=', 'archived')
            ->where('pc.is_360', true)
            ->distinct()
            ->pluck('pc.id')
            ->map(fn($v) => (int) $v)
            ->filter(fn($v) => $v > 0)
            ->values()
            ->all();

        if (empty($criteriaIds)) {
            return $stats;
        }

        $stats['criteria_count'] = count($criteriaIds);

        $professionRows = DB::table('users as u')
            ->join('professions as p', 'p.id', '=', 'u.profession_id')
            ->where('u.unit_id', $unitId)
            ->whereNotNull('u.profession_id')
            ->distinct()
            ->orderBy('p.name')
            ->get(['p.id as profession_id', 'p.name as profession_name']);

        if ($professionRows->isEmpty()) {
            return $stats;
        }

        $hasHierarchyMaster = Schema::hasTable('profession_reporting_lines');
        $hierarchyByAssessee = [];
        if ($hasHierarchyMaster) {
            $professionIds = $professionRows->pluck('profession_id')->map(fn($v) => (int) $v)->filter(fn($v) => $v > 0)->values()->all();

            if (!empty($professionIds)) {
                $hierarchyRows = DB::table('profession_reporting_lines')
                    ->whereIn('assessee_profession_id', $professionIds)
                    ->where('is_active', 1)
                    ->orderBy('assessee_profession_id')
                    ->orderBy('relation_type')
                    ->orderByRaw("CASE WHEN level IS NULL THEN 999999 ELSE level END ASC")
                    ->orderBy('assessor_profession_id')
                    ->get([
                        'assessee_profession_id',
                        'assessor_profession_id',
                        'relation_type',
                        'level',
                        'is_required',
                        'is_active',
                    ]);

                foreach ($hierarchyRows as $hr) {
                    $aId = (int) $hr->assessee_profession_id;
                    $type = (string) $hr->relation_type;
                    $hierarchyByAssessee[$aId][$type][] = [
                        'assessor_profession_id' => (int) $hr->assessor_profession_id,
                        'level' => $hr->level !== null ? (int) $hr->level : null,
                    ];
                }
            }
        }

        $assessorTypesByCriteria = DB::table('criteria_rater_rules')
            ->whereIn('performance_criteria_id', $criteriaIds)
            ->get(['performance_criteria_id', 'assessor_type'])
            ->groupBy('performance_criteria_id')
            ->map(fn($rows) => $rows->pluck('assessor_type')->filter()->unique()->values()->all())
            ->all();

        $now = now();

        DB::transaction(function () use ($unitId, $periodId, $criteriaIds, $professionRows, $assessorTypesByCriteria, $hierarchyByAssessee, $now, &$stats) {
            foreach ($criteriaIds as $criteriaId) {
                $ruleTypes = array_values(array_filter($assessorTypesByCriteria[$criteriaId] ?? []));
                $ruleTypes = array_values(array_unique($ruleTypes));

                if (empty($ruleTypes)) {
                    continue;
                }

                    $ruleCount = count($ruleTypes);
                    $isSingleRule = $ruleCount === 1;

                foreach ($professionRows as $pr) {
                    $professionId = (int) $pr->profession_id;
                    $professionName = (string) ($pr->profession_name ?? '');
                    $role = MedicalStaffReviewRole::guessFromProfession($professionName);

                    $desiredTypes = $ruleTypes;
                    if ($role !== MedicalStaffReviewRole::DOKTER) {
                        $desiredTypes = array_values(array_filter($desiredTypes, fn($t) => (string) $t !== 'subordinate'));
                    }

                    if (empty($desiredTypes)) {
                        // no desired types for this profession (e.g., only subordinate rule but profession is not Dokter)
                        $this->deleteObsoleteDrafts($unitId, $periodId, $criteriaId, $professionId, [], $stats);
                        continue;
                    }

                    // Expand desired rows based on profession hierarchy master (if present). Always include SELF as a single row.
                    $desiredDescriptors = [];

                    foreach ($desiredTypes as $assessorType) {
                        $assessorType = (string) $assessorType;

                        if ($assessorType === 'self') {
                            $desiredDescriptors[] = [
                                'assessor_type' => 'self',
                                'assessor_profession_id' => null,
                                'assessor_level' => null,
                            ];
                            continue;
                        }

                        $lines = $hierarchyByAssessee[$professionId][$assessorType] ?? [];

                        if (!empty($lines)) {
                            foreach ($lines as $line) {
                                $desiredDescriptors[] = [
                                    'assessor_type' => $assessorType,
                                    'assessor_profession_id' => (int) ($line['assessor_profession_id'] ?? 0) ?: null,
                                    'assessor_level' => $assessorType === 'supervisor' ? (int) ($line['level'] ?? 0) ?: null : null,
                                ];
                            }
                        } else {
                            // Fallback behavior: keep a single row per assessor_type (legacy flat mode)
                            $desiredDescriptors[] = [
                                'assessor_type' => $assessorType,
                                'assessor_profession_id' => null,
                                'assessor_level' => null,
                            ];
                        }
                    }

                    // If total desired rows is 1, auto set weight 100.
                    $isSingleLine = count($desiredDescriptors) === 1;

                    // Fetch existing draft/rejected/locked rows for matching descriptors (in-memory match)
                    $existing = RaterWeight::query()
                        ->where('assessment_period_id', $periodId)
                        ->where('unit_id', $unitId)
                        ->where('performance_criteria_id', $criteriaId)
                        ->where('assessee_profession_id', $professionId)
                        ->get();

                    $existingMap = [];
                    foreach ($existing as $erw) {
                        $existingMap[$this->makeKey((string) $erw->assessor_type, $erw->assessor_profession_id, $erw->assessor_level)] = $erw;
                    }

                    $desiredKeys = [];

                    // Create/update desired
                    foreach ($desiredDescriptors as $desc) {
                        $assessorType = (string) ($desc['assessor_type'] ?? '');
                        $assessorProfessionId = $desc['assessor_profession_id'] ?? null;
                        $assessorLevel = $desc['assessor_level'] ?? null;

                        $key = $this->makeKey($assessorType, $assessorProfessionId, $assessorLevel);
                        $desiredKeys[] = $key;

                        /** @var RaterWeight|null $rw */
                        $rw = $existingMap[$key] ?? null;

                        if (!$rw) {
                            RaterWeight::query()->create([
                                'assessment_period_id' => $periodId,
                                'unit_id' => $unitId,
                                'performance_criteria_id' => $criteriaId,
                                'assessee_profession_id' => $professionId,
                                'assessor_type' => $assessorType,
                                'assessor_profession_id' => $assessorProfessionId,
                                'assessor_level' => $assessorLevel,
                                'weight' => $isSingleLine ? 100 : null,
                                'status' => RaterWeightStatus::DRAFT->value,
                                'proposed_by' => null,
                                'decided_by' => null,
                                'decided_at' => null,
                                'created_at' => $now,
                                'updated_at' => $now,
                            ]);
                            $stats['created']++;
                            continue;
                        }

                        $locked = in_array((string) ($rw->status?->value ?? $rw->status), [RaterWeightStatus::PENDING->value, RaterWeightStatus::ACTIVE->value], true);
                        if ($locked) {
                            $stats['skipped_locked']++;
                            continue;
                        }

                        $updates = [];
                        if ($isSingleLine) {
                            if ((float) $rw->weight !== 100.0) {
                                $updates['weight'] = 100;
                            }
                            if ((string) ($rw->status?->value ?? $rw->status) !== RaterWeightStatus::DRAFT->value) {
                                $updates['status'] = RaterWeightStatus::DRAFT->value;
                            }
                        }

                        // Defensive normalization: assessor_level must be null unless supervisor
                        if ($assessorType !== 'supervisor' && $rw->assessor_level !== null) {
                            $updates['assessor_level'] = null;
                        }

                        if (!empty($updates)) {
                            $updates['updated_at'] = $now;
                            $rw->fill($updates);
                            $rw->save();
                            $stats['updated']++;
                        }
                    }

                    // Remove obsolete draft rows (if rule changed / hierarchy changed)
                    $this->deleteObsoleteDrafts($unitId, $periodId, $criteriaId, $professionId, $desiredKeys, $stats);

                    // If multi rule, do not overwrite weights; UI validation will force total=100 before submit.
                }
            }
        });

        return $stats;
    }

    /**
     * Delete draft/rejected rows not in desired assessor types.
     * Keeps pending/active/history for audit.
     *
     * @param array<int,string> $desiredTypes
     * @param array<string,int> $stats
     */
    private function deleteObsoleteDrafts(int $unitId, int $periodId, int $criteriaId, int $professionId, array $desiredKeys, array &$stats): void
    {
        $keep = array_fill_keys(array_values(array_map('strval', $desiredKeys)), true);

        $rows = RaterWeight::query()
            ->where('assessment_period_id', $periodId)
            ->where('unit_id', $unitId)
            ->where('performance_criteria_id', $criteriaId)
            ->where('assessee_profession_id', $professionId)
            ->whereIn('status', [RaterWeightStatus::DRAFT->value, RaterWeightStatus::REJECTED->value])
            ->get(['id', 'assessor_type', 'assessor_profession_id', 'assessor_level']);

        if ($rows->isEmpty()) {
            return;
        }

        $deleteIds = [];
        foreach ($rows as $r) {
            $key = $this->makeKey((string) $r->assessor_type, $r->assessor_profession_id, $r->assessor_level);
            if (!isset($keep[$key])) {
                $deleteIds[] = (int) $r->id;
            }
        }

        if (empty($deleteIds)) {
            return;
        }

        $deleted = (int) RaterWeight::query()->whereIn('id', $deleteIds)->delete();
        $stats['deleted'] += $deleted;
    }

    private function makeKey(string $assessorType, $assessorProfessionId, $assessorLevel): string
    {
        $pid = $assessorProfessionId === null ? 'null' : (string) (int) $assessorProfessionId;
        $lvl = $assessorLevel === null ? 'null' : (string) (int) $assessorLevel;
        return $assessorType . '|' . $pid . '|' . $lvl;
    }
}
