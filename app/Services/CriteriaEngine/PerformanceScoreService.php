<?php

namespace App\Services\CriteriaEngine;

use App\Models\AssessmentPeriod;
use App\Services\CriteriaEngine\Contracts\WeightProvider;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class PerformanceScoreService
{
    public function __construct(
        private readonly CriteriaAggregator $aggregator,
        private readonly CriteriaNormalizer $normalizer,
        private readonly WeightProvider $weightProvider,
        private readonly CriteriaRegistry $registry,
    ) {
    }

    /**
     * @param array<int> $userIds
     * @return array{
     *   users: array<int, array{criteria: array<int, array{key:string,label:string,source:string,type:string,raw:float,normalized:float,weight:float,weighted:float,readiness_status:string,readiness_message:?string}>, total_wsm: float}>,
     *   unit_total: float,
     *   weights: array<string,float>,
     *   criteria_used: array<int,string>,
     *   criteria_meta: array<string, array{label:string,source:string,type:string,readiness_status:string,readiness_message:?string}>
     * }
     */
    public function calculate(int $unitId, AssessmentPeriod $period, array $userIds, ?int $professionId = null): array
    {
        $userIds = array_values(array_unique(array_map('intval', $userIds)));
        if (empty($userIds)) {
            return [
                'users' => [],
                'unit_total' => 0.0,
                'weights' => [],
                'criteria_used' => [],
                'criteria_meta' => [],
            ];
        }

        $agg = $this->aggregator->aggregate($period, $unitId, $userIds, $professionId);
        $criteriaUsed = $agg['criteria_used'] ?? [];

        // Normalize per criteria (WSM normalization follows DB normalization_basis/custom_target_value)
        $normalizedByKey = [];
        $criteriaMeta = [];
        foreach (($agg['criteria'] ?? []) as $key => $info) {
            $type = (string) ($info['type'] ?? 'benefit');
            $raw = (array) ($info['raw'] ?? []);

            $policy = $this->resolveNormalizationPolicyForKey((string) $key);
            $basis = (string) ($policy['basis'] ?? 'total_unit');
            $target = $policy['custom_target'] !== null ? (float) $policy['custom_target'] : null;

            // Helpful default: if custom_target is selected but missing for attendance,
            // use the period total days as a dynamic target.
            if ($basis === 'custom_target' && $target === null && (string) $key === 'attendance') {
                $target = $this->periodTotalDays($period);
            }

            $norm = $this->normalizer->normalizeWithBasis(
                $type === 'cost' ? 'cost' : 'benefit',
                $basis,
                $raw,
                $userIds,
                $target
            );
            $normalizedByKey[$key] = $norm['normalized'];

            $readiness = (array) ($info['readiness'] ?? []);
            $criteriaMeta[$key] = [
                'label' => (string) ($info['label'] ?? $key),
                'source' => (string) ($info['source'] ?? 'metric_import'),
                'type' => $type === 'cost' ? 'cost' : 'benefit',
                'readiness_status' => (string) ($readiness['status'] ?? 'ready'),
                'readiness_message' => $readiness['message'] ?? null,
            ];
        }

        $weights = $this->weightProvider->getWeights($period, $unitId, $professionId, $criteriaUsed);

        $usersOut = [];
        $unitTotal = 0.0;
        foreach ($userIds as $uid) {
            $uid = (int) $uid;
            $criteriaRows = [];
            $sum = 0.0;

            foreach ($criteriaUsed as $key) {
                $meta = $criteriaMeta[$key] ?? [
                    'label' => $key,
                    'source' => 'metric_import',
                    'type' => 'benefit',
                    'readiness_status' => 'ready',
                    'readiness_message' => null,
                ];

                $rawVal = (float) (($agg['criteria'][$key]['raw'][$uid] ?? 0.0));
                $normVal = (float) (($normalizedByKey[$key][$uid] ?? 0.0));
                $weight = (float) ($weights[$key] ?? 0.0);
                $weighted = $normVal * ($weight / 100.0);

                $sum += $weighted;
                $criteriaRows[] = [
                    'key' => $key,
                    'label' => (string) $meta['label'],
                    'source' => (string) $meta['source'],
                    'type' => (string) $meta['type'],
                    'raw' => $rawVal,
                    'normalized' => round($normVal, 6),
                    'weight' => round($weight, 6),
                    'weighted' => round($weighted, 6),
                    'readiness_status' => (string) $meta['readiness_status'],
                    'readiness_message' => $meta['readiness_message'],
                ];
            }

            $usersOut[$uid] = [
                'criteria' => $criteriaRows,
                'total_wsm' => round($sum, 6),
            ];
            $unitTotal += $sum;
        }

        return [
            'users' => $usersOut,
            'unit_total' => round($unitTotal, 6),
            'weights' => $weights,
            'criteria_used' => $criteriaUsed,
            'criteria_meta' => $criteriaMeta,
        ];
    }

    /**
     * @return array{basis: string, custom_target: ?float}
     */
    private function resolveNormalizationPolicyForKey(string $key): array
    {
        $default = ['basis' => 'total_unit', 'custom_target' => null];

        if (!Schema::hasTable('performance_criterias')) {
            return $default;
        }

        // Metric / 360 keys have direct ID.
        if (str_starts_with($key, 'metric:')) {
            $id = (int) substr($key, strlen('metric:'));
            return $this->resolveNormalizationPolicyForCriteriaId($id) ?? $default;
        }
        if (str_starts_with($key, '360:')) {
            $id = (int) substr($key, strlen('360:'));
            return $this->resolveNormalizationPolicyForCriteriaId($id) ?? $default;
        }

        // System keys -> resolve by reserved criteria name.
        $name = $this->registry->systemNameByKey($key);
        if (!$name) {
            return $default;
        }

        $row = DB::table('performance_criterias')
            ->where('name', $name)
            ->first(['normalization_basis', 'custom_target_value']);

        if (!$row) {
            return $default;
        }

        return [
            'basis' => (string) ($row->normalization_basis ?? 'total_unit'),
            'custom_target' => $row->custom_target_value !== null ? (float) $row->custom_target_value : null,
        ];
    }

    /** @return array{basis: string, custom_target: ?float}|null */
    private function resolveNormalizationPolicyForCriteriaId(int $criteriaId): ?array
    {
        if ($criteriaId <= 0) {
            return null;
        }

        $row = DB::table('performance_criterias')
            ->where('id', $criteriaId)
            ->first(['normalization_basis', 'custom_target_value']);

        if (!$row) {
            return null;
        }

        return [
            'basis' => (string) ($row->normalization_basis ?? 'total_unit'),
            'custom_target' => $row->custom_target_value !== null ? (float) $row->custom_target_value : null,
        ];
    }

    private function periodTotalDays(AssessmentPeriod $period): float
    {
        if (!$period->start_date || !$period->end_date) {
            return 0.0;
        }

        try {
            $s = \Illuminate\Support\Carbon::parse((string) $period->start_date)->startOfDay();
            $e = \Illuminate\Support\Carbon::parse((string) $period->end_date)->startOfDay();
            return (float) ($s->diffInDays($e) + 1);
        } catch (\Throwable) {
            return 0.0;
        }
    }
}
