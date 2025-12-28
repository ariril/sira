<?php

namespace App\Services\CriteriaEngine\WeightProviders;

use App\Models\AssessmentPeriod;
use App\Services\CriteriaEngine\Contracts\WeightProvider;

class EqualWeightProvider implements WeightProvider
{
    public function getWeights(AssessmentPeriod $period, int $unitId, ?int $professionId, array $criteriaKeys): array
    {
        $criteriaKeys = array_values(array_unique($criteriaKeys));
        $n = count($criteriaKeys);
        if ($n <= 0) {
            return [];
        }
        $per = 100.0 / $n;
        $out = [];
        foreach ($criteriaKeys as $key) {
            $out[(string) $key] = $per;
        }
        return $out;
    }
}
