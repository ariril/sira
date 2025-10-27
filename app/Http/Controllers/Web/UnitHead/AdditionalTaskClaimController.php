<?php

namespace App\Http\Controllers\Web\UnitHead;

use App\Http\Controllers\Controller;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\View\View;
use Illuminate\Support\Carbon;
use Illuminate\Http\Request;

class AdditionalTaskClaimController extends Controller
{
    /**
     * Monitoring klaim tugas tambahan pada unit Kepala Unit.
     */
    public function index(Request $request): View
    {
        $this->authorizeAccess();
        $me = Auth::user();
        $unitId = $me?->unit_id;

        $perPageOptions = [10, 20, 30, 50];
        $data = $request->validate([
            'q'        => ['nullable','string','max:100'],
            'status'   => ['nullable','string','in:active,completed,cancelled'],
            'overdue'  => ['nullable','boolean'],
            'per_page' => ['nullable','integer','in:' . implode(',', $perPageOptions)],
        ]);
        $q = (string)($data['q'] ?? '');
        $status = $data['status'] ?? '';
        $overdue = (bool)($data['overdue'] ?? false);
        $perPage = (int) ($data['per_page'] ?? 20);

        if ($unitId && Schema::hasTable('additional_task_claims') && Schema::hasTable('additional_tasks')) {
            $builder = DB::table('additional_task_claims as c')
                ->join('users as u', 'u.id', '=', 'c.user_id')
                ->join('additional_tasks as t', 't.id', '=', 'c.additional_task_id')
                ->leftJoin('assessment_periods as ap', 'ap.id', '=', 't.assessment_period_id')
                ->selectRaw('c.id, c.status, c.claimed_at, c.completed_at, c.cancelled_at, c.cancel_deadline_at, u.name as user_name, t.title as task_title, ap.name as period_name')
                ->where('t.unit_id', $unitId)
                ->orderByDesc('c.id');

            if ($q !== '') {
                $builder->where(function($w) use ($q) {
                    $w->where('u.name', 'like', "%$q%")
                      ->orWhere('t.title', 'like', "%$q%")
                      ->orWhere('ap.name', 'like', "%$q%");
                });
            }
            if (!empty($status)) $builder->where('c.status', $status);
            if ($overdue) {
                $builder->whereNotNull('c.cancel_deadline_at')->where('c.cancel_deadline_at', '<', Carbon::now())->where('c.status','active');
            }

            $items = $builder->paginate($perPage)->withQueryString();
        } else {
            $items = new LengthAwarePaginator(collect(), 0, $perPage, (int) $request->integer('page', 1), [
                'path' => $request->url(),
                'query' => $request->query(),
            ]);
        }

        return view('kepala_unit.additional_task_claims.index', [
            'items' => $items,
            'q' => $q,
            'status' => $status,
            'overdue' => $overdue,
            'perPage' => $perPage,
            'perPageOptions' => $perPageOptions,
        ]);
    }

    private function authorizeAccess(): void
    {
        $user = Auth::user();
        if (!$user || $user->role !== 'kepala_unit') abort(403);
    }
}
