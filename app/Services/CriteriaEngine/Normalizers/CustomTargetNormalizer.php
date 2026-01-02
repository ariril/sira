<?php

namespace App\Services\CriteriaEngine\Normalizers;

use App\Services\CriteriaEngine\Contracts\CriteriaNormalizer;

class CustomTargetNormalizer implements CriteriaNormalizer
{
    private function clampPct(float $value): float
    {
        if (!is_finite($value)) {
            return 0.0;
        }

        return max(0.0, min(100.0, $value));
    }

    public function normalize(array $rawByUserId, array $context): array
    {
        $type = ((string) ($context['type'] ?? 'benefit')) === 'cost' ? 'cost' : 'benefit';
        $target = array_key_exists('target', $context) ? (float) ($context['target'] ?? 0.0) : 0.0;

        $scopeTotal = 0.0;
        $scopeMax = 0.0;
        $count = 0;
        foreach ($rawByUserId as $v) {
            $val = (float) ($v ?? 0.0);
            $scopeTotal += $val;
            $scopeMax = max($scopeMax, $val);
            $count++;
        }
        $scopeAvg = $count > 0 ? ($scopeTotal / $count) : 0.0;

        $normalized = [];
        foreach ($rawByUserId as $uid => $raw) {
            $raw = (float) ($raw ?? 0.0);

            if ($target <= 0.0) {
                $normalized[(int) $uid] = $type === 'cost' ? 100.0 : 0.0;
                continue;
            }

            if ($type === 'cost') {
                $n = (1.0 - ($raw / $target)) * 100.0;
                $normalized[(int) $uid] = $this->clampPct(min(100.0, $n));
            } else {
                $n = ($raw / $target) * 100.0;
                $normalized[(int) $uid] = $this->clampPct(min(100.0, $n));
            }
        }

        $formulaPreview = $type === 'cost'
            ? 'MIN(100, (1 - ({raw_i}/{target})) * 100)'
            : 'MIN(100, ({raw_i}/{target}) * 100)';

        return [
            'normalized' => $normalized,
            'meta' => [
                'scope_total' => (float) $scopeTotal,
                'scope_max_raw' => (float) $scopeMax,
                'scope_avg_raw' => (float) $scopeAvg,
                'target' => $target > 0.0 ? (float) $target : null,
                'formula_preview' => $formulaPreview,
            ],
        ];
    }
}
