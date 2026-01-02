<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class VerifyPerformanceAssessmentSnapshots extends Command
{
    protected $signature = 'snapshots:verify-performance-assessments
                            {--period_id= : Limit to a single assessment_period_id}
                            {--only-frozen : Only show locked/approval/closed periods}';

    protected $description = 'Verify snapshot coverage: compares performance_assessments vs performance_assessment_snapshots per period.';

    public function handle(): int
    {
        if (!Schema::hasTable('assessment_periods') || !Schema::hasTable('performance_assessments')) {
            $this->error('Required tables are missing (assessment_periods/performance_assessments).');
            return self::FAILURE;
        }
        if (!Schema::hasTable('performance_assessment_snapshots')) {
            $this->error('Table performance_assessment_snapshots does not exist. Run php artisan migrate first.');
            return self::FAILURE;
        }

        $periodIdOpt = $this->option('period_id');
        $periodId = $periodIdOpt !== null && $periodIdOpt !== '' ? (int) $periodIdOpt : null;
        $onlyFrozen = (bool) $this->option('only-frozen');

        // Build per-period counts.
        $periodsQuery = DB::table('assessment_periods as p')
            ->select([
                'p.id',
                'p.name',
                'p.status',
                DB::raw('(select count(*) from performance_assessments pa where pa.assessment_period_id = p.id) as assessments_count'),
                DB::raw('(select count(*) from performance_assessment_snapshots ps where ps.assessment_period_id = p.id) as snapshots_count'),
            ])
            ->orderBy('p.start_date');

        if ($periodId !== null && $periodId > 0) {
            $periodsQuery->where('p.id', $periodId);
        }

        if ($onlyFrozen) {
            $periodsQuery->whereIn('p.status', ['locked', 'approval', 'closed']);
        }

        $rows = $periodsQuery->get();
        if ($rows->isEmpty()) {
            $this->info('No periods found for verification.');
            return self::SUCCESS;
        }

        $out = [];
        $hasMissing = false;
        foreach ($rows as $r) {
            $assessments = (int) ($r->assessments_count ?? 0);
            $snapshots = (int) ($r->snapshots_count ?? 0);
            $missing = max(0, $assessments - $snapshots);

            if ($missing > 0) {
                $hasMissing = true;
            }

            $out[] = [
                'period_id' => (int) $r->id,
                'name' => (string) ($r->name ?? '-'),
                'status' => (string) ($r->status ?? '-'),
                'assessments' => $assessments,
                'snapshots' => $snapshots,
                'missing' => $missing,
            ];
        }

        $this->table(
            ['period_id', 'name', 'status', 'assessments', 'snapshots', 'missing'],
            $out
        );

        if ($hasMissing) {
            $this->warn('Some periods have missing snapshots. You can run: php artisan snapshots:backfill-performance-assessments');
            return self::FAILURE;
        }

        $this->info('OK: snapshots are fully covered for the listed periods.');
        return self::SUCCESS;
    }
}
