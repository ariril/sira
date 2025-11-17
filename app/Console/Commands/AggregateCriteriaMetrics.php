<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\CriteriaAggregator;
use App\Models\User;

class AggregateCriteriaMetrics extends Command
{
    protected $signature = 'metrics:aggregate {period_id} {user_id?}';
    protected $description = 'Aggregate criteria metrics (including 360) into assessment details on 0-100 scale';

    public function handle(CriteriaAggregator $svc)
    {
        $periodId = (int)$this->argument('period_id');
        $userId = $this->argument('user_id');

        if ($userId) {
            $svc->aggregateUserPeriod((int)$userId, $periodId);
            $this->info("Aggregated metrics for user {$userId} period {$periodId}");
            return 0;
        }

        $users = User::query()->pluck('id');
        foreach ($users as $uid) {
            $svc->aggregateUserPeriod((int)$uid, $periodId);
        }
        $this->info("Aggregated metrics for ".$users->count()." users, period {$periodId}");
        return 0;
    }
}
