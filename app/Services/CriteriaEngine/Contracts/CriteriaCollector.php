<?php

namespace App\Services\CriteriaEngine\Contracts;

use App\Models\AssessmentPeriod;

interface CriteriaCollector
{
    public function key(): string;

    public function label(): string;

    /** @return 'benefit'|'cost' */
    public function type(): string;

    /** @return 'system'|'metric_import'|'assessment_360' */
    public function source(): string;

    /**
     * @param array<int> $userIds
     * @return array<int, float> [userId => rawValue]
     */
    public function collect(AssessmentPeriod $period, int $unitId, array $userIds): array;

    /**
     * @return array{status: 'ready'|'missing_data', message: ?string}
     */
    public function readiness(AssessmentPeriod $period, int $unitId): array;
}
