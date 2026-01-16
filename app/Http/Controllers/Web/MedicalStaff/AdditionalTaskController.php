<?php

namespace App\Http\Controllers\Web\MedicalStaff;

use App\Http\Controllers\Controller;
use App\Models\AdditionalTask;
use App\Models\AdditionalTaskClaim;
use App\Services\AdditionalTasks\AdditionalTaskStatusService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Schema;
use Illuminate\View\View;
use App\Support\AssessmentPeriodGuard;
use Illuminate\Database\Eloquent\Builder;
use App\Models\AssessmentPeriod;

class AdditionalTaskController extends Controller
{
    public function index(): View
    {
        $me = Auth::user();
        abort_unless($me && $me->role === 'pegawai_medis', 403);

        $activePeriod = AssessmentPeriodGuard::resolveActive();

        $tasks = collect();
        $myClaimsByTaskId = collect();
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

            $tasks = AdditionalTask::query()
                ->with(['period:id,name'])
                ->withCount([
                    'claims as claims_used' => function (Builder $q) {
                        $q->whereIn('status', AdditionalTaskStatusService::ACTIVE_STATUSES);
                    },
                ])
                ->forUnit($me->unit_id)
                ->where(function (Builder $q) {
                    $q->whereNull('assessment_period_id')
                        ->orWhereHas('period', fn (Builder $p) => $p->where('status', \App\Models\AssessmentPeriod::STATUS_ACTIVE));
                })
                ->open()
                ->excludeCreator($me->id)
                ->orderByDesc('id')
                ->get();

            if ($tasks->isNotEmpty()) {
                $taskIds = $tasks->pluck('id')->all();
                $claims = AdditionalTaskClaim::query()
                    ->select([
                        'id',
                        'additional_task_id',
                        'status',
                        'submitted_at',
                        'reviewed_at',
                        'review_comment',
                        'awarded_points',
                        'result_note',
                        'result_file_path',
                    ])
                    ->where('user_id', $me->id)
                    ->whereIn('additional_task_id', $taskIds)
                    ->orderByDesc('id')
                    ->get();

                $myClaimsByTaskId = $claims
                    ->groupBy('additional_task_id')
                    ->map(fn ($items) => $items->first());
            }

            // UI lama: daftar tugas tersedia + status klaim saya per tugas
            $availableTasks = $tasks->map(function ($t) use ($myClaimsByTaskId) {
                $claim = $myClaimsByTaskId->get($t->id);
                $t->setAttribute('my_claim_status', $claim?->status);
                $t->setAttribute('period_name', $t->period?->name);
                $max = $t->max_claims;
                $used = (int) ($t->claims_used ?? 0);
                $available = empty($max) ? true : ($used < (int) $max);
                $t->setAttribute('available', $available);
                return $t;
            });

            // Klaim saya (berjalan = active/submitted, riwayat = approved/rejected)
            $currentClaims = AdditionalTaskClaim::query()
                ->with(['task.period:id,name,status'])
                ->where('user_id', $me->id)
                ->whereIn('status', ['active', 'submitted'])
                ->whereHas('task', fn (Builder $q) => $q->where('unit_id', (int) $me->unit_id))
                ->orderByDesc('submitted_at')
                ->orderByDesc('created_at')
                ->get();

            $historyClaims = AdditionalTaskClaim::query()
                ->with(['task.period:id,name,status'])
                ->where('user_id', $me->id)
                ->whereIn('status', ['approved', 'rejected'])
                ->whereHas('task', fn (Builder $q) => $q->where('unit_id', (int) $me->unit_id))
                ->orderByDesc('reviewed_at')
                ->orderByDesc('submitted_at')
                ->get();

            // Tugas yang pernah diberikan tetapi tidak disubmit oleh user
            if ($activePeriod?->id) {
                $missedTasks = AdditionalTask::query()
                    ->with(['period:id,name,status'])
                    ->withCount([
                        'claims as claims_used' => function (Builder $q) {
                            $q->whereIn('status', AdditionalTaskStatusService::ACTIVE_STATUSES);
                        },
                    ])
                    ->forUnit($me->unit_id)
                    ->where('assessment_period_id', (int) $activePeriod->id)
                    ->where('status', 'closed')
                    ->excludeCreator($me->id)
                    ->whereDoesntHave('claims', fn (Builder $q) => $q->where('user_id', $me->id))
                    ->orderByDesc('id')
                    ->limit(100)
                    ->get();
            }
        }

        return view('pegawai_medis.additional_tasks.index', [
            'activePeriod' => $activePeriod,
            // keep for compatibility
            'tasks' => $tasks,
            'myClaimsByTaskId' => $myClaimsByTaskId,

            // UI lama
            'availableTasks' => $availableTasks,
            'currentClaims' => $currentClaims,
            'historyClaims' => $historyClaims,
            'missedTasks' => $missedTasks,
        ]);
    }
}
