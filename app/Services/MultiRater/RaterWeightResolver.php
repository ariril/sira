<?php

namespace App\Services\MultiRater;

use Illuminate\Support\Facades\DB;

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
                        if (\Illuminate\Support\Facades\Schema::hasColumn('unit_rater_weights', 'was_active_before')) {
                            $q->where('was_active_before', 1);
                        }
                    });
            })
            ->get(['assessee_profession_id', 'assessor_type', 'weight', 'status']);

        if ($rows->isEmpty()) {
            return [];
        }

        $byProfession = [];
        foreach ($rows as $r) {
            $professionId = (int) ($r->assessee_profession_id ?? 0);
            $assessorType = (string) ($r->assessor_type ?? '');
            $status = (string) ($r->status ?? '');
            if ($professionId <= 0 || $assessorType === '' || ($status !== 'active' && $status !== 'archived')) {
                continue;
            }

            $byProfession[$professionId][$status][$assessorType] = (float) (($byProfession[$professionId][$status][$assessorType] ?? 0.0) + (float) ($r->weight ?? 0.0));
        }

        $out = [];
        foreach ($assesseeProfessionIds as $professionId) {
            $active = $byProfession[$professionId]['active'] ?? null;
            $archived = $byProfession[$professionId]['archived'] ?? null;
            $chosen = is_array($active) && !empty($active) ? $active : (is_array($archived) ? $archived : null);

            if (is_array($chosen) && !empty($chosen)) {
                $out[$professionId] = $chosen;
            }
        }

        return $out;
    }
}
