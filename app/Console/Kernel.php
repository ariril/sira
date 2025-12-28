<?php

namespace App\Console;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;

class Kernel extends ConsoleKernel
{
    protected $commands = [
        \App\Console\Commands\AggregateCriteriaMetrics::class,
        \App\Console\Commands\ImportCriteriaMetricsCsv::class,
        \App\Console\Commands\GenerateMultiRaterInvites::class,
        \App\Console\Commands\ImportReviewInvitations::class,
        \App\Console\Commands\SyncAssessmentPeriodsLifecycle::class,
    ];

    protected function schedule(Schedule $schedule): void
    {
        // Keep AssessmentPeriod lifecycle in sync.
        $schedule->command('assessment-periods:sync-lifecycle')->everyMinute()->withoutOverlapping();
    }
}
