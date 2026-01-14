<?php

namespace App\Console\Commands;

use App\Models\AssessmentPeriod;
use App\Services\AssessmentPeriods\PeriodPerformanceAssessmentService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class RebuildPerformanceAssessmentSnapshots extends Command
{
    protected $signature = 'snapshots:rebuild-performance-assessments {period_id : assessment_period_id to rebuild} {--dry-run : Show what would be changed without writing}';

    protected $description = 'Rebuild performance_assessment_snapshots for a period by deleting existing snapshot rows and recalculating performance assessments (including new snapshots).';

    public function handle(PeriodPerformanceAssessmentService $svc): int
    {
        $periodId = (int) $this->argument('period_id');
        $dryRun = (bool) $this->option('dry-run');

        if ($periodId <= 0) {
            $this->error('period_id must be a positive integer.');
            return self::FAILURE;
        }

        if (!Schema::hasTable('assessment_periods')) {
            $this->error('Table assessment_periods does not exist.');
            return self::FAILURE;
        }

        if (!Schema::hasTable('performance_assessment_snapshots')) {
            $this->error('Table performance_assessment_snapshots does not exist. Run php artisan migrate first.');
            return self::FAILURE;
        }

        $period = AssessmentPeriod::query()->find($periodId);
        if (!$period) {
            $this->error("AssessmentPeriod {$periodId} not found.");
            return self::FAILURE;
        }

        $this->line(sprintf('Period #%d (%s) status=%s frozen=%s', (int) $period->id, (string) ($period->name ?? '-'), (string) ($period->status ?? '-'), $period->isFrozen() ? 'yes' : 'no'));

        $existingSnapshots = (int) DB::table('performance_assessment_snapshots')
            ->where('assessment_period_id', $periodId)
            ->count();

        $existingMembership = null;
        if (Schema::hasTable('assessment_period_user_membership_snapshots')) {
            $existingMembership = (int) DB::table('assessment_period_user_membership_snapshots')
                ->where('assessment_period_id', $periodId)
                ->count();
        }

        $this->info('Existing snapshot rows: ' . $existingSnapshots);
        if ($existingMembership !== null) {
            $this->info('Existing membership snapshot rows: ' . $existingMembership);
        }

        if ($dryRun) {
            $this->warn('DRY RUN: no changes written.');
            return self::SUCCESS;
        }

        // Delete old snapshots so PeriodPerformanceAssessmentService can regenerate them.
        DB::table('performance_assessment_snapshots')
            ->where('assessment_period_id', $periodId)
            ->delete();

        if (Schema::hasTable('assessment_period_user_membership_snapshots')) {
            DB::table('assessment_period_user_membership_snapshots')
                ->where('assessment_period_id', $periodId)
                ->delete();
        }

        // Recalculate assessments and (for frozen periods) recreate snapshots.
        $svc->recalculateForPeriodId($periodId);

        $newSnapshots = (int) DB::table('performance_assessment_snapshots')
            ->where('assessment_period_id', $periodId)
            ->count();

        $this->info('Rebuild complete. New snapshot rows: ' . $newSnapshots);

        return self::SUCCESS;
    }
}
