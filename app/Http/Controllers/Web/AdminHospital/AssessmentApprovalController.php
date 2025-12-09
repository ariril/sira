<?php

namespace App\Http\Controllers\Web\AdminHospital;

use App\Enums\AssessmentApprovalStatus as AStatus;
use App\Http\Controllers\Controller;
use App\Models\AssessmentApproval;
use App\Models\PerformanceAssessment;
use App\Services\AssessmentApprovalFlow;
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
            'q'         => ['nullable', 'string', 'max:100'],
            'per_page'  => ['nullable', 'integer', 'in:' . implode(',', $perPageOptions)],
            'period_id' => ['nullable','integer','exists:assessment_periods,id'],
        ]);

        $q = (string) ($data['q'] ?? '');
        $perPage = (int) ($data['per_page'] ?? 10); // keep existing default 10

        $periodOptions = Schema::hasTable('assessment_periods')
            ? DB::table('assessment_periods')->orderByDesc('start_date')->pluck('name','id')
            : collect();
        $periodOptions = $periodOptions->prepend('(Semua)', '')->toArray();
        $activePeriodId = Schema::hasTable('assessment_periods')
            ? DB::table('assessment_periods')->where('status','active')->value('id')
            : null;
        $periodId = $request->filled('period_id') ? (int) $request->input('period_id') : ($activePeriodId ?? null);

        // Composite filter values covering status and level scopes
        // Values: '', 'pending_l1','approved_l1','rejected_l1','pending_all','approved_all','rejected_all'
        $status = (string) $request->input('status', 'pending_l1');

        $items = collect();
        if (Schema::hasTable('assessment_approvals')) {
            $builder = DB::table('assessment_approvals as aa')
                ->join('performance_assessments as pa', 'pa.id', '=', 'aa.performance_assessment_id')
                ->leftJoin('users as u', 'u.id', '=', 'pa.user_id')
                ->leftJoin('assessment_periods as ap', 'ap.id', '=', 'pa.assessment_period_id')
                ->selectRaw("aa.id, aa.status, aa.level, aa.note, aa.created_at, aa.acted_at, u.name as user_name, ap.name as period_name, pa.total_wsm_score,
                              EXISTS(SELECT 1 FROM assessment_approvals aa2 WHERE aa2.performance_assessment_id = aa.performance_assessment_id AND aa2.level = 2 AND aa2.status = 'approved') as has_lvl2_approved")
                ->orderByDesc('aa.id');

            // Apply combined status+level filter
            switch ($status) {
                case 'pending_l1':
                    $builder->where('aa.level', 1)->where('aa.status', 'pending');
                    break;
                case 'approved_l1':
                    $builder->where('aa.level', 1)->where('aa.status', 'approved');
                    break;
                case 'rejected_l1':
                    $builder->where('aa.level', 1)->where('aa.status', 'rejected');
                    break;
                case 'pending_all':
                    $builder->where('aa.status', 'pending');
                    break;
                case 'approved_all':
                    $builder->where('aa.status', 'approved');
                    break;
                case 'rejected_all':
                    $builder->where('aa.status', 'rejected');
                    break;
                case '': // Semua
                default:
                    // No additional filters
                    break;
            }
            if ($q !== '') {
                $builder->where(function ($w) use ($q) {
                    $w->where('u.name', 'like', "%$q%")
                      ->orWhere('ap.name', 'like', "%$q%");
                });
            }
            if ($periodId) {
                $builder->where('pa.assessment_period_id', $periodId);
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
            'periodOptions' => $periodOptions,
            'periodId' => $periodId,
        ]);
    }

    /** Approve selected approval (level 1). */
    public function approve(Request $request, AssessmentApproval $assessment): RedirectResponse
    {
        $this->authorizeAccess();
        // Only Level 1 approval allowed here and must be pending
        if ((int)($assessment->level ?? 0) !== 1) {
            return back()->withErrors(['status' => 'Tidak dapat menyetujui: bukan level 1.']);
        }
        // Dengan casts, status adalah instance enum; samakan dengan Level 2
        if ($assessment->status === AStatus::APPROVED) {
            return back()->with('status', 'Penilaian sudah disetujui.');
        }
        if ($assessment->status !== AStatus::PENDING) {
            return back()->withErrors(['status' => 'Status saat ini tidak dapat disetujui.']);
        }
        $assessment->update([
            'status'   => AStatus::APPROVED->value,
            'note'     => (string) $request->input('note'),
            'acted_at' => now(),
        ]);
        AssessmentApprovalFlow::ensureNextLevel($assessment, Auth::id());
        return back()->with('status', 'Penilaian disetujui.');
    }

    /** Reject selected approval (level 1). */
    public function reject(Request $request, AssessmentApproval $assessment): RedirectResponse
    {
        $this->authorizeAccess();
        $request->validate(['note' => ['required','string','max:500']]);
        if ($assessment->status !== AStatus::PENDING) {
            return back()->withErrors(['status' => 'Tidak dapat menolak karena status sudah ' . $assessment->status->value . '.']);
        }
        // Disallow reject if already approved at level 2 for the same assessment
        $hasLvl2Approved = DB::table('assessment_approvals')
            ->where('performance_assessment_id', $assessment->performance_assessment_id)
            ->where('level', 2)
            ->where('status', AStatus::APPROVED->value)
            ->exists();
        if ($hasLvl2Approved) {
            return back()->withErrors(['status' => 'Tidak dapat menolak, sudah disetujui pada level 2.']);
        }
        $assessment->update([
            'status'   => AStatus::REJECTED->value,
            'note'     => (string) $request->input('note'),
            'acted_at' => now(),
        ]);
        AssessmentApprovalFlow::removeFutureLevels($assessment);
        return back()->with('status', 'Penilaian ditolak.');
    }

    private function authorizeAccess(): void
    {
        $user = Auth::user();
        if (!$user || $user->role !== 'admin_rs') abort(403);
    }
}
