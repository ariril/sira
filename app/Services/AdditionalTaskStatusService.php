<?php

namespace App\Services;

use App\Models\AdditionalTask;
use Illuminate\Support\Carbon;

class AdditionalTaskStatusService
{
    public const ACTIVE_STATUSES = ['active','submitted','validated','approved'];

    public static function sync(AdditionalTask $task): void
    {
        if (!in_array($task->status, ['open', 'closed'])) {
            return;
        }

        $shouldCloseByTime = false;
        if ($task->due_date) {
            $dueEnd = Carbon::parse($task->due_date, 'Asia/Jakarta')->endOfDay('Asia/Jakarta');
            $shouldCloseByTime = Carbon::now('Asia/Jakarta')->greaterThan($dueEnd);
        }

        $shouldCloseByQuota = false;
        if (!empty($task->max_claims)) {
            $activeCount = $task->claims()->whereIn('status', self::ACTIVE_STATUSES)->count();
            $shouldCloseByQuota = $activeCount >= (int) $task->max_claims;
        }

        $newStatus = ($shouldCloseByTime || $shouldCloseByQuota) ? 'closed' : 'open';

        if ($task->status !== $newStatus) {
            $task->status = $newStatus;
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
