<?php

namespace App\Support;

final class ProportionalAllocator
{
    /**
     * Allocate a total amount proportionally by weights, returning amounts rounded to 2 decimals
     * such that the SUM of returned amounts equals the rounded total amount.
     *
     * Uses the Largest Remainder method on cents.
     *
     * @param array<int|string, float|int> $weights
     * @return array<int|string, float>
     */
    public static function allocate(float $totalAmount, array $weights): array
    {
        if (empty($weights)) {
            return [];
        }

        $totalCents = (int) round($totalAmount * 100);
        if ($totalCents <= 0) {
            return array_fill_keys(array_keys($weights), 0.0);
        }

        $cleanWeights = [];
        $sumWeights = 0.0;
        foreach ($weights as $key => $w) {
            $val = (float) $w;
            if (!is_finite($val) || $val < 0) {
                $val = 0.0;
            }
            $cleanWeights[$key] = $val;
            $sumWeights += $val;
        }

        // If all weights are 0, distribute equally.
        if ($sumWeights <= 0.0) {
            $n = max(count($cleanWeights), 1);
            $cleanWeights = array_fill_keys(array_keys($cleanWeights), 1.0);
            $sumWeights = (float) $n;
        }

        $baseCents = [];
        $fractions = [];
        $sumBase = 0;

        foreach ($cleanWeights as $key => $w) {
            $exactCents = ($totalCents * ($w / $sumWeights));
            // Bias tiny epsilon to avoid floating artifacts like 12.0000000002
            $base = (int) floor($exactCents + 1e-9);
            $baseCents[$key] = $base;
            $fractions[$key] = $exactCents - $base;
            $sumBase += $base;
        }

        $remainder = $totalCents - $sumBase;
        if ($remainder > 0) {
            // Sort by fractional part desc, tie-break by key asc for determinism.
            $keys = array_keys($cleanWeights);
            usort($keys, function ($a, $b) use ($fractions) {
                $fa = $fractions[$a] ?? 0.0;
                $fb = $fractions[$b] ?? 0.0;
                if ($fb === $fa) {
                    return (string) $a <=> (string) $b;
                }
                return $fb <=> $fa;
            });

            for ($i = 0; $i < $remainder; $i++) {
                $k = $keys[$i % count($keys)];
                $baseCents[$k] += 1;
            }
        }

        $out = [];
        foreach ($baseCents as $key => $cents) {
            $out[$key] = round($cents / 100, 2);
        }

        return $out;
    }
}
