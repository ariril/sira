<?php

namespace Database\Seeders;

use App\Models\AdditionalContribution;
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
        if (!Schema::hasTable('additional_tasks') || !Schema::hasTable('additional_task_claims') || !Schema::hasTable('additional_contributions')) {
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
            $deadline = Carbon::parse($task->due_date->toDateString() . ' ' . $deadlineTime, $tz);

            // Only seed for tasks that are already past due (matches the screen case: Jan 2026 looking at Nov/Dec 2025)
            if ($deadline->isFuture()) {
                continue;
            }

            $alreadyFinished = AdditionalTaskClaim::query()
                ->where('additional_task_id', $task->id)
                ->whereIn('status', ['approved', 'completed'])
                ->exists();

            if ($alreadyFinished) {
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
                $claim = AdditionalTaskClaim::query()->create([
                    'additional_task_id' => $task->id,
                    'user_id' => $user->id,
                    'status' => 'approved',
                    'claimed_at' => $now->copy()->subDays(2),
                    'completed_at' => $now->copy()->subDay(),
                    'result_file_path' => 'dummy/additional_task_results/' . $task->id . '/hasil.pdf',
                    'result_note' => 'Dummy data: menandakan tugas sudah dikerjakan.',
                    'awarded_points' => $task->points,
                    'awarded_bonus_amount' => $task->bonus_amount,
                    'reviewed_by_id' => $task->created_by,
                    'reviewed_at' => $now->copy()->subDay(),
                    'review_comment' => 'Dummy approve.',
                ]);

                AdditionalContribution::query()->create([
                    'user_id' => $user->id,
                    'claim_id' => $claim->id,
                    'title' => 'Dummy kontribusi untuk task #' . $task->id,
                    'description' => 'Dummy data: bukti kontribusi tambahan terkait klaim tugas.',
                    'submission_date' => $now->toDateString(),
                    'claimed_at' => $now->copy()->subDays(2),
                    'submitted_at' => $now->copy()->subDay(),
                    'evidence_file' => 'dummy/additional_contributions/' . $task->id . '/evidence.pdf',
                    'attachment_original_name' => 'evidence.pdf',
                    'attachment_mime' => 'application/pdf',
                    'attachment_size' => 12345,
                    'validation_status' => 'Disetujui',
                    'supervisor_comment' => 'Dummy approved.',
                    'reviewer_id' => $task->created_by,
                    'score' => $task->points,
                    'bonus_awarded' => $task->bonus_amount,
                    'assessment_period_id' => $task->assessment_period_id,
                ]);
            });
        }

        $this->command?->info('DummyAdditionalTaskUsageSeeder finished.');
    }
}
