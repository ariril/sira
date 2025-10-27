<?php

namespace App\Http\Controllers\Web\MedicalStaff;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class AdditionalContributionController extends Controller
{
    /**
     * Show available and claimed/completed additional tasks for the user.
     */
    public function index(Request $request): View
    {
        $me = Auth::user();
        $unitId = $me?->unit_id;

        // My active claims
        $myActiveClaims = DB::table('additional_task_claims as c')
            ->join('additional_tasks as t', 't.id', '=', 'c.additional_task_id')
            ->leftJoin('assessment_periods as ap', 'ap.id', '=', 't.assessment_period_id')
            ->selectRaw('c.id as claim_id, c.claimed_at, c.cancel_deadline_at, t.id as task_id, t.title, t.due_date, t.bonus_amount, t.points, t.max_claims, ap.name as period_name')
            ->where('c.user_id', $me->id)
            ->where('c.status', 'active')
            ->orderByDesc('c.claimed_at')
            ->get();

        // Completed claims
        $myCompletedClaims = DB::table('additional_task_claims as c')
            ->join('additional_tasks as t', 't.id', '=', 'c.additional_task_id')
            ->leftJoin('assessment_periods as ap', 'ap.id', '=', 't.assessment_period_id')
            ->selectRaw('c.id as claim_id, c.completed_at, t.id as task_id, t.title, t.bonus_amount, t.points, ap.name as period_name')
            ->where('c.user_id', $me->id)
            ->where('c.status', 'completed')
            ->orderByDesc('c.completed_at')
            ->limit(20)
            ->get();

        // Tasks open in my unit (potentially available)
        $openTasks = DB::table('additional_tasks as t')
            ->leftJoin('assessment_periods as ap', 'ap.id', '=', 't.assessment_period_id')
            ->selectRaw('t.id, t.title, t.description, t.due_date, t.bonus_amount, t.points, t.max_claims, t.status, ap.name as period_name')
            ->where('t.unit_id', $unitId)
            ->where('t.status', 'open')
            ->orderBy('t.due_date')
            ->get();

        // Map: total used claims per task and my active claim id per task
        $taskIds = $openTasks->pluck('id');
        $usedCounts = DB::table('additional_task_claims')
            ->selectRaw('additional_task_id, count(*) as used')
            ->whereIn('additional_task_id', $taskIds)
            ->whereIn('status', ['active','completed'])
            ->groupBy('additional_task_id')
            ->pluck('used','additional_task_id');
        $myActiveMap = DB::table('additional_task_claims')
            ->select('id','additional_task_id')
            ->whereIn('additional_task_id', $taskIds)
            ->where('user_id', $me->id)
            ->where('status', 'active')
            ->get()->keyBy('additional_task_id');

        $availableTasks = $openTasks->map(function($t) use ($usedCounts, $myActiveMap) {
            $t->claims_used = (int)($usedCounts[$t->id] ?? 0);
            $t->my_claim_id = optional($myActiveMap->get($t->id))->id;
            $t->available   = empty($t->max_claims) || $t->claims_used < (int)$t->max_claims;
            return $t;
        });

        return view('pegawai_medis.additional_contributions.index', [
            'availableTasks'   => $availableTasks,
            'myActiveClaims'   => $myActiveClaims,
            'myCompletedClaims'=> $myCompletedClaims,
        ]);
    }
}
