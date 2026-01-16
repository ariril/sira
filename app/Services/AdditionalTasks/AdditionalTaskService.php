<?php

namespace App\Services\AdditionalTasks;

use App\Models\AdditionalTask;
use App\Models\AdditionalTaskClaim;
use App\Models\User;
use App\Support\AssessmentPeriodGuard;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class AdditionalTaskService
{
    /**
     * Submit a task result (creates a claim directly; no claim/cancel lifecycle).
     *
     * Rules:
     * - task must be open
     * - creator cannot submit
     * - if task has assessment period: must be ACTIVE
     * - unique (task,user)
     * - quota counts submitted+approved
     * - if submitted after deadline: auto rejected
     */
    public function submitClaim(AdditionalTask $task, User $user, array $payload = []): AdditionalTaskClaim
    {
        $task->loadMissing('period');
        if ($task->period) {
            AssessmentPeriodGuard::requireActive($task->period, 'Submit Hasil Tugas Tambahan');
        }

        if ($task->status !== 'open') {
            throw new \RuntimeException('Tugas tidak tersedia.');
        }

        if ($task->created_by && (int) $task->created_by === (int) $user->id) {
            throw new \RuntimeException('Pembuat tugas tidak dapat submit tugasnya sendiri.');
        }

        $tz = config('app.timezone');
        $now = Carbon::now($tz);
        $deadlineTime = $task->due_time ?: '23:59:59';
        $deadlineDate = Carbon::parse($task->due_date)->toDateString();
        $deadline = Carbon::parse($deadlineDate . ' ' . $deadlineTime, $tz);
        $isLate = $now->greaterThan($deadline);

        $note = isset($payload['note']) ? (string) $payload['note'] : null;
        $file = $payload['file'] ?? null;

        return DB::transaction(function () use ($task, $user, $now, $isLate, $note, $file) {
            $existing = AdditionalTaskClaim::query()
                ->where('additional_task_id', $task->id)
                ->where('user_id', $user->id)
                ->first();
            if ($existing) {
                return $existing;
            }

            if (!empty($task->max_claims)) {
                $used = AdditionalTaskClaim::query()
                    ->where('additional_task_id', $task->id)
                    ->quotaCounted()
                    ->lockForUpdate()
                    ->count();

                if ($used >= (int) $task->max_claims) {
                    throw new \RuntimeException('Kuota klaim habis.');
                }
            }

            $storedPath = null;
            if ($file instanceof UploadedFile) {
                $storedPath = Storage::disk('public')->putFile('additional_task_claims', $file);
            }

            if ($isLate) {
                return AdditionalTaskClaim::create([
                    'additional_task_id' => $task->id,
                    'user_id' => $user->id,
                    'status' => 'rejected',
                    'submitted_at' => $now,
                    'result_file_path' => $storedPath,
                    'result_note' => $note,
                    'awarded_points' => 0,
                    'reviewed_by_id' => null,
                    'reviewed_at' => $now,
                    'review_comment' => 'Auto rejected: melewati batas waktu.',
                ]);
            }

            return AdditionalTaskClaim::create([
                'additional_task_id' => $task->id,
                'user_id' => $user->id,
                'status' => 'submitted',
                'submitted_at' => $now,
                'result_file_path' => $storedPath,
                'result_note' => $note,
                'awarded_points' => null,
                'reviewed_by_id' => null,
                'reviewed_at' => null,
                'review_comment' => null,
            ]);
        });
    }

    /**
     * Review a submitted claim.
     *
     * @param array $payload {decision: approved|rejected, comment?: string, awarded_points?: numeric}
     */
    public function reviewClaim(AdditionalTaskClaim $claim, User $reviewer, array $payload = []): AdditionalTaskClaim
    {
        $claim->loadMissing('task.period');
        if ($claim->task?->period) {
            AssessmentPeriodGuard::requireActive($claim->task->period, 'Review Klaim Tugas Tambahan');
        }

        if ($claim->status !== 'submitted') {
            throw new \RuntimeException('Klaim tidak dapat direview pada status saat ini.');
        }

        $decision = (string) ($payload['decision'] ?? '');
        $comment = array_key_exists('comment', $payload) ? (string) $payload['comment'] : null;

        $now = now();

        if ($decision === 'approved') {
            $taskPoints = (float) ($claim->task?->points ?? 0);
            $awarded = array_key_exists('awarded_points', $payload)
                ? (float) $payload['awarded_points']
                : $taskPoints;

            $claim->update([
                'status' => 'approved',
                'awarded_points' => $awarded,
                'reviewed_by_id' => $reviewer->id,
                'reviewed_at' => $now,
                'review_comment' => $comment,
            ]);

            return $claim;
        }

        if ($decision === 'rejected') {
            $claim->update([
                'status' => 'rejected',
                'awarded_points' => 0,
                'reviewed_by_id' => $reviewer->id,
                'reviewed_at' => $now,
                'review_comment' => $comment,
            ]);

            return $claim;
        }

        throw new \InvalidArgumentException('Keputusan review tidak valid.');
    }
}
