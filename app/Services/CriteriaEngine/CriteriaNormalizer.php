<?php

namespace App\Services\CriteriaEngine;

class CriteriaNormalizer
{
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
        // NOTE: Per definisi Skor Kinerja yang berlaku, normalisasi tidak dibedakan
        // antara benefit vs cost. Pembedaan benefit/cost dilakukan pada tahap nilai relatif.
        // Parameter $type dipertahankan untuk kompatibilitas pemanggil.
        $basis = $basis ?: 'total_unit';

        $sum = 0.0;
        $max = 0.0;
        $count = 0;
        foreach ($userIds as $uid) {
            $uid = (int) $uid;
            $val = (float) ($rawByUser[$uid] ?? 0.0);
            $sum += $val;
            $max = max($max, $val);
            $count++;
        }
        $avg = $count > 0 ? ($sum / $count) : 0.0;

        $basisValue = 0.0;
        switch ($basis) {
            case 'max_unit':
                $basisValue = $max;
                break;
            case 'average_unit':
                $basisValue = $avg;
                break;
            case 'custom_target':
                $basisValue = (float) ($customTarget ?? 0.0);
                break;
            case 'total_unit':
            default:
                $basisValue = $sum;
                break;
        }

        $normalized = [];
        foreach ($userIds as $uid) {
            $uid = (int) $uid;
            $value = (float) ($rawByUser[$uid] ?? 0.0);

            if ($basisValue <= 0.0) {
                $normalized[$uid] = 0.0;
                continue;
            }

            // Rumus baku: (nilai individu / basis) × 100
            // Catatan: tidak di-clamp ke 100 agar konsisten dengan rumus baku.
            $normalized[$uid] = ($value / $basisValue) * 100.0;
        }

        return ['normalized' => $normalized, 'basis_value' => $basisValue];
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
