<?php

namespace App\Services\AdditionalTasks;

use App\Models\AdditionalTaskClaim;
use Illuminate\Support\Facades\DB;

class AdditionalTaskClaimNoticeService
{
    public function latestRejectedClaimForUser(int $userId): ?object
    {
        return DB::table('additional_task_claims as c')
            ->join('additional_tasks as t', 't.id', '=', 'c.additional_task_id')
            ->leftJoin('assessment_periods as ap', 'ap.id', '=', 't.assessment_period_id')
            ->selectRaw('c.id, c.updated_at, c.penalty_note, t.title, ap.name as period_name')
            ->where('c.user_id', $userId)
            ->where('c.status', 'rejected')
            ->orderByDesc('c.updated_at')
            ->first();
    }

    public function latestRejectedClaimModelForUser(int $userId): ?AdditionalTaskClaim
    {
        return AdditionalTaskClaim::query()
            ->with(['task.period'])
            ->where('user_id', $userId)
            ->where('status', 'rejected')
            ->latest('updated_at')
            ->first();
    }
}
