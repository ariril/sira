<?php

namespace App\Http\Controllers\Web\AdminHospital;

use App\Enums\AssessmentApprovalStatus as AStatus;
use App\Http\Controllers\Controller;
use App\Models\AssessmentApproval;
use App\Models\PerformanceAssessment;
use App\Services\AssessmentApprovals\AssessmentApprovalFlow;
use App\Services\AssessmentApprovals\AssessmentApprovalDetailService;
use App\Services\AssessmentApprovals\AssessmentApprovalService;
use App\Support\AssessmentPeriodGuard;
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
        // IMPORTANT:
        // - If user selects (Semua), the browser sends period_id empty -> we must NOT apply period filter.
        // - If period_id is not present at all, we also default to (Semua) to match the UI.
        $periodId = $request->filled('period_id') ? (int) $request->input('period_id') : null;

        // Composite filter values covering status and level scopes
        // Values: 'all','pending_l1','approved_l1','rejected_l1','pending_all','approved_all','rejected_all'
        // Use sentinel "all" to represent (Semua) so pagination won't drop it
        $status = (string) $request->input('status', 'pending_l1');

        $items = collect();
        if (Schema::hasTable('assessment_approvals')) {
            $builder = DB::table('assessment_approvals as aa')
                ->join('performance_assessments as pa', 'pa.id', '=', 'aa.performance_assessment_id')
                ->leftJoin('users as u', 'u.id', '=', 'pa.user_id')
                ->leftJoin('assessment_periods as ap', 'ap.id', '=', 'pa.assessment_period_id')
                ->selectRaw("aa.id, aa.status, aa.level, aa.note, aa.created_at, aa.acted_at, u.name as user_name, ap.name as period_name, pa.total_wsm_score,
                              EXISTS(SELECT 1 FROM assessment_approvals aa2 WHERE aa2.performance_assessment_id = aa.performance_assessment_id AND aa2.attempt = aa.attempt AND aa2.invalidated_at IS NULL AND aa2.level = 2 AND aa2.status = 'approved') as has_lvl2_approved")
                ->orderByDesc('aa.id');

            // Only show current attempt approvals + not invalidated
            $builder->whereNull('aa.invalidated_at')
                // assessment_periods.approval_attempt defaults to 0 (legacy). Treat 0 as attempt=1.
                ->whereRaw('aa.attempt = COALESCE(NULLIF(ap.approval_attempt, 0), 1)');

            // Apply combined status+level filter
            $statusNormalized = $status === 'all' ? '' : $status;

            switch ($statusNormalized) {
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
            // Preserve status sentinel across pagination
            $queryParams = $request->query();
            $queryParams['status'] = $status;
            $items = $builder->paginate($perPage)->appends($queryParams);
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
    public function approve(Request $request, AssessmentApproval $assessment, AssessmentApprovalService $svc): RedirectResponse
    {
        $this->authorizeAccess();
        $assessment->loadMissing('performanceAssessment.assessmentPeriod');
        $period = $assessment->performanceAssessment?->assessmentPeriod;
        AssessmentPeriodGuard::forbidWhenApprovalRejected($period, 'Setujui Penilaian');
        try {
            $svc->approve($assessment, Auth::user(), (string) $request->input('note'));
            return back()->with('status', 'Penilaian disetujui.');
        } catch (\Throwable $e) {
            return back()->withErrors(['status' => $e->getMessage()]);
        }
    }

    /** Reject selected approval (level 1). */
    public function reject(Request $request, AssessmentApproval $assessment, AssessmentApprovalService $svc): RedirectResponse
    {
        $this->authorizeAccess();
        $request->validate(['note' => ['required','string','max:500']]);
        $assessment->loadMissing('performanceAssessment.assessmentPeriod');
        $period = $assessment->performanceAssessment?->assessmentPeriod;
        AssessmentPeriodGuard::forbidWhenApprovalRejected($period, 'Tolak Penilaian');
        try {
            $svc->reject($assessment, Auth::user(), (string) $request->input('note'));
            return back()->with('status', 'Penilaian ditolak.');
        } catch (\Throwable $e) {
            return back()->withErrors(['status' => $e->getMessage()]);
        }
    }

    public function resubmit(Request $request, AssessmentApproval $assessment, AssessmentApprovalService $svc): RedirectResponse
    {
        $this->authorizeAccess();
        $assessment->loadMissing('performanceAssessment.assessmentPeriod');
        $period = $assessment->performanceAssessment?->assessmentPeriod;
        AssessmentPeriodGuard::forbidWhenApprovalRejected($period, 'Ajukan Ulang Penilaian');
        try {
            $svc->resubmitAfterReject($assessment, Auth::user());
            return back()->with('status', 'Penilaian diajukan ulang.');
        } catch (\Throwable $e) {
            return back()->withErrors(['status' => $e->getMessage()]);
        }
    }

    public function detail(Request $request, AssessmentApproval $assessment, AssessmentApprovalService $approvalSvc, AssessmentApprovalDetailService $detailSvc): View
    {
        $this->authorizeAccess();

        $assessment->load([
            'performanceAssessment.user',
            'performanceAssessment.assessmentPeriod',
            'performanceAssessment.details.performanceCriteria',
            'performanceAssessment.approvals.approver',
        ]);

        $pa = $assessment->performanceAssessment;
        $approvalSvc->assertCanViewPerformanceAssessment(Auth::user(), $pa);

        $breakdown = $detailSvc->getBreakdown($pa);
        $raw = $detailSvc->getRawImportedValues($pa);

        return view('shared.assessment_approval_detail', [
            'approval' => $assessment,
            'pa' => $pa,
            'breakdown' => $breakdown,
            'rawValues' => $raw,
            'backUrl' => route('admin_rs.assessments.pending'),
        ]);
    }

    private function authorizeAccess(): void
    {
        $user = Auth::user();
        if (!$user || $user->role !== 'admin_rs') abort(403);
    }
}
