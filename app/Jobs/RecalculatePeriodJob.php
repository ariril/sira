<?php

namespace App\Jobs;

use App\Services\AssessmentPeriods\PeriodPerformanceAssessmentService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class RecalculatePeriodJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(public readonly int $periodId)
    {
    }

    public function handle(PeriodPerformanceAssessmentService $svc): void
    {
        $periodId = (int) $this->periodId;
        if ($periodId <= 0) {
            return;
        }

        $svc->recalculateForPeriodId($periodId);
    }
}
