<?php

namespace App\Services\AssessmentApprovals;

use App\Models\AssessmentApproval;
use App\Models\PerformanceAssessment;
use App\Models\User;
use App\Enums\AssessmentApprovalStatus;
use Illuminate\Support\Facades\DB;

class AssessmentApprovalFlow
{
    public static function ensureNextLevel(AssessmentApproval $current, ?int $fallbackApproverId = null): void
    {
        $nextLevel = (int) $current->level + 1;
        if ($nextLevel > 3) { return; }

        $assessmentId = $current->performance_assessment_id;
        $attempt = (int) ($current->attempt ?? 1);
        if (AssessmentApproval::where('performance_assessment_id', $assessmentId)
                ->where('attempt', $attempt)
                ->where('level', $nextLevel)
                ->whereNull('invalidated_at')
                ->exists()) {
            return;
        }

        $performanceAssessment = PerformanceAssessment::with('user')->find($assessmentId);
        if (!$performanceAssessment) { return; }

        // Tetapkan approver jika ditemukan; jika tidak tetap buat record pending agar muncul di daftar "Semua".
        $approverId = self::resolveApproverId($nextLevel, $performanceAssessment) ?? $fallbackApproverId ?? $current->approver_id;

        AssessmentApproval::create([
            'performance_assessment_id' => $assessmentId,
            'approver_id' => $approverId, // boleh null
            'level' => $nextLevel,
            'attempt' => $attempt,
            'status' => AssessmentApprovalStatus::PENDING->value,
            'note' => null,
            'acted_at' => null,
        ]);
    }

    public static function invalidateFutureLevels(AssessmentApproval $current, ?int $actorId = null, ?string $reason = null): void
    {
        $attempt = (int) ($current->attempt ?? 1);

        AssessmentApproval::where('performance_assessment_id', $current->performance_assessment_id)
            ->where('attempt', $attempt)
            ->where('level', '>', (int) $current->level)
            ->whereNull('invalidated_at')
            ->update([
                'invalidated_at' => now(),
                'invalidated_by_id' => $actorId,
                'invalidated_reason' => $reason,
            ]);
    }

    protected static function resolveApproverId(int $level, PerformanceAssessment $assessment): ?int
    {
        $role = match ($level) {
            1 => User::ROLE_ADMINISTRASI,
            2 => User::ROLE_KEPALA_UNIT,
            3 => User::ROLE_KEPALA_POLIKLINIK,
            default => null,
        };
        if (!$role) { return null; }

        // Gunakan scopeRole (whereHas pivot roles) karena kolom 'role' tidak ada di tabel users.
        $query = User::query()->role($role);
        $unitId = $assessment->user?->unit_id;

        if ($role === 'kepala_unit' && $unitId) {
            $query->where('unit_id', $unitId);
        }

        if ($role === 'kepala_poliklinik') {
            $parentId = null;
            if ($unitId && DB::table('units')->where('id', $unitId)->exists()) {
                $parentId = DB::table('units')->where('id', $unitId)->value('parent_id');
            }
            if ($parentId) {
                $query->where('unit_id', $parentId);
            }
        }

        return $query->orderBy('id')->value('id');
    }
}
