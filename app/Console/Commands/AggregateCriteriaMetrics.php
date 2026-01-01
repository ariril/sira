<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\User;
use App\Services\AssessmentPeriods\PeriodPerformanceAssessmentService;

class AggregateCriteriaMetrics extends Command
{
    protected $signature = 'metrics:aggregate {period_id} {user_id?}';
    protected $description = 'Recalculate performance assessments for a period (engine: CriteriaEngine-backed WSM)';

    public function handle(PeriodPerformanceAssessmentService $svc)
    {
        $periodId = (int)$this->argument('period_id');
        $userId = $this->argument('user_id');

        if ($userId) {
            $user = User::query()->whereKey((int) $userId)->first(['id', 'unit_id', 'profession_id']);
            if (!$user) {
                $this->error("User {$userId} not found");
                return 1;
            }

            $svc->recalculateForGroup($periodId, $user->unit_id ? (int) $user->unit_id : null, $user->profession_id ? (int) $user->profession_id : null, [(int) $userId]);
            $this->info("Recalculated scores for user {$userId} period {$periodId}");
            return 0;
        }

        $svc->recalculateForPeriodId($periodId);
        $this->info("Recalculated scores for all users, period {$periodId}");
        return 0;
    }
}
