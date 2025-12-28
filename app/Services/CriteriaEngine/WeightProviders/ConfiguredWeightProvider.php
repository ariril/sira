<?php

namespace App\Services\CriteriaEngine\WeightProviders;

use App\Models\AssessmentPeriod;
use App\Services\CriteriaEngine\Contracts\WeightProvider;
use App\Services\CriteriaEngine\CriteriaRegistry;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

class ConfiguredWeightProvider implements WeightProvider
{
    public function __construct(private readonly CriteriaRegistry $registry)
    {
    }

    public function getWeights(AssessmentPeriod $period, int $unitId, ?int $professionId, array $criteriaKeys): array
    {
        $criteriaKeys = array_values(array_unique($criteriaKeys));
        if (empty($criteriaKeys)) {
            return [];
        }

        $periodId = (int) $period->id;
        if ($periodId <= 0 || $unitId <= 0) {
            return array_fill_keys($criteriaKeys, 0.0);
        }

        $isActive = (string) ($period->status ?? '') === AssessmentPeriod::STATUS_ACTIVE;
        $statuses = $isActive ? ['active'] : ['active', 'archived'];

        $select = [
            'ucw.performance_criteria_id',
            'ucw.weight',
            'ucw.status',
            'pc.name',
            'pc.input_method',
            'pc.is_360',
        ];
        if (Schema::hasColumn('performance_criterias', 'source')) {
            $select[] = 'pc.source';
        }

        $rows = DB::table('unit_criteria_weights as ucw')
            ->join('performance_criterias as pc', 'pc.id', '=', 'ucw.performance_criteria_id')
            ->where('ucw.unit_id', $unitId)
            ->where('ucw.assessment_period_id', $periodId)
            ->whereIn('ucw.status', $statuses)
            ->get($select);

        if ($rows->isEmpty()) {
            return array_fill_keys($criteriaKeys, 0.0);
        }

        if (!$isActive && $rows->contains(fn($r) => (string) $r->status === 'active')) {
            $rows = $rows->filter(fn($r) => (string) $r->status === 'active');
        } elseif (!$isActive) {
            $rows = $rows->filter(fn($r) => (string) $r->status === 'archived');
        }

        // Convert DB rows to key => rawWeight
        $rawWeightsByKey = [];
        $sum = 0.0;
        foreach ($rows as $r) {
            $criteriaId = (int) $r->performance_criteria_id;
            $weight = (float) $r->weight;
            $sum += $weight;

            // Reuse registry mapping by reconstructing minimal criteria object
            $criteria = new \App\Models\PerformanceCriteria([
                'name' => (string) $r->name,
                'source' => (string) ($r->source ?? ''),
                'input_method' => (string) ($r->input_method ?? ''),
                'is_360' => (bool) $r->is_360,
            ]);
            $criteria->id = $criteriaId;

            $key = $this->registry->keyForCriteria($criteria);
            if ($key) {
                $rawWeightsByKey[$key] = ($rawWeightsByKey[$key] ?? 0.0) + $weight;
            }
        }

        $out = [];
        if ($sum > 0) {
            foreach ($rawWeightsByKey as $key => $raw) {
                $out[$key] = ($raw / $sum) * 100.0;
            }
        }

        // Ensure completeness for requested keys
        $missing = [];
        foreach ($criteriaKeys as $key) {
            if (!array_key_exists($key, $out)) {
                $out[$key] = 0.0;
                $missing[] = $key;
            }
        }

        if (!empty($missing)) {
            Log::warning('ConfiguredWeightProvider: missing weights for some criteria keys; defaulting to 0', [
                'period_id' => $periodId,
                'unit_id' => $unitId,
                'missing_keys' => $missing,
            ]);
        }

        return $out;
    }
}
