<?php

namespace App\Services\AssessmentApprovals;

use App\Enums\AssessmentApprovalStatus;
use App\Enums\AssessmentValidationStatus;
use App\Models\AssessmentApproval;
use App\Models\AssessmentPeriod;
use App\Models\PerformanceAssessment;
use App\Models\User;
use App\Services\AssessmentPeriods\AssessmentPeriodRevisionService;
use Illuminate\Support\Facades\DB;

class AssessmentApprovalService
{
    public function __construct(
        private readonly AssessmentPeriodRevisionService $periodRevisionService,
    ) {
    }

    public function approve(AssessmentApproval $approval, User $actor, ?string $note = null): void
    {
        DB::transaction(function () use ($approval, $actor, $note) {
            $approval->refresh();

            $assessment = $approval->performanceAssessment()->with(['assessmentPeriod', 'user'])->firstOrFail();
            $period = $assessment->assessmentPeriod;

            $this->assertPeriodAllowsApprovalActions($period);
            $this->assertApprovalIsCurrentAttempt($approval, $period);

            $this->assertActorCanActOnApproval($approval, $actor);
            $this->assertPending($approval);
            $this->assertPreviousLevelsApproved($approval);

            $approval->update([
                'status' => AssessmentApprovalStatus::APPROVED->value,
                'note' => $this->normalizeOptionalNote($note),
                'acted_at' => now(),
            ]);

            AssessmentApprovalFlow::ensureNextLevel($approval, $actor->id);

            $this->syncAssessmentValidationStatus($approval->performanceAssessment()->firstOrFail());
        });
    }

    public function reject(AssessmentApproval $approval, User $actor, string $note): void
    {
        DB::transaction(function () use ($approval, $actor, $note) {
            $approval->refresh();

            $assessment = $approval->performanceAssessment()->with('assessmentPeriod')->firstOrFail();
            $period = $assessment->assessmentPeriod;

            $this->assertPeriodAllowsApprovalActions($period);
            $this->assertApprovalIsCurrentAttempt($approval, $period);

            $note = trim($note);
            if ($note === '') {
                throw new \RuntimeException('Catatan penolakan wajib diisi.');
            }

            $this->assertActorCanActOnApproval($approval, $actor);
            $this->assertPending($approval);
            $this->assertPreviousLevelsApproved($approval);

            $approval->update([
                'status' => AssessmentApprovalStatus::REJECTED->value,
                'note' => $note,
                'acted_at' => now(),
            ]);

            AssessmentApprovalFlow::invalidateFutureLevels($approval, (int) $actor->id, 'Penolakan pada level ' . (int) ($approval->level ?? 0));

            $this->syncAssessmentValidationStatus($approval->performanceAssessment()->firstOrFail());

            // Period-level rejected state (status tetap approval; rejected_* metadata diisi)
            if ($period && (string) ($period->status ?? '') === AssessmentPeriod::STATUS_APPROVAL) {
                $this->periodRevisionService->markRejectedFromApproval(
                    $period,
                    $actor,
                    (int) ($approval->level ?? 0),
                    $note,
                    [
                        'rejected_approval_id' => (int) $approval->id,
                        'rejected_performance_assessment_id' => (int) $assessment->id,
                        'rejected_staff_id' => (int) ($assessment->user_id ?? 0),
                        'rejected_staff_name' => (string) ($assessment->user?->name ?? ''),
                    ]
                );
            }
        });
    }

