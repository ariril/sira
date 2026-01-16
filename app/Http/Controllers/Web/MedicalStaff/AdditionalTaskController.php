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

class AdditionalTaskController extends Controller
{
    public function index(): View
    {
        $me = Auth::user();
        abort_unless($me && $me->role === 'pegawai_medis', 403);

        $activePeriod = AssessmentPeriodGuard::resolveActive();

        $tasks = collect();
        $myClaimsByTaskId = collect();

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
        }

        return view('pegawai_medis.additional_tasks.index', [
            'activePeriod' => $activePeriod,
            'tasks' => $tasks,
            'myClaimsByTaskId' => $myClaimsByTaskId,
        ]);
    }
}
