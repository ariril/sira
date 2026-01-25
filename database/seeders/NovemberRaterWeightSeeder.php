<?php

namespace Database\Seeders;

use App\Enums\RaterWeightStatus;
use App\Models\AssessmentPeriod;
use App\Models\RaterWeight;
use App\Services\RaterWeights\RaterWeightGenerator;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Collection;

class NovemberRaterWeightSeeder extends Seeder
{
    public function run(): void
    {
        if (!Schema::hasTable('assessment_periods') || !Schema::hasTable('unit_criteria_weights') || !Schema::hasTable('unit_rater_weights')) {
            return;
        }

        $periodIds = $this->resolveTargetPeriodIds();
        if (empty($periodIds)) {
            $this->command?->warn('NovemberRaterWeightSeeder: target assessment period not found. Set env RATER_WEIGHT_PERIOD_ID to force a period.');
            return;
        }

        foreach ($periodIds as $periodId) {
            $periodId = (int) $periodId;
            if ($periodId <= 0) {
                continue;
            }

            $period = DB::table('assessment_periods')->where('id', $periodId)->first(['id', 'status', 'name']);
            if (!$period) {
                $this->command?->warn("NovemberRaterWeightSeeder: period_id={$periodId} not found.");
                continue;
            }

            // For history periods (non-active), we still seed weights so the Riwayat table is meaningful.
            // We will archive rater weights after seeding when the period is non-active.

            $unitIds = DB::table('unit_criteria_weights')
                ->where('assessment_period_id', $periodId)
                ->distinct()
                ->pluck('unit_id')
                ->map(fn ($v) => (int) $v)
                ->filter(fn ($v) => $v > 0)
                ->values()
                ->all();

            if (empty($unitIds)) {
                $this->command?->warn("NovemberRaterWeightSeeder: no unit_criteria_weights found for period_id={$periodId}.");
                continue;
            }

            $structuralProfessionIds = $this->resolveStructuralProfessionIds();
            $professionsByUnit = $this->mapProfessionsByUnit($unitIds, $structuralProfessionIds);

            // Ensure rater weight rows exist.
            foreach ($unitIds as $unitId) {
                app(RaterWeightGenerator::class)->syncForUnitPeriod((int) $unitId, $periodId);
            }

            // Fill missing weights, but only when a group is entirely empty (non-destructive).
            // Treat "all zero" as empty too (weights should not be 0 in realistic distributions).
            $rows = RaterWeight::query()
                ->where('assessment_period_id', $periodId)
                ->whereIn('unit_id', $unitIds)
                ->orderBy('unit_id')
                ->orderBy('performance_criteria_id')
                ->orderBy('assessee_profession_id')
                ->orderBy('assessor_type')
                ->orderByRaw('COALESCE(assessor_level, 999999) ASC')
                ->orderByRaw('COALESCE(assessor_profession_id, 999999) ASC')
                ->get();

            if ($rows->isEmpty()) {
                continue;
            }

            $updated = 0;

            $groups = $rows->groupBy(function ($r) {
                return (int) $r->unit_id . ':' . (int) $r->performance_criteria_id . ':' . (int) $r->assessee_profession_id;
            });

            DB::transaction(function () use ($groups, $professionsByUnit, &$updated) {
                foreach ($groups as $groupRows) {
                    $updated += $this->normalizeGroupWeights($groupRows, $professionsByUnit);
                }
            });

            // If the target period is not active, archive rater weights after seeding so they show as history.
            if ((string) ($period->status ?? '') !== AssessmentPeriod::STATUS_ACTIVE) {
                $archived = RaterWeightStatus::ARCHIVED->value;

                $rwUpdates = [
                    'status' => $archived,
                    'updated_at' => now(),
                ];
                if (Schema::hasColumn('unit_rater_weights', 'was_active_before')) {
                    // Only mark as previously active if the row was active.
                    $rwUpdates['was_active_before'] = DB::raw("CASE WHEN status='active' THEN 1 ELSE was_active_before END");
                }

                DB::table('unit_rater_weights')
                    ->where('assessment_period_id', $periodId)
                    ->where('status', '!=', $archived)
                    ->update($rwUpdates);

                // Ensure criteria weights for this historical period are archived as well.
                if (Schema::hasTable('unit_criteria_weights')) {
                    $ucwUpdates = [
                        'status' => 'archived',
                        'updated_at' => now(),
                    ];
                    if (Schema::hasColumn('unit_criteria_weights', 'was_active_before')) {
                        // Only mark as previously active if the row was active.
                        $ucwUpdates['was_active_before'] = DB::raw("CASE WHEN status='active' THEN 1 ELSE was_active_before END");
                    }

                    DB::table('unit_criteria_weights')
                        ->where('assessment_period_id', $periodId)
                        ->where('status', '!=', 'archived')
                        ->update($ucwUpdates);
                }

                $pname = (string) ($period->name ?? '');
            }

            // Backfill approval metadata for demo/history periods (Nov/Dec): decided_by/decided_at + was_active_before.
            $deciderId = $this->resolveDeciderId();
            $decidedAt = $this->resolveDecidedAt($periodId);
            if ($deciderId > 0) {
                DB::table('unit_rater_weights')
                    ->where('assessment_period_id', $periodId)
                    ->whereIn('status', ['active', 'archived'])
                    ->where(function ($q) {
                        $q->whereNull('decided_by')
                          ->orWhereNull('decided_at');
                    })
                    ->update([
                        'decided_by' => $deciderId,
                        'decided_at' => $decidedAt,
                        'updated_at' => now(),
                    ]);

                DB::table('unit_criteria_weights')
                    ->where('assessment_period_id', $periodId)
                    ->whereIn('status', ['active', 'archived'])
                    ->where(function ($q) {
                        $q->whereNull('decided_by')
                          ->orWhereNull('decided_at');
                    })
                    ->update([
                        'decided_by' => $deciderId,
                        'decided_at' => $decidedAt,
                        'updated_at' => now(),
                    ]);
            }

            if (Schema::hasColumn('unit_rater_weights', 'was_active_before')) {
                DB::table('unit_rater_weights')
                    ->where('assessment_period_id', $periodId)
                    ->where('status', 'archived')
                    ->where('was_active_before', 0)
                    ->where(function ($q) {
                        $q->whereNotNull('decided_by')
                          ->orWhereNotNull('decided_at');
                    })
                    ->update([
                        'was_active_before' => 1,
                        'updated_at' => now(),
                    ]);
            }

            if (Schema::hasColumn('unit_criteria_weights', 'was_active_before')) {
                DB::table('unit_criteria_weights')
                    ->where('assessment_period_id', $periodId)
                    ->where('status', 'archived')
                    ->where('was_active_before', 0)
                    ->where(function ($q) {
                        $q->whereNotNull('decided_by')
                          ->orWhereNotNull('decided_at');
                    })
                    ->update([
                        'was_active_before' => 1,
                        'updated_at' => now(),
                    ]);
            }
        }
    }

