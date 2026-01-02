<?php

namespace App\Services\CriteriaEngine\Contracts;

interface CriteriaNormalizer
{
    /**
     * @param array<int,float> $rawByUserId
     * @param array<string,mixed> $context
     * @return array{
     *   normalized: array<int,float>,
     *   meta: array{
     *     scope_total: float,
     *     scope_max_raw: float,
     *     scope_avg_raw: float,
     *     target: ?float,
     *     formula_preview: string
     *   }
     * }
     */
    public function normalize(array $rawByUserId, array $context): array;
}