    public function resubmitAfterReject(AssessmentApproval $anyApprovalForAssessment, User $actor): void
    {
        DB::transaction(function () use ($anyApprovalForAssessment, $actor) {
            if (!$actor->isAdministrasi()) {
                throw new \RuntimeException('Hanya Admin RS yang dapat melakukan ajukan ulang.');
            }

            $assessment = $anyApprovalForAssessment->performanceAssessment()->firstOrFail();

            // NOTE: New workflow uses period-level revision/resubmit.
            // Keep this method for legacy manual resets, but disallow when period is in rejected-approval/revision.
            $period = $assessment->assessmentPeriod()->first();
            if ($period && (method_exists($period, 'isRejectedApproval') && $period->isRejectedApproval())) {
                throw new \RuntimeException('Tidak dapat ajukan ulang individual: periode sedang DITOLAK. Gunakan Open Revision + Resubmit periode.');
            }
            if ($period && (string) ($period->status ?? '') === AssessmentPeriod::STATUS_REVISION) {
                throw new \RuntimeException('Tidak dapat ajukan ulang individual ketika periode sedang revision.');
            }

            $approvals = AssessmentApproval::query()
                ->where('performance_assessment_id', $assessment->id)
                ->where('attempt', (int) ($period?->approval_attempt ?? 1))
                ->whereNull('invalidated_at')
                ->orderBy('level')
                ->get();

            $rejectedLevels = $approvals
                ->filter(fn ($a) => $a->status === AssessmentApprovalStatus::REJECTED)
                ->pluck('level')
                ->map(fn ($v) => (int) $v)
                ->values();

            if ($rejectedLevels->isEmpty()) {
                throw new \RuntimeException('Tidak ada penolakan untuk diajukan ulang.');
            }

            // If multiple rejections exist (legacy data), reset from the earliest rejected level.
            $resetFromLevel = (int) $rejectedLevels->min();

            AssessmentApproval::query()
                ->where('performance_assessment_id', $assessment->id)
                ->where('level', '>=', $resetFromLevel)
                ->where('attempt', (int) ($period?->approval_attempt ?? 1))
                ->whereNull('invalidated_at')
                ->update([
                    'status' => AssessmentApprovalStatus::PENDING->value,
                    'note' => null,
                    'acted_at' => null,
                ]);

            $this->syncAssessmentValidationStatus($assessment);
        });
    }

    public function syncAssessmentValidationStatus(PerformanceAssessment $assessment): void
    {
        $period = $assessment->assessmentPeriod()->first();
        $attempt = (int) ($period?->approval_attempt ?? 1);

        $approvals = AssessmentApproval::query()
            ->where('performance_assessment_id', $assessment->id)
            ->where('attempt', $attempt)
            ->whereNull('invalidated_at')
            ->get(['level', 'status']);

        $hasRejected = $approvals->contains(fn ($a) => $a->status === AssessmentApprovalStatus::REJECTED);
        if ($hasRejected) {
            $assessment->update(['validation_status' => AssessmentValidationStatus::REJECTED->value]);
            return;
        }

        $approvedLevels = $approvals
            ->filter(fn ($a) => $a->status === AssessmentApprovalStatus::APPROVED)
            ->pluck('level')
            ->map(fn ($v) => (int) $v)
            ->unique()
            ->sort()
            ->values()
            ->all();

        $isFullyApproved = $approvedLevels === [1, 2, 3];
        if ($isFullyApproved) {
            $assessment->update(['validation_status' => AssessmentValidationStatus::VALIDATED->value]);
            return;
        }

        $assessment->update(['validation_status' => AssessmentValidationStatus::PENDING->value]);
    }

    private function assertPeriodAllowsApprovalActions(?AssessmentPeriod $period): void
    {
        if (!$period) {
            throw new \RuntimeException('Periode penilaian tidak ditemukan.');
        }

        if ((string) ($period->status ?? '') !== AssessmentPeriod::STATUS_APPROVAL) {
            $status = strtoupper((string) ($period->status ?? '-'));
            throw new \RuntimeException("Approval hanya dapat diproses saat status periode = APPROVAL. Status periode saat ini: {$status}.");
        }

        if (method_exists($period, 'isRejectedApproval') && $period->isRejectedApproval()) {
            throw new \RuntimeException('Periode sedang DITOLAK. Semua modul bersifat read-only sampai Admin RS membuka revisi.');
        }
    }

    private function assertApprovalIsCurrentAttempt(AssessmentApproval $approval, AssessmentPeriod $period): void
    {
        $currentAttempt = (int) ($period->approval_attempt ?? 1);
        $approvalAttempt = (int) ($approval->attempt ?? 1);
        if ($approvalAttempt !== $currentAttempt) {
            throw new \RuntimeException('Approval ini bukan attempt yang aktif untuk periode ini.');
        }
        if ($approval->invalidated_at !== null) {
            throw new \RuntimeException('Approval ini sudah tidak berlaku (invalidated).');
        }
    }