    /**
     * @param array<int,int> $unitIds
     * @return array<int,array<int,bool>> unit_id => [profession_id => true]
     */
    private function mapProfessionsByUnit(array $unitIds, array $structuralProfessionIds = []): array
    {
        if (empty($unitIds)) {
            return [];
        }

        $rows = DB::table('users')
            ->whereIn('unit_id', $unitIds)
            ->whereNotNull('profession_id')
            ->get(['unit_id', 'profession_id']);

        $out = [];
        foreach ($rows as $row) {
            $uid = (int) ($row->unit_id ?? 0);
            $pid = (int) ($row->profession_id ?? 0);
            if ($uid > 0 && $pid > 0) {
                $out[$uid][$pid] = true;
            }
        }

        if (!empty($structuralProfessionIds)) {
            foreach ($unitIds as $unitId) {
                foreach ($structuralProfessionIds as $pid) {
                    $pid = (int) $pid;
                    if ($pid > 0) {
                        $out[(int) $unitId][$pid] = true;
                    }
                }
            }
        }

        return $out;
    }

    /**
     * @return array<int>
     */
    private function resolveStructuralProfessionIds(): array
    {
        if (!Schema::hasTable('professions')) {
            return [];
        }

        return DB::table('professions')
            ->whereIn('code', ['KPL-UNIT', 'KPL-POLI'])
            ->pluck('id')
            ->map(fn($v) => (int) $v)
            ->filter(fn($v) => $v > 0)
            ->values()
            ->all();
    }

