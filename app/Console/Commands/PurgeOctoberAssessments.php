<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class PurgeOctoberAssessments extends Command
{
    protected $signature = 'kpi:purge-october-assessments
        {--period=Oktober 2025 : Nama AssessmentPeriod (default: Oktober 2025)}
        {--dry-run : Tampilkan jumlah row yang akan dihapus tanpa menjalankan delete}';

    protected $description = 'Hapus SEMUA KPI data pada periode Oktober: derived (performance_assessments+details/approvals, remunerations) dan RAW inputs (absensi/import, 360, metric import, kontribusi, review invitations/reviews).';

    public function handle(): int
    {
        $periodName = trim((string) $this->option('period'));
        $dryRun = (bool) $this->option('dry-run');

        $period = DB::table('assessment_periods')->where('name', $periodName)->first();
        if (!$period) {
            $this->error("AssessmentPeriod tidak ditemukan: '{$periodName}'");
            return self::FAILURE;
        }

        $periodId = (int) $period->id;
        $startDate = null;
        $endDate = null;
        try {
            $startDate = $period->start_date ? Carbon::parse((string) $period->start_date)->toDateString() : null;
            $endDate = $period->end_date ? Carbon::parse((string) $period->end_date)->toDateString() : null;
        } catch (\Throwable) {
            $startDate = null;
            $endDate = null;
        }

        $paCount = (int) DB::table('performance_assessments')
            ->where('assessment_period_id', $periodId)
            ->count();

        $detailCount = 0;
        if (Schema::hasTable('performance_assessment_details')) {
            $detailCount = (int) DB::table('performance_assessment_details')
                ->join('performance_assessments', 'performance_assessments.id', '=', 'performance_assessment_details.performance_assessment_id')
                ->where('performance_assessments.assessment_period_id', $periodId)
                ->count();
        }

        $approvalCount = 0;
        if (Schema::hasTable('assessment_approvals')) {
            $approvalCount = (int) DB::table('assessment_approvals')
                ->join('performance_assessments', 'performance_assessments.id', '=', 'assessment_approvals.performance_assessment_id')
                ->where('performance_assessments.assessment_period_id', $periodId)
                ->count();
        }

        $mraCount = 0;
        $mraDetailCount = 0;
        if (Schema::hasTable('multi_rater_assessments')) {
            $mraCount = (int) DB::table('multi_rater_assessments')
                ->where('assessment_period_id', $periodId)
                ->count();
        }
        if (Schema::hasTable('multi_rater_assessment_details')) {
            $mraDetailCount = (int) DB::table('multi_rater_assessment_details')
                ->join('multi_rater_assessments', 'multi_rater_assessments.id', '=', 'multi_rater_assessment_details.multi_rater_assessment_id')
                ->where('multi_rater_assessments.assessment_period_id', $periodId)
                ->count();
        }

        $metricBatchCount = 0;
        $metricValueCount = 0;
        if (Schema::hasTable('metric_import_batches')) {
            $metricBatchCount = (int) DB::table('metric_import_batches')
                ->where('assessment_period_id', $periodId)
                ->count();
        }
        if (Schema::hasTable('imported_criteria_values')) {
            $metricValueCount = (int) DB::table('imported_criteria_values')
                ->where('assessment_period_id', $periodId)
                ->count();
        }

        $attBatchCount = 0;
        $attRowCount = 0;
        $attendanceCount = 0;
        $attBatchIds = [];
        if (Schema::hasTable('attendance_import_batches')) {
            $attBatchIds = DB::table('attendance_import_batches')
                ->where('assessment_period_id', $periodId)
                ->pluck('id')
                ->map(fn ($v) => (int) $v)
                ->all();
            $attBatchCount = count($attBatchIds);
        }
        if (!empty($attBatchIds) && Schema::hasTable('attendance_import_rows')) {
            $attRowCount = (int) DB::table('attendance_import_rows')
                ->whereIn('batch_id', $attBatchIds)
                ->count();
        }
        if (Schema::hasTable('attendances')) {
            $attendanceCount = (int) DB::table('attendances')
                ->when($startDate && $endDate, fn ($q) => $q->whereBetween('attendance_date', [$startDate, $endDate]))
                ->count();
        }

        $reviewInvCount = 0;
        $reviewInvStaffCount = 0;
        $reviewCount = 0;
        $reviewDetailCount = 0;
        $registrationRefs = [];
        $invIds = [];
        if (Schema::hasTable('review_invitations')) {
            $invIds = DB::table('review_invitations')
                ->where('assessment_period_id', $periodId)
                ->pluck('id')
                ->map(fn ($v) => (int) $v)
                ->all();
            $reviewInvCount = count($invIds);
            $registrationRefs = DB::table('review_invitations')
                ->where('assessment_period_id', $periodId)
                ->pluck('registration_ref')
                ->filter()
                ->unique()
                ->values()
                ->all();
        }
        if (!empty($invIds) && Schema::hasTable('review_invitation_staff')) {
            $reviewInvStaffCount = (int) DB::table('review_invitation_staff')
                ->whereIn('invitation_id', $invIds)
                ->count();
        }
        if (!empty($registrationRefs) && Schema::hasTable('reviews')) {
            $reviewCount = (int) DB::table('reviews')
                ->whereIn('registration_ref', $registrationRefs)
                ->count();
        }
        if (!empty($registrationRefs) && Schema::hasTable('review_details')) {
            $reviewDetailCount = (int) DB::table('review_details')
                ->join('reviews', 'reviews.id', '=', 'review_details.review_id')
                ->whereIn('reviews.registration_ref', $registrationRefs)
                ->count();
        }

        $remCount = 0;
        if (Schema::hasTable('remunerations')) {
            $remCount = (int) DB::table('remunerations')
                ->where('assessment_period_id', $periodId)
                ->count();
        }

        $this->info("Target period: {$periodName} (id={$periodId})");
        $this->line('date_range=' . ($startDate ?? '-') . ' .. ' . ($endDate ?? '-'));
        $this->line("performance_assessments to delete: {$paCount}");
        $this->line("performance_assessment_details (join) to delete: {$detailCount}");
        $this->line("assessment_approvals (join) to delete: {$approvalCount}");
        $this->line("multi_rater_assessments to delete: {$mraCount}");
        $this->line("multi_rater_assessment_details (join) to delete: {$mraDetailCount}");
        $this->line("metric_import_batches to delete: {$metricBatchCount}");
        $this->line("imported_criteria_values to delete: {$metricValueCount}");
        $this->line("attendance_import_batches to delete: {$attBatchCount}");
        $this->line("attendance_import_rows to delete: {$attRowCount}");
        $this->line("attendances (by date range) to delete: {$attendanceCount}");
        $this->line("review_invitations to delete: {$reviewInvCount}");
        $this->line("review_invitation_staff to delete: {$reviewInvStaffCount}");
        $this->line("reviews (by registration_ref) to delete: {$reviewCount}");
        $this->line("review_details (join) to delete: {$reviewDetailCount}");
        $this->line("remunerations to delete: {$remCount}");

        if ($dryRun) {
            $this->warn('Dry-run: tidak ada data yang dihapus.');
            return self::SUCCESS;
        }

        DB::transaction(function () use ($periodId) {
            if (Schema::hasTable('multi_rater_assessments')) {
                DB::table('multi_rater_assessments')
                    ->where('assessment_period_id', $periodId)
                    ->delete();
            }

            if (Schema::hasTable('imported_criteria_values')) {
                DB::table('imported_criteria_values')
                    ->where('assessment_period_id', $periodId)
                    ->delete();
            }
            if (Schema::hasTable('metric_import_batches')) {
                DB::table('metric_import_batches')
                    ->where('assessment_period_id', $periodId)
                    ->delete();
            }

            // Attendance import batches (rows cascade), but attendances must be removed separately.
            if (Schema::hasTable('attendance_import_batches')) {
                DB::table('attendance_import_batches')
                    ->where('assessment_period_id', $periodId)
                    ->delete();
            }

            if (Schema::hasTable('review_invitations')) {
                $invIds = DB::table('review_invitations')
                    ->where('assessment_period_id', $periodId)
                    ->pluck('id')
                    ->map(fn ($v) => (int) $v)
                    ->all();

                $registrationRefs = DB::table('review_invitations')
                    ->where('assessment_period_id', $periodId)
                    ->pluck('registration_ref')
                    ->filter()
                    ->unique()
                    ->values()
                    ->all();

                if (!empty($registrationRefs) && Schema::hasTable('reviews')) {
                    DB::table('reviews')->whereIn('registration_ref', $registrationRefs)->delete();
                }

                // invitation staff cascades from invitations, but delete invitations last.
                if (!empty($invIds) && Schema::hasTable('review_invitation_staff')) {
                    DB::table('review_invitation_staff')->whereIn('invitation_id', $invIds)->delete();
                }

                DB::table('review_invitations')
                    ->where('assessment_period_id', $periodId)
                    ->delete();
            }

            // Derived outputs
            DB::table('performance_assessments')
                ->where('assessment_period_id', $periodId)
                ->delete();

            if (Schema::hasTable('remunerations')) {
                DB::table('remunerations')
                    ->where('assessment_period_id', $periodId)
                    ->delete();
            }
        });

        // Attendances are filtered by date range (not by period_id).
        if (Schema::hasTable('attendances') && $startDate && $endDate) {
            DB::table('attendances')
                ->whereBetween('attendance_date', [$startDate, $endDate])
                ->delete();
        }

        $paAfter = (int) DB::table('performance_assessments')
            ->where('assessment_period_id', $periodId)
            ->count();

        $detailAfter = 0;
        if (Schema::hasTable('performance_assessment_details')) {
            $detailAfter = (int) DB::table('performance_assessment_details')
                ->join('performance_assessments', 'performance_assessments.id', '=', 'performance_assessment_details.performance_assessment_id')
                ->where('performance_assessments.assessment_period_id', $periodId)
                ->count();
        }

        $approvalAfter = 0;
        if (Schema::hasTable('assessment_approvals')) {
            $approvalAfter = (int) DB::table('assessment_approvals')
                ->join('performance_assessments', 'performance_assessments.id', '=', 'assessment_approvals.performance_assessment_id')
                ->where('performance_assessments.assessment_period_id', $periodId)
                ->count();
        }

        $mraAfter = 0;
        if (Schema::hasTable('multi_rater_assessments')) {
            $mraAfter = (int) DB::table('multi_rater_assessments')
                ->where('assessment_period_id', $periodId)
                ->count();
        }

        $metricBatchAfter = 0;
        $metricValueAfter = 0;
        if (Schema::hasTable('metric_import_batches')) {
            $metricBatchAfter = (int) DB::table('metric_import_batches')
                ->where('assessment_period_id', $periodId)
                ->count();
        }
        if (Schema::hasTable('imported_criteria_values')) {
            $metricValueAfter = (int) DB::table('imported_criteria_values')
                ->where('assessment_period_id', $periodId)
                ->count();
        }

        $attBatchAfter = 0;
        if (Schema::hasTable('attendance_import_batches')) {
            $attBatchAfter = (int) DB::table('attendance_import_batches')
                ->where('assessment_period_id', $periodId)
                ->count();
        }

        $attendanceAfter = 0;
        if (Schema::hasTable('attendances')) {
            $attendanceAfter = (int) DB::table('attendances')
                ->when($startDate && $endDate, fn ($q) => $q->whereBetween('attendance_date', [$startDate, $endDate]))
                ->count();
        }

        $reviewInvAfter = 0;
        if (Schema::hasTable('review_invitations')) {
            $reviewInvAfter = (int) DB::table('review_invitations')
                ->where('assessment_period_id', $periodId)
                ->count();
        }

        $remAfter = 0;
        if (Schema::hasTable('remunerations')) {
            $remAfter = (int) DB::table('remunerations')
                ->where('assessment_period_id', $periodId)
                ->count();
        }

        $this->info('Selesai.');
        $this->line("performance_assessments remaining: {$paAfter}");
        $this->line("performance_assessment_details (join) remaining: {$detailAfter}");
        $this->line("assessment_approvals (join) remaining: {$approvalAfter}");
        $this->line("multi_rater_assessments remaining: {$mraAfter}");
        $this->line("metric_import_batches remaining: {$metricBatchAfter}");
        $this->line("imported_criteria_values remaining: {$metricValueAfter}");
        $this->line("attendance_import_batches remaining: {$attBatchAfter}");
        $this->line("attendances (by date range) remaining: {$attendanceAfter}");
        $this->line("review_invitations remaining: {$reviewInvAfter}");
        $this->line("remunerations remaining: {$remAfter}");

        return self::SUCCESS;
    }
}
