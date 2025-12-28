<?php

namespace App\Services\CriteriaEngine;

use App\Models\AssessmentPeriod;
use App\Services\CriteriaEngine\Contracts\CriteriaCollector;

class CriteriaAggregator
{
    public function __construct(private readonly CriteriaRegistry $registry)
    {
    }

    /**
     * @param array<int> $userIds
     * @return array{criteria: array<string, array{label:string,type:string,source:string,raw:array<int,float>,readiness:array{status:string,message:?string}}>, criteria_used: array<int,string>}
     */
    public function aggregate(AssessmentPeriod $period, int $unitId, array $userIds, ?int $professionId = null): array
    {
        $userIds = array_values(array_unique(array_map('intval', $userIds)));

        /** @var array<int, CriteriaCollector> $collectors */
        $collectors = $this->registry->getCollectorsForPeriod($period, $unitId, $professionId);

        $out = [
            'criteria' => [],
            'criteria_used' => [],
        ];

        foreach ($collectors as $collector) {
            $key = $collector->key();
            $raw = $collector->collect($period, $unitId, $userIds);

            // Ensure all users exist in raw map (default 0)
            foreach ($userIds as $uid) {
                if (!array_key_exists($uid, $raw)) {
                    $raw[$uid] = 0.0;
                }
            }

            $out['criteria'][$key] = [
                'label' => $collector->label(),
                'type' => $collector->type(),
                'source' => $collector->source(),
                'raw' => $raw,
                'readiness' => $collector->readiness($period, $unitId),
            ];
            $out['criteria_used'][] = $key;
        }

        return $out;
    }
}