    private function normalizeGroupWeights(Collection $groupRows, array $professionsByUnit): int
    {
        if ($groupRows->isEmpty()) {
            return 0;
        }

        $unitId = (int) ($groupRows->first()?->unit_id ?? 0);
        $allowedProfessions = $professionsByUnit[$unitId] ?? [];

        $rows = $groupRows->values();

        // Remove assessors whose profession is not present in the unit.
        $invalid = $rows->filter(function ($r) use ($allowedProfessions) {
            $pid = $r->assessor_profession_id;
            return $pid !== null && (int) $pid > 0 && !isset($allowedProfessions[(int) $pid]);
        });

        if ($invalid->isNotEmpty()) {
            RaterWeight::query()->whereIn('id', $invalid->pluck('id'))->delete();
            $rows = $rows->reject(fn($r) => $invalid->contains('id', $r->id))->values();
        }

        if ($rows->isEmpty()) {
            return 0;
        }

        if ($rows->count() === 1) {
            $rw = $rows->first();
            if ($rw && ($rw->weight === null || (float) $rw->weight === 0.0)) {
                $rw->weight = 100;
                $rw->save();
                return 1;
            }
            return 0;
        }

        [$defaultWeights, $defaultTotal] = $this->buildDefaultWeights($rows);

        $baseWeights = [];
        foreach ($rows as $rw) {
            $current = $rw->weight !== null ? (float) $rw->weight : null;
            $base = $current !== null && $current > 0.0
                ? $current
                : ($defaultWeights[$rw->id] ?? 0.0);

            $baseWeights[$rw->id] = $base ?? 0.0;
        }

        $totalBase = array_sum($baseWeights);
        if ($totalBase <= 0 && $defaultTotal > 0) {
            $baseWeights = $defaultWeights;
            $totalBase = $defaultTotal;
        }

        if ($totalBase <= 0) {
            return 0;
        }

        $normalized = $this->normalizeToHundred($baseWeights);
        $normalized = $this->ensureSupervisorLevelShare($normalized, $rows);

        $changes = 0;
        foreach ($rows as $rw) {
            $newWeight = (int) ($normalized[$rw->id] ?? 0);
            if ((int) ($rw->weight ?? 0) !== $newWeight) {
                $rw->weight = $newWeight;
                $rw->save();
                $changes++;
            }
        }

        return $changes;
    }

    /**
     * @return array{0:array<int,float>,1:float}
     */
    private function buildDefaultWeights(Collection $rows): array
    {
        if ($rows->isEmpty()) {
            return [[], 0.0];
        }

        $typePriority = [
            'supervisor' => 60.0,
            'peer' => 25.0,
            'subordinate' => 10.0,
            'self' => 5.0,
        ];

        $rowsByType = $rows->groupBy(fn($r) => (string) ($r->assessor_type ?? ''));
        $presentTypes = array_values(array_filter(array_keys($rowsByType->all())));

        $presentWeights = [];
        $sumBase = 0.0;
        foreach ($presentTypes as $t) {
            $w = (float) ($typePriority[$t] ?? 0.0);
            if ($w <= 0.0) {
                continue;
            }
            $presentWeights[$t] = $w;
            $sumBase += $w;
        }

        if ($sumBase <= 0.0) {
            $n = $rows->count();
            $even = $n > 0 ? (100.0 / $n) : 0.0;
            $out = [];
            foreach ($rows as $rw) {
                $out[$rw->id] = $even;
            }
            return [$out, array_sum($out)];
        }

        $typeShares = [];
        foreach ($presentWeights as $t => $w) {
            $typeShares[$t] = ($w / $sumBase) * 100.0;
        }

        $out = [];
        foreach ($typeShares as $type => $share) {
            $typeRows = $rowsByType->get($type, collect());
            if ($typeRows->isEmpty()) {
                continue;
            }

            if ($type === 'supervisor' && $typeRows->count() > 1) {
                $sorted = $typeRows
                    ->sortBy(fn($r) => $r->assessor_level === null ? 999999 : (int) $r->assessor_level)
                    ->values();

                $ratios = [0.60, 0.25, 0.10, 0.05];
                while (count($ratios) < $sorted->count()) {
                    $ratios[] = 0.01;
                }
                $ratios = array_slice($ratios, 0, $sorted->count());
                $ratioSum = array_sum($ratios) ?: 1.0;

                for ($i = 0; $i < $sorted->count(); $i++) {
                    $out[$sorted[$i]->id] = $share * ($ratios[$i] / $ratioSum);
                }
                continue;
            }

            $n = max(1, $typeRows->count());
            $base = $share / $n;
            foreach ($typeRows as $rw) {
                $out[$rw->id] = $base;
            }
        }

        return [$out, array_sum($out)];
    }

    /**
     * @param array<int,float> $weights
     * @return array<int,int>
     */
    private function normalizeToHundred(array $weights): array
    {
        if (empty($weights)) {
            return [];
        }

        $total = array_sum($weights);
        if ($total <= 0) {
            return array_fill_keys(array_keys($weights), 0);
        }

        $allocated = 0;
        $remainders = [];
        $out = [];
        foreach ($weights as $id => $w) {
            $exact = ($w / $total) * 100.0;
            $floor = (int) floor($exact);
            $out[$id] = $floor;
            $allocated += $floor;
            $remainders[] = ['id' => $id, 'rem' => $exact - $floor];
        }

        $remaining = 100 - $allocated;
        if ($remaining > 0 && !empty($remainders)) {
            usort($remainders, fn($a, $b) => $b['rem'] <=> $a['rem']);
            for ($i = 0; $i < $remaining; $i++) {
                $idx = $i % count($remainders);
                $out[$remainders[$idx]['id']]++;
            }
        }

        return $out;
    }

