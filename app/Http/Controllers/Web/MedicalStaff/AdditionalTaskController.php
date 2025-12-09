<?php

namespace App\Http\Controllers\Web\MedicalStaff;

use App\Http\Controllers\Controller;
use App\Models\AdditionalTask;
use App\Models\AdditionalTaskClaim;
use App\Services\AdditionalTaskStatusService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Carbon;
use Illuminate\View\View;

class AdditionalTaskController extends Controller
{
    /**
     * Daftar tugas tambahan yang masih tersedia untuk diklaim oleh pegawai medis.
     */
    public function index(): View
    {
        $me = Auth::user();
        abort_unless($me && $me->role === 'pegawai_medis', 403);

        $tasks = collect();
        if (
            Schema::hasTable('additional_tasks') &&
            Schema::hasTable('additional_task_claims') &&
            Schema::hasTable('assessment_periods')
        ) {
            AdditionalTaskStatusService::syncForUnit($me->unit_id);

            $now = Carbon::now('Asia/Jakarta');

            $tasks = AdditionalTask::query()
                ->with(['period:id,name'])
                ->withCount([
                    'claims as active_claims' => function ($query) {
                        $query->whereIn('status', AdditionalTaskStatusService::ACTIVE_STATUSES);
                    },
                ])
                ->where('unit_id', $me->unit_id)
                ->where('status', 'open')
                ->orderByDesc('id')
                ->get()
                ->filter(function (AdditionalTask $task) use ($now) {
                    if ($task->due_date) {
                        $dueEnd = Carbon::parse($task->due_date, 'Asia/Jakarta')->endOfDay();
                        if ($dueEnd->lessThan($now)) {
                            return false;
                        }
                    }
                    return true;
                })
                ->filter(function (AdditionalTask $task) {
                    if (empty($task->max_claims)) {
                        return true;
                    }
                    return (int) $task->active_claims < (int) $task->max_claims;
                })
                ->map(function (AdditionalTask $task) {
                    return (object) [
                        'id' => $task->id,
                        'title' => $task->title,
                        'period_name' => $task->period?->name,
                        'due_date' => $task->due_date
                            ? Carbon::parse($task->due_date)->translatedFormat('d M Y')
                            : '-',
                        'points' => $task->points,
                        'bonus_amount' => $task->bonus_amount,
                        'max_claims' => $task->max_claims,
                        'active_claims' => $task->active_claims,
                        'supporting_file_url' => $task->policy_doc_path
                            ? asset('storage/'.ltrim($task->policy_doc_path, '/'))
                            : null,
                    ];
                })
                ->values();
        }

        return view('pegawai_medis.additional_tasks.index', [ 'tasks' => $tasks ]);
    }
}
