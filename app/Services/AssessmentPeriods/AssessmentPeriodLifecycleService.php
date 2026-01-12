<?php

namespace App\Services\AssessmentPeriods;

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

        $this->finalizeMultiRaterAssessments();
        $this->autoCloseApprovedPeriods();
    }

    /**
     * Multi-rater (360) rule:
     * - While the 360 window is running, each save keeps the assessment status as in_progress.
        * - After the window end date has passed, assessments that have been started (in_progress) become submitted.
        *   Assessments still in invited state are NOT considered filled and will be cancelled.
     */
    private function finalizeMultiRaterAssessments(): void
    {
        if (!Schema::hasTable('assessment_360_windows')
            || !Schema::hasTable('multi_rater_assessments')
            || !Schema::hasTable('multi_rater_assessment_details')) {
            return;
        }

        $today = now()->toDateString();

        $expiredPeriodIds = DB::table('assessment_360_windows')
            ->whereNotNull('end_date')
            ->where('end_date', '<', $today)
            ->pluck('assessment_period_id')
            ->filter()
            ->map(fn($id) => (int) $id)
            ->unique()
            ->values();

        if ($expiredPeriodIds->isEmpty()) {
            return;
        }

        // Close any still-active windows that have passed their end date.
        DB::table('assessment_360_windows')
            ->whereIn('assessment_period_id', $expiredPeriodIds)
            ->where('is_active', true)
            ->update([
                'is_active' => false,
                'updated_at' => now(),
            ]);

        // Mark as submitted only if the assessment was started (in_progress) and has at least one saved detail row.
        DB::table('multi_rater_assessments as mra')
            ->whereIn('mra.assessment_period_id', $expiredPeriodIds)
            ->where('mra.status', '=', 'in_progress')
            ->whereExists(function ($q) {
                $q->select(DB::raw(1))
                    ->from('multi_rater_assessment_details as d')
                    ->whereColumn('d.multi_rater_assessment_id', 'mra.id');
            })
            ->update([
                'status' => 'submitted',
                'submitted_at' => now(),
                'updated_at' => now(),
            ]);

        // Any invitations not started by the rater by the window end are cancelled.
        DB::table('multi_rater_assessments as mra')
            ->whereIn('mra.assessment_period_id', $expiredPeriodIds)
            ->where('mra.status', '=', 'invited')
            ->update([
                'status' => 'cancelled',
                'submitted_at' => null,
                'updated_at' => now(),
            ]);
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
