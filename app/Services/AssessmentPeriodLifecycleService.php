<?php

namespace App\Services;

use App\Enums\AssessmentValidationStatus;
use App\Models\AssessmentPeriod;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class AssessmentPeriodLifecycleService
{
    /**
     * Sync lifecycle state machine.
     * - Auto-sync draft/active/locked by date
     * - Auto-close approval -> closed when all medical staff assessments are fully approved
     */
    public function sync(): void
    {
        if (!Schema::hasTable('assessment_periods')) {
            return;
        }

        AssessmentPeriod::syncByNow();

        $this->autoCloseApprovedPeriods();
    }

    private function autoCloseApprovedPeriods(): void
    {
        if (!Schema::hasTable('performance_assessments')) {
            return;
        }

        $approvalPeriods = AssessmentPeriod::query()
            ->where('status', AssessmentPeriod::STATUS_APPROVAL)
            ->get(['id']);

        if ($approvalPeriods->isEmpty()) {
            return;
        }

        $validatedValue = AssessmentValidationStatus::VALIDATED->value;

        foreach ($approvalPeriods as $period) {
            $periodId = (int) $period->id;

            // Only close if there is at least one assessment in the period.
            $hasAny = DB::table('performance_assessments')
                ->where('assessment_period_id', $periodId)
                ->exists();
            if (!$hasAny) {
                continue;
            }

            // If any medical staff is pending or rejected, do NOT close.
            $hasNotValidated = DB::table('performance_assessments')
                ->where('assessment_period_id', $periodId)
                ->where('validation_status', '!=', $validatedValue)
                ->exists();
            if ($hasNotValidated) {
                continue;
            }

            // Close period.
            AssessmentPeriod::query()
                ->where('id', $periodId)
                ->update([
                    'status' => AssessmentPeriod::STATUS_CLOSED,
                    'closed_at' => now(),
                    'updated_at' => now(),
                ]);
        }
    }
}
