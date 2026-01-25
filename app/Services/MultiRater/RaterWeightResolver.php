<?php

namespace App\Services\MultiRater;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use App\Models\AssessmentPeriod;

class RaterWeightResolver
{
    /** @return array<string,float> */
    public static function defaults(): array
    {
        return [
            'supervisor' => 40.0,
            'peer' => 30.0,
            'subordinate' => 20.0,
            'self' => 10.0,
        ];
    }

    private static function makeWeightKey(string $assessorType, $assessorLevel): string
    {
        $assessorType = (string) $assessorType;
        if ($assessorType === 'supervisor') {
            $lvl = $assessorLevel === null ? null : (int) $assessorLevel;
            if ($lvl && $lvl > 0) {
                return 'supervisor:' . $lvl;
            }
        }
        return $assessorType;
    }

    /**
     * Resolve per-assessee-profession weight map for one criteria.
     *
     * Prefers status=active when present, otherwise uses status=archived.
     *
     * @param array<int> $assesseeProfessionIds
     * @return array<int, array<string,float>> profession_id => (assessor_type => weight)
     */
    public static function resolveForCriteria(int $periodId, int $unitId, int $criteriaId, array $assesseeProfessionIds): array
    {
        $periodId = (int) $periodId;
        $unitId = (int) $unitId;
        $criteriaId = (int) $criteriaId;
        $assesseeProfessionIds = array_values(array_unique(array_filter(array_map('intval', $assesseeProfessionIds), fn ($v) => $v > 0)));

        if ($periodId <= 0 || $unitId <= 0 || $criteriaId <= 0 || empty($assesseeProfessionIds)) {
            return [];
        }

        $rows = DB::table('unit_rater_weights')
            ->where('assessment_period_id', $periodId)
            ->where('unit_id', $unitId)
            ->where('performance_criteria_id', $criteriaId)
            ->whereIn('assessee_profession_id', $assesseeProfessionIds)
            ->where(function ($q) {
                $q->where('status', 'active')
                    ->orWhere(function ($q) {
                        $q->where('status', 'archived');
                        if (Schema::hasColumn('unit_rater_weights', 'was_active_before')) {
                            $q->where('was_active_before', 1);
                        }
                    });
            })
            ->get(['assessee_profession_id', 'assessor_type', 'assessor_level', 'weight', 'status']);

        $period = Schema::hasTable('assessment_periods')
            ? AssessmentPeriod::query()->find($periodId)
            : null;

        if ($rows->isEmpty() && $period && $period->isFrozen()) {
            $previous = self::resolvePreviousPeriod($periodId);
            if ($previous) {
                $rows = DB::table('unit_rater_weights')
                    ->where('assessment_period_id', (int) $previous->id)
                    ->where('unit_id', $unitId)
                    ->where('performance_criteria_id', $criteriaId)
                    ->whereIn('assessee_profession_id', $assesseeProfessionIds)
                    ->whereIn('status', ['active', 'archived'])
                    ->when(Schema::hasColumn('unit_rater_weights', 'was_active_before'), fn($q) => $q->where('was_active_before', 1))
                    ->get(['assessee_profession_id', 'assessor_type', 'assessor_level', 'weight', 'status']);
            }
        }

        if ($rows->isEmpty()) {
            if ($period && $period->isFrozen()) {
                throw ValidationException::withMessages([
                    'status' => 'Periode tidak dapat diproses karena bobot kinerja tidak tersedia dan tidak ditemukan bobot aktif pada periode sebelumnya.',
                ]);
            }
            return [];
        }

        if ($rows->isEmpty()) {
            return [];
        }

        $byProfession = [];
        foreach ($rows as $r) {
            $professionId = (int) ($r->assessee_profession_id ?? 0);
            $assessorType = (string) ($r->assessor_type ?? '');
            $assessorLevel = $r->assessor_level ?? null;
            $status = (string) ($r->status ?? '');
            if ($professionId <= 0 || $assessorType === '' || ($status !== 'active' && $status !== 'archived')) {
                continue;
            }

            $key = self::makeWeightKey($assessorType, $assessorLevel);
            $byProfession[$professionId][$status][$key] = (float) (($byProfession[$professionId][$status][$key] ?? 0.0) + (float) ($r->weight ?? 0.0));
        }

        $out = [];
        foreach ($assesseeProfessionIds as $professionId) {
            $active = $byProfession[$professionId]['active'] ?? null;
            $archived = $byProfession[$professionId]['archived'] ?? null;
            $chosen = is_array($active) && !empty($active) ? $active : (is_array($archived) ? $archived : null);

            if (is_array($chosen) && !empty($chosen)) {
                // If supervisor level weights exist, ignore any legacy 'supervisor' key to avoid double-counting.
                $hasSupervisorLevels = false;
                foreach (array_keys($chosen) as $k) {
                    if (is_string($k) && str_starts_with($k, 'supervisor:')) {
                        $hasSupervisorLevels = true;
                        break;
                    }
                }
                if ($hasSupervisorLevels) {
                    unset($chosen['supervisor']);
                }
                $out[$professionId] = $chosen;
            }
        }

        return $out;
    }

    private static function resolvePreviousPeriod(int $periodId): ?object
    {
        if (!Schema::hasTable('assessment_periods')) {
            return null;
        }

        $current = AssessmentPeriod::query()->find($periodId);
        if (!$current) {
            return null;
        }

        $query = DB::table('assessment_periods')
            ->where('id', '!=', (int) $current->id)
            ->whereIn('status', [
                AssessmentPeriod::STATUS_LOCKED,
                AssessmentPeriod::STATUS_APPROVAL,
                AssessmentPeriod::STATUS_CLOSED,
            ]);

        if (Schema::hasColumn('assessment_periods', 'end_date') && Schema::hasColumn('assessment_periods', 'start_date') && !empty($current->start_date)) {
            $query->where('end_date', '<', $current->start_date)
                ->orderByDesc('end_date');
        } else {
            $query->where('id', '<', (int) $current->id)
                ->orderByDesc('id');
        }

        return $query->first();
    }
}
