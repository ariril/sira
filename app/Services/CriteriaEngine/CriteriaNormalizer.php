<?php

namespace App\Services\CriteriaEngine;

use App\Services\CriteriaEngine\Contracts\CriteriaNormalizer as CriteriaNormalizerContract;

class CriteriaNormalizer
{
    public function __construct(private readonly CriteriaRegistry $registry)
    {
    }

    private function clampPct(float $value): float
    {
        if (!is_finite($value)) {
            return 0.0;
        }

        return max(0.0, min(100.0, $value));
    }

    /**
     * Normalize values using a policy basis.
     *
     * Policies:
     * - total_unit: basis = sum(values)
     * - max_unit: basis = max(values)
     * - average_unit: basis = avg(values)
     * - custom_target: basis = customTarget (must be > 0)
     *
     * Output is clamped to [0, 100].
     *
     * @param array<int,float> $rawByUser
     * @param array<int> $userIds
     * @return array{normalized: array<int,float>, basis_value: float}
     */
    public function normalizeWithBasis(string $type, string $basis, array $rawByUser, array $userIds, ?float $customTarget = null): array
    {
        // Backward compatible wrapper used by existing services.
        $type = ($type === 'cost') ? 'cost' : 'benefit';
        $basis = (string) ($basis ?: 'total_unit');

        // Ensure every uid exists.
        foreach ($userIds as $uid) {
            $uid = (int) $uid;
            if (!array_key_exists($uid, $rawByUser)) {
                $rawByUser[$uid] = 0.0;
            }
        }

        $normalizer = $this->registry->normalizerForBasis($basis);
        $out = $normalizer->normalize($rawByUser, [
            'type' => $type,
            'target' => $customTarget,
        ]);

        $meta = (array) ($out['meta'] ?? []);
        $basisValue = match ($basis) {
            'max_unit' => (float) ($meta['scope_max_raw'] ?? 0.0),
            'average_unit' => (float) ($meta['scope_avg_raw'] ?? 0.0),
            'custom_target' => (float) (($meta['target'] ?? null) ?? 0.0),
            default => (float) ($meta['scope_total'] ?? 0.0),
        };

        return [
            'normalized' => (array) ($out['normalized'] ?? []),
            'basis_value' => $basisValue,
        ];
    }

    /**
     * Normalize a set of criteria and compute relative scores.
     *
     * @param array<string, array{type:string,raw:array<int,float>}> $criteriaAgg
     * @param array<int> $userIds
     * @param callable(string): array{basis:string, custom_target:?float} $policyResolver
     * @return array{
     *   normalized_by_key: array<string, array<int,float>>,
     *   relative_by_key: array<string, array<int,float>>,
     *   meta_by_key: array<string, array{scope_total:float,scope_max_raw:float,scope_avg_raw:float,target:?float,formula_preview:string,max_normalized_in_scope:float}>
     * }
     */
    public function normalizeCriteriaSet(array $criteriaAgg, array $userIds, callable $policyResolver): array
    {
        $normalizedByKey = [];
        $relativeByKey = [];
        $metaByKey = [];

        foreach ($criteriaAgg as $key => $info) {
            $key = (string) $key;
            $type = ((string) ($info['type'] ?? 'benefit')) === 'cost' ? 'cost' : 'benefit';
            $raw = (array) ($info['raw'] ?? []);

            foreach ($userIds as $uid) {
                $uid = (int) $uid;
                if (!array_key_exists($uid, $raw)) {
                    $raw[$uid] = 0.0;
                }
            }

            $policy = (array) ($policyResolver($key) ?? []);
            $basis = (string) (($policy['basis'] ?? null) ?: 'total_unit');
            $target = $policy['custom_target'] !== null ? (float) $policy['custom_target'] : null;

            /** @var CriteriaNormalizerContract $normalizer */
            $normalizer = $this->registry->normalizerForBasis($basis);
            $out = $normalizer->normalize($raw, [
                'type' => $type,
                'target' => $target,
            ]);

            $nByUser = (array) ($out['normalized'] ?? []);
            $meta = (array) ($out['meta'] ?? []);

            $maxN = 0.0;
            foreach ($userIds as $uid) {
                $uid = (int) $uid;
                $maxN = max($maxN, (float) ($nByUser[$uid] ?? 0.0));
            }

            $rByUser = [];
            foreach ($userIds as $uid) {
                $uid = (int) $uid;
                $n = (float) ($nByUser[$uid] ?? 0.0);
                $r = $maxN > 0.0 ? (($n / $maxN) * 100.0) : 0.0;
                $rByUser[$uid] = $this->clampPct($r);
            }

            $normalizedByKey[$key] = $nByUser;
            $relativeByKey[$key] = $rByUser;
            $metaByKey[$key] = [
                'scope_total' => (float) ($meta['scope_total'] ?? 0.0),
                'scope_max_raw' => (float) ($meta['scope_max_raw'] ?? 0.0),
                'scope_avg_raw' => (float) ($meta['scope_avg_raw'] ?? 0.0),
                'target' => array_key_exists('target', $meta) ? ($meta['target'] !== null ? (float) $meta['target'] : null) : null,
                'formula_preview' => (string) ($meta['formula_preview'] ?? ''),
                'max_normalized_in_scope' => (float) $maxN,
            ];
        }

        return [
            'normalized_by_key' => $normalizedByKey,
            'relative_by_key' => $relativeByKey,
            'meta_by_key' => $metaByKey,
        ];
    }

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
