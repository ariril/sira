<?php

namespace App\Services\CriteriaEngine;

use App\Models\AssessmentPeriod;
use App\Services\CriteriaEngine\Contracts\WeightProvider;

class PerformanceScoreService
{
    public function __construct(
        private readonly CriteriaAggregator $aggregator,
        private readonly CriteriaNormalizer $normalizer,
        private readonly WeightProvider $weightProvider,
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

        // Normalize per criteria
        $normalizedByKey = [];
        $criteriaMeta = [];
        foreach (($agg['criteria'] ?? []) as $key => $info) {
            $type = (string) ($info['type'] ?? 'benefit');
            $raw = (array) ($info['raw'] ?? []);

            if ($type === 'cost') {
                $norm = $this->normalizer->normalizeCost($raw, $userIds);
                $normalizedByKey[$key] = $norm['normalized'];
            } else {
                $norm = $this->normalizer->normalizeBenefit($raw, $userIds);
                $normalizedByKey[$key] = $norm['normalized'];
            }

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
}
