<?php

namespace App\Services\CriteriaEngine\Normalizers;

use App\Services\CriteriaEngine\Contracts\CriteriaNormalizer;

class MaxUnitNormalizer implements CriteriaNormalizer
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

            if ($scopeMax <= 0.0) {
                $normalized[(int) $uid] = $type === 'cost' ? 100.0 : 0.0;
                continue;
            }

            $ratio = $raw / $scopeMax;
            $n = $type === 'cost' ? (1.0 - $ratio) * 100.0 : $ratio * 100.0;
            $normalized[(int) $uid] = $this->clampPct($n);
        }

        $formulaPreview = $type === 'cost'
            ? '(1 - ({raw_i}/{scope_max_raw})) * 100'
            : '{raw_i} / {scope_max_raw} * 100';

        return [
            'normalized' => $normalized,
            'meta' => [
                'scope_total' => (float) $scopeTotal,
                'scope_max_raw' => (float) $scopeMax,
                'scope_avg_raw' => (float) $scopeAvg,
                'target' => null,
                'formula_preview' => $formulaPreview,
            ],
        ];
    }
}
