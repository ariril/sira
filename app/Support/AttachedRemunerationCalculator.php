<?php

namespace App\Support;

final class AttachedRemunerationCalculator
{
    private static function clampPct(float $value): float
    {
        if (!is_finite($value)) {
            return 0.0;
        }

        return max(0.0, min(100.0, $value));
    }

    /**
     * Attached-per-employee remuneration calculation.
     *
     * Rules:
     * - remuneration_max_per_employee = allocation / headcount
     * - payout_percent = total_wsm_score (0..100)
     * - amount_final = remuneration_max_per_employee * (payout_percent/100)
     * - leftover_amount = allocation - SUM(amount_final)
     * - leftover is NOT redistributed.
     *
     * @param array<int|string, float|int|null> $payoutPercentByUser
     * @return array{headcount:int, remuneration_max_per_employee:float, amounts:array<int|string,float>, sum_amounts:float, leftover_amount:float}
     */
    public static function calculate(float $allocationAmount, array $payoutPercentByUser, int $precision = 2): array
    {
        $keys = array_keys($payoutPercentByUser);
        $headcount = count($keys);

        if ($headcount <= 0 || !is_finite($allocationAmount) || $allocationAmount <= 0) {
            return [
                'headcount' => max(0, $headcount),
                'remuneration_max_per_employee' => 0.0,
                'amounts' => array_fill_keys($keys, 0.0),
                'sum_amounts' => 0.0,
                'leftover_amount' => round(max(0.0, $allocationAmount), $precision),
            ];
        }

        $remunMax = $allocationAmount / $headcount;

        $amounts = [];
        $sum = 0.0;
        foreach ($payoutPercentByUser as $userId => $pct) {
            $p = self::clampPct((float) ($pct ?? 0.0));
            $amt = round($remunMax * ($p / 100.0), $precision);
            $amounts[$userId] = $amt;
            $sum += $amt;
        }

        $sum = round($sum, $precision);
        $leftover = round($allocationAmount - $sum, $precision);
        if (!is_finite($leftover)) {
            $leftover = 0.0;
        }

        return [
            'headcount' => $headcount,
            'remuneration_max_per_employee' => round($remunMax, $precision),
            'amounts' => $amounts,
            'sum_amounts' => $sum,
            'leftover_amount' => $leftover,
        ];
    }
}
