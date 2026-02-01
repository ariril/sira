<?php

namespace App\Services\AssessmentPeriods;

use App\Enums\AssessmentValidationStatus;
use App\Models\AssessmentPeriod;
use App\Services\Remuneration\RemunerationCalculationService;
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

        // After lifecycle transitions, freeze configuration rows for non-active periods.
        $this->archiveFrozenPeriodWeights();
    }

    private function archiveFrozenPeriodWeights(): void
    {
        if (!Schema::hasTable('assessment_periods')) {
            return;
        }

        $frozenStatuses = [
            AssessmentPeriod::STATUS_LOCKED,
            AssessmentPeriod::STATUS_APPROVAL,
            AssessmentPeriod::STATUS_REVISION,
            AssessmentPeriod::STATUS_CLOSED,
            AssessmentPeriod::STATUS_ARCHIVED,
        ];

        $periodIds = DB::table('assessment_periods')
            ->whereIn('status', $frozenStatuses)
            ->pluck('id')
            ->filter()
            ->map(fn($id) => (int) $id)
            ->unique()
            ->values()
            ->all();

        if (empty($periodIds)) {
            return;
        }

        // Unit criteria weights: archive all non-archived rows for frozen periods.
        // IMPORTANT:
        // Don't compute was_active_before from `status` in the same UPDATE that also sets status='archived'.
        // Some SQL engines may evaluate assignments in an order that makes the CASE see the *new* status,
        // resulting in was_active_before always becoming 0.
        // We do this in two steps: mark active rows first, then archive.
        if (Schema::hasTable('unit_criteria_weights')) {
            $now = now();

            $hasWasActiveBefore = Schema::hasColumn('unit_criteria_weights', 'was_active_before');
            if ($hasWasActiveBefore) {
                // Step 1: remember which rows were active before freezing.
                DB::table('unit_criteria_weights')
                    ->whereIn('assessment_period_id', $periodIds)
                    ->where('status', 'active')
                    ->update([
                        'was_active_before' => 1,
                        'updated_at' => $now,
                    ]);
            }

            // Step 2: archive everything (freeze config).
            DB::table('unit_criteria_weights')
                ->whereIn('assessment_period_id', $periodIds)
                ->where('status', '!=', 'archived')
                ->update([
                    'status' => 'archived',
                    'updated_at' => $now,
                ]);

            // Backfill legacy/buggy archived rows that lost the was_active_before signal.
            if ($hasWasActiveBefore) {
                $hasDecidedColumns = Schema::hasColumn('unit_criteria_weights', 'decided_by') || Schema::hasColumn('unit_criteria_weights', 'decided_at');

                if ($hasDecidedColumns) {
                    DB::table('unit_criteria_weights')
                        ->whereIn('assessment_period_id', $periodIds)
                        ->where('status', 'archived')
                        ->where('was_active_before', 0)
                        ->where(function ($q) {
                            if (Schema::hasColumn('unit_criteria_weights', 'decided_by')) {
                                $q->whereNotNull('decided_by');
                            }
                            if (Schema::hasColumn('unit_criteria_weights', 'decided_at')) {
                                $q->orWhereNotNull('decided_at');
                            }
                        })
                        ->update([
                            'was_active_before' => 1,
                            'updated_at' => $now,
                        ]);
                }

                // Final fallback: if a frozen period has archived weights but none are marked,
                // mark them so WSM can be computed (weights are required to lock/approve).
                foreach ($periodIds as $pid) {
                    $pid = (int) $pid;
                    $unitIds = DB::table('unit_criteria_weights')
                        ->where('assessment_period_id', $pid)
                        ->select('unit_id')
                        ->distinct()
                        ->pluck('unit_id')
                        ->map(fn($v) => (int) $v)
                        ->filter(fn($v) => $v > 0)
                        ->values()
                        ->all();

                    foreach ($unitIds as $unitId) {
                        $unitId = (int) $unitId;
                        $hasAnyMarked = DB::table('unit_criteria_weights')
                            ->where('assessment_period_id', $pid)
                            ->where('unit_id', $unitId)
                            ->where('was_active_before', 1)
                            ->exists();

                        if ($hasAnyMarked) {
                            continue;
                        }

                        $hasAnyActive = DB::table('unit_criteria_weights')
                            ->where('assessment_period_id', $pid)
                            ->where('unit_id', $unitId)
                            ->where('status', 'active')
                            ->exists();

                        if ($hasAnyActive) {
                            continue;
                        }

                        DB::table('unit_criteria_weights')
                            ->where('assessment_period_id', $pid)
                            ->where('unit_id', $unitId)
                            ->where('status', 'archived')
                            ->where('was_active_before', 0)
                            ->update([
                                'was_active_before' => 1,
                                'updated_at' => $now,
                            ]);
                    }
                }
            }
        }

        // Unit rater weights: archive all non-archived rows for frozen periods.
        // Mark was_active_before=1 ONLY if the row used to be active.
        if (Schema::hasTable('unit_rater_weights')) {
            $now = now();

            // Same two-step approach as unit_criteria_weights.
            if (Schema::hasColumn('unit_rater_weights', 'was_active_before')) {
                DB::table('unit_rater_weights')
                    ->whereIn('assessment_period_id', $periodIds)
                    ->where('status', 'active')
                    ->update([
                        'was_active_before' => 1,
                        'updated_at' => $now,
                    ]);
            }

            DB::table('unit_rater_weights')
                ->whereIn('assessment_period_id', $periodIds)
                ->where('status', '!=', 'archived')
                ->update([
                    'status' => 'archived',
                    'updated_at' => $now,
                ]);

            // Backfill legacy archived rows that were approved/active before this flag existed.
            // Heuristic: a row with decided_by is an approved row (i.e., had been active).
            if (Schema::hasColumn('unit_rater_weights', 'was_active_before')) {
                DB::table('unit_rater_weights')
                    ->whereIn('assessment_period_id', $periodIds)
                    ->where('status', 'archived')
                    ->where('was_active_before', 0)
                    ->where(function ($q) {
                        $q->whereNotNull('decided_by')
                          ->orWhereNotNull('decided_at');
                    })
                    ->update([
                        'was_active_before' => 1,
                        'updated_at' => $now,
                    ]);
            }
        }
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

            // Ensure weights are ready before closing.
            $periodModel = AssessmentPeriod::query()->find($periodId);
            if ($periodModel) {
                $periodModel->ensureWeightsReadyForLock();
            }

            // Close period.
            AssessmentPeriod::query()
                ->where('id', $periodId)
                ->update([
                    'status' => AssessmentPeriod::STATUS_CLOSED,
                    'closed_at' => now(),
                    'updated_at' => now(),
                ]);

            // Auto-calculate remuneration after period is closed (best-effort).
            try {
                $closedPeriod = AssessmentPeriod::query()->find($periodId);
                if ($closedPeriod) {
                    app(RemunerationCalculationService::class)->runForPeriod($closedPeriod, null, true);
                }
            } catch (\Throwable $e) {
                // Do not block lifecycle sync if remuneration calc fails.
            }
        }
    }
}
