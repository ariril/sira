<?php

namespace App\Services\CriteriaEngine\Contracts;

use App\Models\AssessmentPeriod;

interface WeightProvider
{
    /**
     * @param array<int, string> $criteriaKeys
     * @return array<string, float> [criteriaKey => weightPercent]
     */
    public function getWeights(AssessmentPeriod $period, int $unitId, ?int $professionId, array $criteriaKeys): array;
}
