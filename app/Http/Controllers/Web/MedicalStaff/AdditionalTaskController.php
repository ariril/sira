<?php

namespace App\Http\Controllers\Web\MedicalStaff;

use App\Http\Controllers\Controller;
use App\Models\AdditionalTask;
use App\Models\AdditionalTaskClaim;
use App\Services\AdditionalTasks\AdditionalTaskStatusService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Carbon;
use Illuminate\View\View;
use App\Support\AssessmentPeriodGuard;

class AdditionalTaskController extends Controller
{
    /**
     * Dashboard tugas tambahan pegawai medis.
     *
     * Menyajikan 4 bagian UI:
     * - Tugas tersedia untuk diklaim
     * - Klaim berjalan
     * - Riwayat klaim
     * - Tugas yang tidak diklaim (terlewat)
     */
    public function index(): View
    {
        $me = Auth::user();
        abort_unless($me && $me->role === 'pegawai_medis', 403);

        $activePeriod = AssessmentPeriodGuard::resolveActive();

        $availableTasks = collect();
        $currentClaims = collect();
        $historyClaims = collect();
        $missedTasks = collect();

        if (
            Schema::hasTable('additional_tasks') &&
            Schema::hasTable('additional_task_claims') &&
            Schema::hasTable('assessment_periods')
        ) {
            AdditionalTaskStatusService::syncForUnit($me->unit_id);

            $tz = config('app.timezone');
            $now = Carbon::now($tz);

            // 1) Tugas tersedia untuk diklaim
            // NOTE: UI blade expects: claims_used, available, my_claim_status, period_name, supporting_file_url.
            $availableTaskRows = AdditionalTask::query()
                ->with(['period:id,name'])
                ->withCount([
                    'claims as claims_used' => function ($q) {
                        $q->whereIn('status', AdditionalTaskStatusService::ACTIVE_STATUSES);
                    },
                ])
                ->forUnit($me->unit_id)
                ->forActivePeriod()
                ->open()
                ->excludeCreator($me->id)
                ->orderByDesc('id')
                ->get()
                ->filter(function (AdditionalTask $task) use ($now, $tz) {
                    if (!$task->due_date) {
                        return true;
                    }

                    $deadlineTime = $task->due_time ?: '23:59:59';
                    $deadlineDate = Carbon::parse($task->due_date)->toDateString();
                    $deadline = Carbon::parse($deadlineDate . ' ' . $deadlineTime, $tz);
                    return !$deadline->lessThan($now);
                })
                ->values();

            $myClaimByTaskId = collect();
            if ($availableTaskRows->isNotEmpty()) {
                $taskIds = $availableTaskRows->pluck('id')->all();
                $myClaims = AdditionalTaskClaim::query()
                    ->select(['id', 'additional_task_id', 'status'])
                    ->where('user_id', $me->id)
                    ->whereIn('additional_task_id', $taskIds)
                    ->whereIn('status', AdditionalTaskStatusService::ACTIVE_STATUSES)
                    ->orderByDesc('id')
                    ->get();

                $myClaimByTaskId = $myClaims
                    ->groupBy('additional_task_id')
                    ->map(fn ($items) => $items->first());
            }

            $availableTasks = $availableTaskRows
                ->map(function (AdditionalTask $task) use ($myClaimByTaskId) {
                    $claimsUsed = (int) ($task->claims_used ?? 0);
                    $maxClaims = $task->max_claims;
                    $available = empty($maxClaims) ? true : ($claimsUsed < (int) $maxClaims);

                    $myClaim = $myClaimByTaskId->get($task->id);

                    return (object) [
                        'id' => $task->id,
                        'title' => $task->title,
                        'description' => $task->description,
                        'period_name' => $task->period?->name,
                        'due_date' => $task->due_date,
                        'due_time' => $task->due_time,
                        'points' => $task->points,
                        'bonus_amount' => $task->bonus_amount,
                        'cancel_window_hours' => (int) ($task->cancel_window_hours ?? 24),
                        'default_penalty_type' => (string) ($task->default_penalty_type ?? 'none'),
                        'default_penalty_value' => (float) ($task->default_penalty_value ?? 0),
                        'penalty_base' => (string) ($task->penalty_base ?? 'task_bonus'),
                        'max_claims' => $task->max_claims,

                        'claims_used' => $claimsUsed,
                        'available' => $available,
                        'my_claim_status' => $myClaim?->status,

                        'supporting_file_url' => $task->policy_doc_path
                            ? asset('storage/' . ltrim($task->policy_doc_path, '/'))
                            : null,
                    ];
                })
                ->values();

            // 2) Klaim berjalan (periode aktif)
            $claimsActivePeriod = AdditionalTaskClaim::query()
                ->with(['task.period'])
                ->where('user_id', $me->id)
                ->whereIn('status', ['active', 'submitted', 'validated'])
                ->whereHas('task', function ($q) use ($me) {
                    $q->forUnit($me->unit_id)->forActivePeriod();
                })
                ->orderByDesc('id')
                ->get();

            $currentClaims = $claimsActivePeriod->values();

            // 3) Riwayat klaim (semua periode, untuk referensi)
            $historyClaims = AdditionalTaskClaim::query()
                ->with(['task.period', 'reviewedBy:id,name'])
                ->where('user_id', $me->id)
                ->whereHas('task', function ($q) use ($me) {
                    $q->forUnit($me->unit_id);
                })
                ->whereNotIn('status', ['active', 'submitted', 'validated'])
                ->orderByDesc('id')
                ->limit(100)
                ->get();

            // 4) Tugas tidak diklaim (terlewat) untuk periode aktif
            if ($activePeriod) {
                $missedTasks = AdditionalTask::query()
                    ->with(['period:id,name'])
                    ->forUnit($me->unit_id)
                    ->forActivePeriod()
                    ->notDraft()
                    ->excludeCreator($me->id)
                    ->whereDoesntHave('claims', function ($q) use ($me) {
                        $q->where('user_id', $me->id);
                    })
                    ->orderByDesc('id')
                    ->get()
                    ->filter(function (AdditionalTask $task) use ($now, $tz) {
                        $duePassed = false;
                        if ($task->due_date) {
                            $deadlineTime = $task->due_time ?: '23:59:59';
                            $deadlineDate = Carbon::parse($task->due_date)->toDateString();
                            $deadline = Carbon::parse($deadlineDate . ' ' . $deadlineTime, $tz);
                            $duePassed = $deadline->lessThan($now);
                        }

                        return $duePassed || $task->status !== 'open';
                    })
                    ->values();
            }
        }

        return view('pegawai_medis.additional_tasks.index', [
            'activePeriod' => $activePeriod,
            'availableTasks' => $availableTasks,
            'currentClaims' => $currentClaims,
            'historyClaims' => $historyClaims,
            'missedTasks' => $missedTasks,
        ]);
    }
}
