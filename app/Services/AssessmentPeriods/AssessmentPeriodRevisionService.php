<?php

namespace App\Services\AssessmentPeriods;

use App\Models\AssessmentApproval;
use App\Models\AssessmentPeriod;
use App\Models\PerformanceAssessment;
use App\Models\User;
use App\Support\AssessmentPeriodAudit;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class AssessmentPeriodRevisionService
{
    /**
     * @param array<string,mixed> $meta
     */
    public function markRejectedFromApproval(AssessmentPeriod $period, User $actor, int $rejectedLevel, string $reason, array $meta = []): void
    {
        $reason = trim($reason);
        if ($reason === '') {
            throw new \RuntimeException('Alasan penolakan wajib diisi.');
        }

        if ((string) ($period->status ?? '') !== AssessmentPeriod::STATUS_APPROVAL) {
            throw new \RuntimeException('Reject periode hanya valid saat status periode = approval.');
        }

        $updates = [
            'rejected_level' => $rejectedLevel,
            'rejected_by_id' => $actor->id,
            'rejected_at' => now(),
            'rejected_reason' => $reason,
        ];

        $period->update($updates);

        Cache::forget('ui.period_state_banner');

        $payload = array_merge([
            'rejected_level' => $rejectedLevel,
        ], $meta);

        AssessmentPeriodAudit::log((int) $period->id, (int) $actor->id, 'period_rejected', $reason, $payload);
    }

    public function openRevision(AssessmentPeriod $period, User $actor, string $reason): void
    {
        $reason = trim($reason);
        if ($reason === '') {
            throw new \RuntimeException('Alasan membuka revisi wajib diisi.');
        }

        if (!$actor->isAdministrasi()) {
            throw new \RuntimeException('Hanya Admin RS yang dapat membuka revisi.');
        }

        if ((string) ($period->status ?? '') !== AssessmentPeriod::STATUS_APPROVAL) {
            throw new \RuntimeException('Open Revision hanya dapat dilakukan ketika status periode = approval.');
        }

        if ($period->rejected_at === null) {
            throw new \RuntimeException('Open Revision hanya dapat dilakukan jika periode sedang REJECTED (rejected_at terisi).');
        }

        DB::transaction(function () use ($period, $actor, $reason) {
            $period->refresh();

            if ((string) ($period->status ?? '') !== AssessmentPeriod::STATUS_APPROVAL || $period->rejected_at === null) {
                throw new \RuntimeException('Periode tidak valid untuk Open Revision.');
            }

            $period->update([
                'status' => AssessmentPeriod::STATUS_REVISION,
                'revision_opened_by_id' => $actor->id,
                'revision_opened_at' => now(),
                'revision_opened_reason' => $reason,
            ]);

            Cache::forget('ui.period_state_banner');

            $this->invalidateApprovalsForPeriodAttempt($period, $actor->id, 'Open revision: approval harus diulang dari Level 1.');

            AssessmentPeriodAudit::log((int) $period->id, (int) $actor->id, 'revision_opened', $reason, [
            'attempt' => max(1, (int) ($period->approval_attempt ?? 0)),
            ]);
        });
    }

    public function resubmitFromRevision(AssessmentPeriod $period, User $actor, ?string $note = null): void
    {
        if (!$actor->isAdministrasi()) {
            throw new \RuntimeException('Hanya Admin RS yang dapat mengajukan ulang periode.');
        }

        if ((string) ($period->status ?? '') !== AssessmentPeriod::STATUS_REVISION) {
            throw new \RuntimeException('Resubmit hanya dapat dilakukan ketika status periode = revision.');
        }

        DB::transaction(function () use ($period, $actor, $note) {
            $period->refresh();

            if ((string) ($period->status ?? '') !== AssessmentPeriod::STATUS_REVISION) {
                throw new \RuntimeException('Periode tidak valid untuk resubmit.');
            }

            $currentAttempt = max(1, (int) ($period->approval_attempt ?? 0));
            $nextAttempt = $currentAttempt + 1;

            $period->update([
                'status' => AssessmentPeriod::STATUS_APPROVAL,
                'approval_attempt' => $nextAttempt,
                // Leaving revision_opened_* as history for UI. Reset rejected state.
                'rejected_level' => null,
                'rejected_by_id' => null,
                'rejected_at' => null,
                'rejected_reason' => null,
            ]);

            Cache::forget('ui.period_state_banner');

            // Create (or reset) Level 1 approvals for the new attempt.
            $this->ensureLevel1ApprovalsForPeriodAttempt($period, $nextAttempt);

            AssessmentPeriodAudit::log((int) $period->id, (int) $actor->id, 'revision_resubmitted', $note, [
                'attempt' => $nextAttempt,
            ]);
        });
    }

    private function ensureLevel1ApprovalsForPeriodAttempt(AssessmentPeriod $period, int $attempt): void
    {
        $assessments = PerformanceAssessment::query()
            ->where('assessment_period_id', $period->id)
            ->get(['id']);

        if ($assessments->isEmpty()) {
            return;
        }

        $adminApproverId = User::query()->role(User::ROLE_ADMINISTRASI)->orderBy('id')->value('id');

        foreach ($assessments as $pa) {
            AssessmentApproval::firstOrCreate(
                [
                    'performance_assessment_id' => $pa->id,
                    'level' => 1,
                    'attempt' => $attempt,
                ],
                [
                    'approver_id' => $adminApproverId,
                    'status' => 'pending',
                    'note' => null,
                    'acted_at' => null,
                ]
            );

            // Reset assessment validation status to pending for the new attempt.
            $pa->update(['validation_status' => 'pending']);
        }
    }

    private function invalidateApprovalsForPeriodAttempt(AssessmentPeriod $period, int $actorId, string $reason): void
    {
        if (!Schema::hasTable('assessment_approvals') || !Schema::hasTable('performance_assessments')) {
            return;
        }

        $attempt = max(1, (int) ($period->approval_attempt ?? 0));

        DB::table('assessment_approvals as aa')
            ->join('performance_assessments as pa', 'pa.id', '=', 'aa.performance_assessment_id')
            ->where('pa.assessment_period_id', $period->id)
            ->where('aa.attempt', $attempt)
            ->whereNull('aa.invalidated_at')
            ->update([
                'aa.invalidated_at' => now(),
                'aa.invalidated_by_id' => $actorId,
                'aa.invalidated_reason' => $reason,
                'aa.updated_at' => now(),
            ]);
    }
}
