<?php

namespace App\Services\RaterWeights;

use App\Enums\RaterWeightStatus;
use App\Models\RaterWeight;
use Illuminate\Support\Collection;

class RaterWeightSummaryService
{
    /**
     * Build summary per (criteria, assessee profession) for a given unit+period.
     *
     * Result shape (per group):
     * - criteria_id, profession_id, criteria_name, profession_name
     * - total (float), has_null (bool), ok (bool), over (bool)
     * - parts: self/supervisor/peer/subordinate (float)
     *
     * @param array<int> $criteriaIds
     * @param array<string, int|float|null> $weightOverrides keyed by rater_weight id (string)
     * @return \Illuminate\Support\Collection<int, array<string, mixed>>
     */
    public function summarizeForUnitPeriod(
        int $unitId,
        int $periodId,
        array $criteriaIds,
        ?int $criteriaFilterId = null,
        ?int $professionFilterId = null,
        array $statuses = [RaterWeightStatus::DRAFT->value, RaterWeightStatus::REJECTED->value],
        array $weightOverrides = [],
    ): Collection {
        if ($unitId <= 0 || $periodId <= 0 || empty($criteriaIds)) {
            return collect();
        }

        $rows = RaterWeight::query()
            ->with(['criteria:id,name', 'assesseeProfession:id,name'])
            ->where('assessment_period_id', $periodId)
            ->where('unit_id', $unitId)
            ->whereIn('performance_criteria_id', $criteriaIds)
            ->when($criteriaFilterId, fn ($q) => $q->where('performance_criteria_id', (int) $criteriaFilterId))
            ->when($professionFilterId, fn ($q) => $q->where('assessee_profession_id', (int) $professionFilterId))
            ->whereIn('status', $statuses)
            ->get();

        return $this->summarizeRows($rows, $weightOverrides);
    }

    /**
     * @param \Illuminate\Support\Collection<int, RaterWeight> $rows
     * @param array<string, int|float|null> $weightOverrides keyed by rater_weight id (string)
     * @return \Illuminate\Support\Collection<int, array<string, mixed>>
     */
    public function summarizeRows(Collection $rows, array $weightOverrides = []): Collection
    {
        if ($rows->isEmpty()) {
            return collect();
        }

        return $rows
            ->groupBy(fn ($r) => (int) $r->performance_criteria_id . ':' . (int) $r->assessee_profession_id)
            ->map(function (Collection $groupRows) use ($weightOverrides) {
                $parts = [
                    'self' => 0.0,
                    'supervisor' => 0.0,
                    'peer' => 0.0,
                    'subordinate' => 0.0,
                ];

                $hasNull = false;
                $total = 0.0;

                foreach ($groupRows as $r) {
                    $effective = $r->weight;
                    if (array_key_exists((string) $r->id, $weightOverrides)) {
                        $effective = $weightOverrides[(string) $r->id];
                    }

                    if ($effective === null) {
                        $hasNull = true;
                        continue;
                    }

                    $w = (float) $effective;
                    $total += $w;

                    $type = (string) ($r->assessor_type ?? '');
                    if (array_key_exists($type, $parts)) {
                        $parts[$type] += $w;
                    }
                }

                $over = (int) round($total, 0) > 100;

                return [
                    'criteria_id' => (int) ($groupRows->first()?->performance_criteria_id ?? 0),
                    'profession_id' => (int) ($groupRows->first()?->assessee_profession_id ?? 0),
                    'criteria_name' => (string) ($groupRows->first()?->criteria?->name ?? '-'),
                    'profession_name' => (string) ($groupRows->first()?->assesseeProfession?->name ?? '-'),
                    'sum' => $total,
                    'total' => $total,
                    'parts' => $parts,
                    'has_null' => $hasNull,
                    'ok' => !$hasNull && ((int) round($total, 0) === 100),
                    'over' => $over,
                ];
            })
            ->values();
    }
}
