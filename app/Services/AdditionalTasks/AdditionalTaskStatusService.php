<?php

namespace App\Services\AdditionalTasks;

use App\Models\AdditionalTask;
use Illuminate\Support\Carbon;

class AdditionalTaskStatusService
{
    // Claims that consume quota (active + submitted + approved)
    public const ACTIVE_STATUSES = ['active', 'submitted', 'approved'];

    // Klaim yang masih menunggu tindakan kepala unit
    public const REVIEW_WAITING_STATUSES = ['submitted'];

    public static function sync(AdditionalTask $task): void
    {
        if (!in_array($task->status, ['open', 'closed'])) {
            return;
        }

        $tz = config('app.timezone');

        $shouldCloseByTime = false;
        if ($task->due_date) {
            $deadlineTime = $task->due_time ?: '23:59:59';
            $deadlineDate = Carbon::parse($task->due_date)->toDateString();
            $deadline = Carbon::parse($deadlineDate . ' ' . $deadlineTime, $tz);
            $shouldCloseByTime = Carbon::now($tz)->greaterThan($deadline);
        }

        $shouldCloseByQuota = false;
        if (!empty($task->max_claims)) {
            $activeCount = $task->claims()->whereIn('status', self::ACTIVE_STATUSES)->count();
            $shouldCloseByQuota = $activeCount >= (int) $task->max_claims;
        }

        // Auto-sync only closes tasks when needed.
        // Do NOT auto-reopen a task that is already 'closed' because that can override
        // a manual "Tutup" action by the unit head.
        if ($task->status === 'open' && ($shouldCloseByTime || $shouldCloseByQuota)) {
            $task->status = 'closed';
            $task->save();
        }
    }

    public static function syncForUnit(?int $unitId): void
    {
        if (!$unitId) {
            return;
        }

        AdditionalTask::query()
            ->where('unit_id', $unitId)
            ->whereIn('status', ['open', 'closed'])
            ->chunkById(100, function ($tasks) {
                /** @var AdditionalTask $task */
                foreach ($tasks as $task) {
                    self::sync($task);
                }
            });
    }
}
