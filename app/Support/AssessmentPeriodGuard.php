<?php

namespace App\Support;

use App\Models\AssessmentPeriod;
use Illuminate\Support\Facades\Schema;

final class AssessmentPeriodGuard
{
    public static function requireActive(?AssessmentPeriod $period, string $actionLabel = 'Aksi'): void
    {
        if (!$period) {
            abort(403, $actionLabel . ' tidak dapat dilakukan: periode penilaian tidak ditemukan.');
        }

        if (!$period->isCurrentlyActive()) {
            $msg = sprintf(
                '%s hanya dapat dilakukan ketika periode sedang berjalan (hari ini berada pada rentang tanggal periode). Status periode saat ini: %s.',
                $actionLabel,
                (string) ($period->status ?? '-')
            );
            abort(403, $msg);
        }
    }

    public static function requireLocked(?AssessmentPeriod $period, string $actionLabel = 'Aksi'): void
    {
        self::requireStatus($period, AssessmentPeriod::STATUS_LOCKED, $actionLabel, 'LOCKED');
    }

    public static function resolveById(?int $periodId): ?AssessmentPeriod
    {
        if (!$periodId) {
            return null;
        }
        if (!Schema::hasTable('assessment_periods')) {
            return null;
        }

        return AssessmentPeriod::query()->find((int) $periodId);
    }

    public static function resolveActive(): ?AssessmentPeriod
    {
        if (!Schema::hasTable('assessment_periods')) {
            return null;
        }

        // Keep lifecycle in sync (auto draft/active/locked by date + auto close)
        try {
            if (class_exists(\App\Services\AssessmentPeriodLifecycleService::class)) {
                app(\App\Services\AssessmentPeriodLifecycleService::class)->sync();
            } else {
                AssessmentPeriod::syncByNow();
            }
        } catch (\Throwable $e) {
            // fallback
            AssessmentPeriod::syncByNow();
        }

        return AssessmentPeriod::query()->active()->orderByDesc('start_date')->first();
    }

    public static function resolveLatestLocked(): ?AssessmentPeriod
    {
        if (!Schema::hasTable('assessment_periods')) {
            return null;
        }

        return AssessmentPeriod::query()
            ->where('status', AssessmentPeriod::STATUS_LOCKED)
            ->orderByDesc('start_date')
            ->first();
    }

    public static function requireDeletable(?AssessmentPeriod $period, string $actionLabel = 'Hapus'): void
    {
        if (!$period) {
            abort(403, $actionLabel . ' tidak dapat dilakukan: periode penilaian tidak ditemukan.');
        }

        if ($period->isNonDeletable()) {
            $label = AssessmentPeriod::statusLabel((string) $period->status);
            abort(403, sprintf('Periode dengan status %s tidak boleh dihapus.', $label));
        }
    }

    private static function requireStatus(?AssessmentPeriod $period, string $requiredStatus, string $actionLabel, string $requiredLabel): void
    {
        $actual = (string) ($period?->status ?? '');

        if (!$period) {
            abort(403, $actionLabel . ' tidak dapat dilakukan: periode penilaian tidak ditemukan.');
        }

        if ($actual !== $requiredStatus) {
            $msg = sprintf(
                '%s hanya dapat dilakukan ketika periode %s. Status periode saat ini: %s.',
                $actionLabel,
                $requiredLabel,
                $actual !== '' ? strtoupper($actual) : '-'
            );
            abort(403, $msg);
        }
    }
}
