<?php

namespace App\Services\CriteriaEngine;

class CriteriaNormalizer
{
    /**
     * @param array<int,float> $rawByUser
     * @param array<int> $userIds
     * @return array{normalized: array<int,float>, total: float}
     */
    public function normalizeBenefit(array $rawByUser, array $userIds): array
    {
        $total = 0.0;
        foreach ($userIds as $uid) {
            $total += (float) ($rawByUser[(int) $uid] ?? 0.0);
        }

        $normalized = [];
        foreach ($userIds as $uid) {
            $uid = (int) $uid;
            $value = (float) ($rawByUser[$uid] ?? 0.0);
            $normalized[$uid] = $total > 0 ? ($value / $total) * 100.0 : 0.0;
        }

        return ['normalized' => $normalized, 'total' => $total];
    }

    /**
     * Cost normalization (stable):
     * - If max == 0 => 0 for all
     * - Else score = ((max - value) / max) * 100
     *
     * @param array<int,float> $rawByUser
     * @param array<int> $userIds
     * @return array{normalized: array<int,float>, max: float}
     */
    public function normalizeCost(array $rawByUser, array $userIds): array
    {
        $max = 0.0;
        foreach ($userIds as $uid) {
            $max = max($max, (float) ($rawByUser[(int) $uid] ?? 0.0));
        }

        $normalized = [];
        foreach ($userIds as $uid) {
            $uid = (int) $uid;
            $value = (float) ($rawByUser[$uid] ?? 0.0);
            if ($max <= 0) {
                $normalized[$uid] = 0.0;
            } else {
                $normalized[$uid] = (($max - $value) / $max) * 100.0;
            }
        }

        return ['normalized' => $normalized, 'max' => $max];
    }
}
