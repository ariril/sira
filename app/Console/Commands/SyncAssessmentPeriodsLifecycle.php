<?php

namespace App\Console\Commands;

use App\Services\AssessmentPeriods\AssessmentPeriodLifecycleService;
use Illuminate\Console\Command;

class SyncAssessmentPeriodsLifecycle extends Command
{
    protected $signature = 'assessment-periods:sync-lifecycle';
    protected $description = 'Sync AssessmentPeriod lifecycle (auto lock by date and auto close after final approvals)';

    public function handle(AssessmentPeriodLifecycleService $svc): int
    {
        $svc->sync();
        $this->info('Assessment period lifecycle synced.');
        return 0;
    }
}
