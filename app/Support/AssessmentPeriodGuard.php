<?php

namespace App\Support;

use App\Models\AssessmentPeriod;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Support\Facades\Schema;

final class AssessmentPeriodGuard
{
    private static function deny(string $message): void
    {
        if (app()->runningInConsole()) {
            throw new \RuntimeException($message);
        }

        try {
            if (function_exists('request') && request()?->expectsJson()) {
                abort(403, $message);
            }
        } catch (\Throwable $e) {
            // ignore and fall back
        }

        throw new HttpResponseException(
            redirect()->back()->with('error', $message)
        );
    }

    public static function requireDraftEditable(?AssessmentPeriod $period, string $actionLabel = 'Edit'): void
    {
        if (!$period) {
            self::deny($actionLabel . ' tidak dapat dilakukan: periode penilaian tidak ditemukan.');
        }

        $status = (string) ($period->status ?? '');
        if ($status !== AssessmentPeriod::STATUS_DRAFT) {
            $label = $status !== '' ? strtoupper($status) : '-';
            self::deny(sprintf('%s hanya dapat dilakukan ketika periode berstatus DRAFT. Status periode saat ini: %s.', $actionLabel, $label));
        }

        if (method_exists($period, 'isCurrentlyActive') && $period->isCurrentlyActive()) {
            self::deny(sprintf('%s tidak dapat dilakukan: periode sudah mulai berjalan (AKTIF).', $actionLabel));
        }
    }

    public static function requireActive(?AssessmentPeriod $period, string $actionLabel = 'Aksi'): void
    {
        if (!$period) {
            self::deny($actionLabel . ' tidak dapat dilakukan: periode penilaian tidak ditemukan.');
        }

        $status = (string) ($period->status ?? '');
        if ($status !== AssessmentPeriod::STATUS_ACTIVE) {
            $msg = sprintf(
                '%s hanya dapat dilakukan ketika status periode = AKTIF. Status periode saat ini: %s.',
                $actionLabel,
                $status !== '' ? strtoupper($status) : '-'
            );
            self::deny($msg);
        }

        if (!$period->isCurrentlyActive()) {
            $msg = sprintf(
                '%s hanya dapat dilakukan ketika periode sedang berjalan (hari ini berada pada rentang tanggal periode). Status periode saat ini: %s.',
                $actionLabel,
                (string) ($period->status ?? '-')
            );
            self::deny($msg);
        }
    }

    public static function requireActiveOrRevision(?AssessmentPeriod $period, string $actionLabel = 'Aksi'): void
    {
        if (!$period) {
            self::deny($actionLabel . ' tidak dapat dilakukan: periode penilaian tidak ditemukan.');
        }

        $status = (string) ($period->status ?? '');

        if ($status === AssessmentPeriod::STATUS_REVISION) {
            return;
        }

        self::requireActive($period, $actionLabel);
    }

    public static function forbidWhenApprovalRejected(?AssessmentPeriod $period, string $actionLabel = 'Aksi'): void
    {
        if (!$period) {
            return;
        }

        if (method_exists($period, 'isRejectedApproval') && $period->isRejectedApproval()) {
            self::deny($actionLabel . ' tidak dapat dilakukan: periode sedang DITOLAK (approval rejected).');
        }
    }

    public static function requireLocked(?AssessmentPeriod $period, string $actionLabel = 'Aksi'): void
    {
        self::requireStatus($period, AssessmentPeriod::STATUS_LOCKED, $actionLabel, 'LOCKED');
    }

    public static function requireLockedOrRevision(?AssessmentPeriod $period, string $actionLabel = 'Aksi'): void
    {
        if (!$period) {
            self::deny($actionLabel . ' tidak dapat dilakukan: periode penilaian tidak ditemukan.');
        }

        $status = (string) ($period->status ?? '');
        if (in_array($status, [AssessmentPeriod::STATUS_LOCKED, AssessmentPeriod::STATUS_REVISION], true)) {
            return;
        }

        $msg = sprintf(
            '%s hanya dapat dilakukan ketika periode LOCKED atau REVISION. Status periode saat ini: %s.',
            $actionLabel,
            $status !== '' ? strtoupper($status) : '-'
        );
        self::deny($msg);
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
            if (class_exists(\App\Services\AssessmentPeriods\AssessmentPeriodLifecycleService::class)) {
                app(\App\Services\AssessmentPeriods\AssessmentPeriodLifecycleService::class)->sync();
            } else {
                AssessmentPeriod::syncByNow();
            }
        } catch (\Throwable $e) {
            // fallback
            AssessmentPeriod::syncByNow();
        }

        return AssessmentPeriod::query()
            ->where('status', AssessmentPeriod::STATUS_ACTIVE)
            ->orderByDesc('start_date')
            ->first();
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
            self::deny($actionLabel . ' tidak dapat dilakukan: periode penilaian tidak ditemukan.');
        }

        if ($period->isNonDeletable()) {
            $label = AssessmentPeriod::statusLabel((string) $period->status);
            self::deny(sprintf('Periode dengan status %s tidak boleh dihapus.', $label));
        }
    }

    private static function requireStatus(?AssessmentPeriod $period, string $requiredStatus, string $actionLabel, string $requiredLabel): void
    {
        $actual = (string) ($period?->status ?? '');

        if (!$period) {
            self::deny($actionLabel . ' tidak dapat dilakukan: periode penilaian tidak ditemukan.');
        }

        if ($actual !== $requiredStatus) {
            $msg = sprintf(
                '%s hanya dapat dilakukan ketika periode %s. Status periode saat ini: %s.',
                $actionLabel,
                $requiredLabel,
                $actual !== '' ? strtoupper($actual) : '-'
            );
            self::deny($msg);
        }
    }
}