    public function assertCanViewPerformanceAssessment(User $actor, PerformanceAssessment $assessment): void
    {
        // Admin RS can view all.
        if ($actor->isAdministrasi()) {
            return;
        }

        $targetUnitId = (int) ($assessment->user?->unit_id ?? 0);
        if ($targetUnitId <= 0) {
            throw new \RuntimeException('Unit pegawai tidak ditemukan.');
        }

        if ($actor->isKepalaUnit()) {
            if ((int) ($actor->unit_id ?? 0) !== $targetUnitId) {
                throw new \RuntimeException('Tidak memiliki akses untuk melihat penilaian ini.');
            }
            return;
        }

        if ($actor->isKepalaPoliklinik()) {
            $myUnitId = (int) ($actor->unit_id ?? 0);
            if ($myUnitId <= 0) {
                throw new \RuntimeException('Unit Kepala Poliklinik tidak ditemukan.');
            }

            // Allow own unit
            if ($targetUnitId === $myUnitId) {
                return;
            }

            // If the unit hierarchy is configured, only allow child units.
            // Otherwise (fallback dataset), allow all units of type 'poliklinik'
            // to match the listing scope used in the controller/dashboard.
            $hasChildren = DB::table('units')
                ->where('parent_id', $myUnitId)
                ->exists();

            if ($hasChildren) {
                $isChildUnit = DB::table('units')
                    ->where('id', $targetUnitId)
                    ->where('parent_id', $myUnitId)
                    ->exists();

                if (!$isChildUnit) {
                    throw new \RuntimeException('Tidak memiliki akses untuk melihat penilaian ini.');
                }
                return;
            }

            $isPoliklinikUnit = DB::table('units')
                ->where('id', $targetUnitId)
                ->where('type', 'poliklinik')
                ->exists();

            if (!$isPoliklinikUnit) {
                throw new \RuntimeException('Tidak memiliki akses untuk melihat penilaian ini.');
            }
            return;
        }

        throw new \RuntimeException('Tidak memiliki akses.');
    }

    private function assertActorCanActOnApproval(AssessmentApproval $approval, User $actor): void
    {
        $level = (int) ($approval->level ?? 0);
        if ($level < 1 || $level > 3) {
            throw new \RuntimeException('Level approval tidak valid.');
        }

        $roleOk = match ($level) {
            1 => $actor->isAdministrasi(),
            2 => $actor->isKepalaUnit(),
            3 => $actor->isKepalaPoliklinik(),
            default => false,
        };
        if (!$roleOk) {
            throw new \RuntimeException('Tidak memiliki akses untuk memproses approval level ini.');
        }

        $assessment = $approval->performanceAssessment()->with('user')->firstOrFail();
        $this->assertCanViewPerformanceAssessment($actor, $assessment);

        // If approver_id is set, it must match the actor.
        if ($approval->approver_id !== null && (int) $approval->approver_id !== (int) $actor->id) {
            throw new \RuntimeException('Approval ini ditugaskan untuk approver lain.');
        }
    }

    private function assertPending(AssessmentApproval $approval): void
    {
        if ($approval->status !== AssessmentApprovalStatus::PENDING) {
            throw new \RuntimeException('Status saat ini tidak dapat diproses.');
        }
    }

    private function assertPreviousLevelsApproved(AssessmentApproval $approval): void
    {
        $level = (int) ($approval->level ?? 0);
        if ($level <= 1) {
            return;
        }

        $attempt = (int) ($approval->attempt ?? 1);

        $requiredLevels = range(1, $level - 1);
        $approvedLevels = AssessmentApproval::query()
            ->where('performance_assessment_id', $approval->performance_assessment_id)
            ->where('attempt', $attempt)
            ->whereNull('invalidated_at')
            ->whereIn('level', $requiredLevels)
            ->where('status', AssessmentApprovalStatus::APPROVED->value)
            ->pluck('level')
            ->map(fn($v) => (int) $v)
            ->unique()
            ->sort()
            ->values()
            ->all();

        if ($approvedLevels !== $requiredLevels) {
            throw new \RuntimeException('Belum dapat diproses: menunggu persetujuan level sebelumnya.');
        }
    }

    private function normalizeOptionalNote(?string $note): ?string
    {
        if ($note === null) {
            return null;
        }
        $note = trim($note);
        return $note === '' ? null : $note;
    }
}
