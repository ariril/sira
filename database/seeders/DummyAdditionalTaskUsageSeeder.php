<?php

namespace Database\Seeders;

use App\Models\AdditionalTask;
use App\Models\AdditionalTaskClaim;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class DummyAdditionalTaskUsageSeeder extends Seeder
{
    public function run(): void
    {
        if (!Schema::hasTable('additional_tasks') || !Schema::hasTable('additional_task_claims')) {
            $this->command?->warn('Skip: required tables do not exist.');
            return;
        }

        $tz = config('app.timezone');
        $now = Carbon::now($tz);

        $tasks = AdditionalTask::query()
            ->whereNotNull('due_date')
            ->orderByDesc('id')
            ->limit(50)
            ->get();

        foreach ($tasks as $task) {
            $deadlineTime = $task->due_time ?: '23:59:59';
            $dueDate = $task->due_date instanceof Carbon
                ? $task->due_date->toDateString()
                : Carbon::parse((string) $task->due_date, $tz)->toDateString();

            $deadline = Carbon::parse($dueDate . ' ' . $deadlineTime, $tz);

            // Only seed for tasks that are already past due (matches the screen case: Jan 2026 looking at Nov/Dec 2025)
            if ($deadline->isFuture()) {
                continue;
            }

            $alreadyHasClaim = AdditionalTaskClaim::query()
                ->where('additional_task_id', $task->id)
                ->exists();

            if ($alreadyHasClaim) {
                continue;
            }

            $user = User::query()
                ->where('unit_id', $task->unit_id)
                ->when($task->created_by, fn ($q) => $q->where('id', '!=', $task->created_by))
                ->orderBy('id')
                ->first();

            if (!$user) {
                $user = User::factory()->create([
                    'unit_id' => $task->unit_id,
                    'name' => 'Dummy Pegawai Medis',
                    'email' => 'dummy_pegawai_' . $task->unit_id . '_' . $task->id . '@example.test',
                ]);
            }

            DB::transaction(function () use ($task, $user, $now) {
                AdditionalTaskClaim::query()->create([
                    'additional_task_id' => $task->id,
                    'user_id' => $user->id,
                    'status' => 'approved',
                    'submitted_at' => $now->copy()->subDays(2),
                    'result_file_path' => 'dummy/additional_task_results/' . $task->id . '/hasil.pdf',
                    'result_note' => 'Dummy data: menandakan tugas sudah dikerjakan.',
                    'awarded_points' => $task->points,
                    'reviewed_by_id' => $task->created_by,
                    'reviewed_at' => $now->copy()->subDay(),
                    'review_comment' => 'Dummy approve.',
                ]);
            });
        }

        $this->command?->info('DummyAdditionalTaskUsageSeeder finished.');
    }
}
