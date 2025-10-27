<?php

namespace App\Http\Controllers\Web\PolyclinicHead;

use App\Enums\AssessmentApprovalStatus as AStatus;
use App\Http\Controllers\Controller;
use App\Models\AssessmentApproval;
use Illuminate\Contracts\Pagination\LengthAwarePaginator as LengthAwarePaginatorContract;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\View\View;

class AssessmentApprovalController extends Controller
{
    /**
     * List pending assessments for Kepala Poliklinik (Level 3 final approval).
     */
    public function index(Request $request): View
    {
        $this->authorizeAccess();

        $perPageOptions = [5, 10, 12, 20, 30, 50];
        $data = $request->validate([
            'q'        => ['nullable', 'string', 'max:100'],
            'per_page' => ['nullable', 'integer', 'in:' . implode(',', $perPageOptions)],
        ]);
        $q = (string) ($data['q'] ?? '');
        $perPage = (int) ($data['per_page'] ?? 10);

        $validStatuses = ['pending','approved','rejected'];
        if ($request->has('status')) {
            $statusInput = (string) $request->input('status');
            $status = in_array($statusInput, $validStatuses, true) ? $statusInput : '';
        } else {
            $status = 'pending';
        }

        // Determine scope units for Kepala Poliklinik
        $me = Auth::user();
        $scopeUnitIds = collect();
        if (Schema::hasTable('units')) {
            if ($me && $me->unit_id) {
                $scopeUnitIds = DB::table('units')->where('parent_id', $me->unit_id)->pluck('id');
            }
            if ($scopeUnitIds->isEmpty()) {
                $scopeUnitIds = DB::table('units')->where('type', 'poliklinik')->pluck('id');
            }
        }

        // Build items list
        if (Schema::hasTable('assessment_approvals') && Schema::hasTable('performance_assessments')) {
            $builder = DB::table('assessment_approvals as aa')
                ->join('performance_assessments as pa', 'pa.id', '=', 'aa.performance_assessment_id')
                ->leftJoin('users as u', 'u.id', '=', 'pa.user_id')
                ->leftJoin('assessment_periods as ap', 'ap.id', '=', 'pa.assessment_period_id')
                ->selectRaw('aa.id, aa.status, aa.level, aa.note, aa.created_at, u.name as user_name, ap.name as period_name, pa.total_wsm_score')
                ->orderByDesc('aa.id');

            // Level 3 only
            $builder->where('aa.level', 3);

            // Scope to units under Poliklinik Head
            if ($scopeUnitIds->isNotEmpty()) {
                $builder->whereIn('u.unit_id', $scopeUnitIds);
            }

            if ($status !== '') $builder->where('aa.status', $status);
            if ($q !== '') {
                $builder->where(function ($w) use ($q) {
                    $w->where('u.name', 'like', "%$q%")
                      ->orWhere('ap.name', 'like', "%$q%");
                });
            }

            $items = $builder->paginate($perPage)->withQueryString();
        } else {
            $items = new LengthAwarePaginator(
                collect(),
                0,
                $perPage,
                (int) $request->integer('page', 1),
                [
                    'path' => $request->url(),
                    'query' => $request->query(),
                ]
            );
        }

        return view('kepala_poli.assessments.index', [
            'items'   => $items,
            'q'       => $q,
            'status'  => $status,
            'perPage' => $perPage,
            'perPageOptions' => $perPageOptions,
        ]);
    }

    /** Approve selected approval (level 3). */
    public function approve(Request $request, AssessmentApproval $assessment): RedirectResponse
    {
        $this->authorizeAccess();
        // Only level 3 allowed here
        if ((int) ($assessment->level ?? 0) !== 3) {
            abort(403);
        }
        if ($assessment->status === AStatus::APPROVED->value) {
            return back()->with('status', 'Penilaian sudah disetujui.');
        }
        $assessment->update([
            'status'   => AStatus::APPROVED->value,
            'note'     => (string) $request->input('note'),
            'acted_at' => now(),
        ]);
        return back()->with('status', 'Penilaian disetujui.');
    }

    /** Reject selected approval (level 3). */
    public function reject(Request $request, AssessmentApproval $assessment): RedirectResponse
    {
        $this->authorizeAccess();
        if ((int) ($assessment->level ?? 0) !== 3) {
            abort(403);
        }
        $request->validate(['note' => ['required','string','max:500']]);
        $assessment->update([
            'status'   => AStatus::REJECTED->value,
            'note'     => (string) $request->input('note'),
            'acted_at' => now(),
        ]);
        return back()->with('status', 'Penilaian ditolak.');
    }

    private function authorizeAccess(): void
    {
        $user = Auth::user();
        if (!$user || $user->role !== 'kepala_poliklinik') abort(403);
    }
}
