<?php

namespace App\Services;

use App\Models\AssessmentApproval;
use App\Models\PerformanceAssessment;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class AssessmentApprovalFlow
{
    public static function ensureNextLevel(AssessmentApproval $current, ?int $fallbackApproverId = null): void
    {
        $nextLevel = (int) $current->level + 1;
        if ($nextLevel > 3) { return; }

        $assessmentId = $current->performance_assessment_id;
        if (AssessmentApproval::where('performance_assessment_id', $assessmentId)
                ->where('level', $nextLevel)
                ->exists()) {
            return;
        }

        $performanceAssessment = PerformanceAssessment::with('user')->find($assessmentId);
        if (!$performanceAssessment) { return; }

        $approverId = self::resolveApproverId($nextLevel, $performanceAssessment) ?? $fallbackApproverId ?? $current->approver_id;
        if (!$approverId) { return; }

        AssessmentApproval::create([
            'performance_assessment_id' => $assessmentId,
            'approver_id' => $approverId,
            'level' => $nextLevel,
            'status' => 'pending',
            'note' => null,
            'acted_at' => null,
        ]);
    }

    public static function removeFutureLevels(AssessmentApproval $current): void
    {
        AssessmentApproval::where('performance_assessment_id', $current->performance_assessment_id)
            ->where('level', '>', (int) $current->level)
            ->delete();
    }

    protected static function resolveApproverId(int $level, PerformanceAssessment $assessment): ?int
    {
        $role = match ($level) {
            1 => 'admin_rs',
            2 => 'kepala_unit',
            3 => 'kepala_poliklinik',
            default => null,
        };
        if (!$role) { return null; }

        $query = User::query()->where('role', $role);
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
