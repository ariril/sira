<?php

namespace App\Http\Controllers\Web\AdminHospital;

use App\Enums\AssessmentApprovalStatus as AStatus;
use App\Http\Controllers\Controller;
use App\Models\AssessmentApproval;
use App\Models\PerformanceAssessment;
use Illuminate\Contracts\Pagination\Paginator;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\View\View;

class AssessmentApprovalController extends Controller
{
    /**
     * List pending assessments for Admin RS (Level 1 review).
     */
    public function index(Request $request): View
    {
        $this->authorizeAccess();
        // Pagination options to mirror Super Admin
        $perPageOptions = [5, 10, 12, 20, 30, 50];

        // Validate inputs (status handled manually to allow "Semua"/empty)
        $data = $request->validate([
            'q'        => ['nullable', 'string', 'max:100'],
            'per_page' => ['nullable', 'integer', 'in:' . implode(',', $perPageOptions)],
        ]);

        $q = (string) ($data['q'] ?? '');
        $perPage = (int) ($data['per_page'] ?? 10); // keep existing default 10

        // Status filter: default to 'pending' only when not provided; allow empty for "Semua".
        $validStatuses = ['pending','approved','rejected'];
        if ($request->has('status')) {
            $statusInput = (string) $request->input('status');
            $status = in_array($statusInput, $validStatuses, true) ? $statusInput : '';
        } else {
            $status = 'pending';
        }

        $items = collect();
        if (Schema::hasTable('assessment_approvals')) {
            $builder = DB::table('assessment_approvals as aa')
                ->join('performance_assessments as pa', 'pa.id', '=', 'aa.performance_assessment_id')
                ->leftJoin('users as u', 'u.id', '=', 'pa.user_id')
                ->leftJoin('assessment_periods as ap', 'ap.id', '=', 'pa.assessment_period_id')
                ->selectRaw('aa.id, aa.status, aa.level, aa.note, aa.created_at, u.name as user_name, ap.name as period_name, pa.total_wsm_score')
                ->orderByDesc('aa.id');

            if ($status !== '') $builder->where('aa.status', $status);
            if ($q !== '') {
                $builder->where(function ($w) use ($q) {
                    $w->where('u.name', 'like', "%$q%")
                      ->orWhere('ap.name', 'like', "%$q%");
                });
            }
            $items = $builder->paginate($perPage)->withQueryString();
        } else {
            // Ensure the view can still call ->links() even when the table doesn't exist yet
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

        return view('admin_rs.assessments.index', [
            'items'   => $items,
            'q'       => $q,
            'status'  => $status,
            'perPage' => $perPage,
            'perPageOptions' => $perPageOptions,
        ]);
    }

    /** Approve selected approval (level 1). */
    public function approve(Request $request, AssessmentApproval $assessment): RedirectResponse
    {
        $this->authorizeAccess();
        // Prevent double approval
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

    /** Reject selected approval (level 1). */
    public function reject(Request $request, AssessmentApproval $assessment): RedirectResponse
    {
        $this->authorizeAccess();
        $request->validate(['note' => ['required','string','max:500']]);
        // Disallow reject if already approved at level 2
        if (($assessment->status === AStatus::APPROVED->value) && ((int)($assessment->level ?? 1) >= 2)) {
            return back()->withErrors(['status' => 'Tidak dapat menolak, sudah disetujui pada level 2.']);
        }
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
        if (!$user || $user->role !== 'admin_rs') abort(403);
    }
}
