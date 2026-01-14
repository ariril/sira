<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

final class AssessmentPeriodAudit
{
    /**
     * Best-effort audit logger (no-throw).
     *
     * @param array<string,mixed> $meta
     */
    public static function log(int $periodId, ?int $actorId, string $action, ?string $reason = null, array $meta = []): void
    {
        try {
            if (!Schema::hasTable('assessment_period_audit_logs')) {
                return;
            }

            DB::table('assessment_period_audit_logs')->insert([
                'assessment_period_id' => $periodId,
                'actor_id' => $actorId,
                'action' => $action,
                'reason' => $reason,
                'meta' => empty($meta) ? null : json_encode($meta, JSON_UNESCAPED_UNICODE),
                'created_at' => now(),
            ]);
        } catch (\Throwable $e) {
            // swallow
        }
    }
}