    /**
     * Ensure supervisor level >=3 (kepala poliklinik) is never zero after normalization.
     *
     * @param array<int,int> $weights
     */
    private function ensureSupervisorLevelShare(array $weights, Collection $rows): array
    {
        $targetRows = $rows->filter(function ($r) {
            $lvl = (int) ($r->assessor_level ?? 0);
            return (string) ($r->assessor_type ?? '') === 'supervisor' && $lvl >= 2;
        });

        if ($targetRows->isEmpty()) {
            return $weights;
        }

        foreach ($targetRows as $rw) {
            $current = (int) ($weights[$rw->id] ?? 0);
            if ($current > 0) {
                continue;
            }

            $donorId = $this->pickSupervisorDonorId($weights, $rows, (int) $rw->id);
            if ($donorId === null) {
                continue;
            }

            if (($weights[$donorId] ?? 0) <= 0) {
                continue;
            }

            $weights[$donorId] = (int) $weights[$donorId] - 1;
            $weights[$rw->id] = 1;
        }

        return $weights;
    }

    /**
     * @param array<int,int> $weights
     */
    private function pickSupervisorDonorId(array $weights, Collection $rows, int $skipId): ?int
    {
        $supervisorIds = $rows
            ->filter(fn($r) => (string) ($r->assessor_type ?? '') === 'supervisor')
            ->pluck('id')
            ->filter(fn($id) => (int) $id !== $skipId)
            ->all();

        $candidate = null;
        $max = -1;

        foreach ($supervisorIds as $id) {
            $weight = (int) ($weights[$id] ?? 0);
            if ($weight > $max) {
                $max = $weight;
                $candidate = (int) $id;
            }
        }

        if ($candidate !== null && $max > 0) {
            return $candidate;
        }

        foreach ($weights as $id => $weight) {
            if ((int) $id === $skipId) {
                continue;
            }
            if ($weight > $max) {
                $max = (int) $weight;
                $candidate = (int) $id;
            }
        }

        return $candidate !== null && $max > 0 ? $candidate : null;
    }

    private function resolveDeciderId(): int
    {
        if (!Schema::hasTable('users')) {
            return 0;
        }

        if (Schema::hasColumn('users', 'role')) {
            $id = (int) (DB::table('users')->where('role', 'kepala_unit')->value('id') ?? 0);
            if ($id > 0) {
                return $id;
            }
        }

        if (Schema::hasColumn('users', 'last_role')) {
            $id = (int) (DB::table('users')->where('last_role', 'kepala_unit')->value('id') ?? 0);
            if ($id > 0) {
                return $id;
            }
        }

        return (int) (DB::table('users')->orderBy('id')->value('id') ?? 0);
    }

    private function resolveDecidedAt(int $periodId): string
    {
        $endDate = Schema::hasTable('assessment_periods')
            ? (string) (DB::table('assessment_periods')->where('id', $periodId)->value('end_date') ?? '')
            : '';

        return $endDate !== '' ? ($endDate . ' 23:59:59') : now()->toDateTimeString();
    }

    /**
     * @return array<int>
     */
    private function resolveTargetPeriodIds(): array
    {
        $forced = (int) (env('RATER_WEIGHT_PERIOD_ID') ?: 0);
        if ($forced > 0) {
            return [$forced];
        }

        // Prefer explicit demo period names if they exist (Indonesian/English).
        $byName = DB::table('assessment_periods')
            ->whereIn('name', ['November 2025', 'Desember 2025', 'December 2025'])
            ->orderByDesc('start_date')
            ->pluck('id')
            ->map(fn ($v) => (int) $v)
            ->filter(fn ($v) => $v > 0)
            ->values()
            ->all();

        if (!empty($byName)) {
            return array_values(array_unique($byName));
        }

        // Default: pick the latest period that starts in November and the latest that starts in December.
        $nov = (int) (DB::table('assessment_periods')->whereMonth('start_date', 11)->orderByDesc('start_date')->value('id') ?? 0);
        $dec = (int) (DB::table('assessment_periods')->whereMonth('start_date', 12)->orderByDesc('start_date')->value('id') ?? 0);

        $ids = array_values(array_unique(array_filter([$nov, $dec], fn ($v) => (int) $v > 0)));
        if (!empty($ids)) {
            return $ids;
        }

        // Fallback: name-based search for both months.
        $fallback = DB::table('assessment_periods')
            ->where('name', 'like', '%Nov%')
            ->orWhere('name', 'like', '%November%')
            ->orWhere('name', 'like', '%Des%')
            ->orWhere('name', 'like', '%Dec%')
            ->orWhere('name', 'like', '%Desember%')
            ->orWhere('name', 'like', '%December%')
            ->orderByDesc('start_date')
            ->limit(2)
            ->pluck('id')
            ->map(fn ($v) => (int) $v)
            ->filter(fn ($v) => $v > 0)
            ->values()
            ->all();

        return array_values(array_unique($fallback));
    }
}
