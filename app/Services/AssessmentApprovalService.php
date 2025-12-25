<?php

namespace App\Services;

use App\Enums\AssessmentApprovalStatus;
use App\Enums\AssessmentValidationStatus;
use App\Models\AssessmentApproval;
use App\Models\PerformanceAssessment;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class AssessmentApprovalService
{
    public function approve(AssessmentApproval $approval, User $actor, ?string $note = null): void
    {
        DB::transaction(function () use ($approval, $actor, $note) {
            $approval->refresh();

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

            AssessmentApprovalFlow::removeFutureLevels($approval);

            $this->syncAssessmentValidationStatus($approval->performanceAssessment()->firstOrFail());
        });
    }

    public function resubmitAfterReject(AssessmentApproval $anyApprovalForAssessment, User $actor): void
    {
        DB::transaction(function () use ($anyApprovalForAssessment, $actor) {
            if (!$actor->isAdministrasi()) {
                throw new \RuntimeException('Hanya Admin RS yang dapat melakukan ajukan ulang.');
            }

            $assessment = $anyApprovalForAssessment->performanceAssessment()->firstOrFail();

            $approvals = AssessmentApproval::query()
                ->where('performance_assessment_id', $assessment->id)
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
        $approvals = AssessmentApproval::query()
            ->where('performance_assessment_id', $assessment->id)
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

            $isChildUnit = DB::table('units')
                ->where('id', $targetUnitId)
                ->where('parent_id', $myUnitId)
                ->exists();

            if (!$isChildUnit) {
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

        $requiredLevels = range(1, $level - 1);
        $approvedLevels = AssessmentApproval::query()
            ->where('performance_assessment_id', $approval->performance_assessment_id)
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
