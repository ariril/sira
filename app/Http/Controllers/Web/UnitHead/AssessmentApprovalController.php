<?php

namespace App\Http\Controllers\Web\UnitHead;

use App\Enums\AssessmentApprovalStatus;
use App\Http\Controllers\Controller;
use App\Models\AssessmentApproval;
use App\Services\AssessmentApprovals\AssessmentApprovalService;
use App\Services\AssessmentApprovals\AssessmentApprovalDetailService;
use App\Support\AssessmentPeriodGuard;
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
     * List pending assessments for Kepala Unit (Level 2 review).
     */
    public function index(Request $request): View
    {
        $this->authorizeAccess();
        $perPageOptions = [5, 10, 12, 20, 30, 50];

        $periodFilterRequested = $request->query->has('period_id');
        if ($request->input('period_id') === '') {
            $request->merge(['period_id' => null]);
        }

        $data = $request->validate([
            'q'         => ['nullable', 'string', 'max:100'],
            'per_page'  => ['nullable', 'integer', 'in:' . implode(',', $perPageOptions)],
            'period_id' => ['nullable', 'integer', 'exists:assessment_periods,id'],
        ]);

        $q = (string) ($data['q'] ?? '');
        $perPage = (int) ($data['per_page'] ?? 10);

        // Use sentinel "all" to represent (Semua) so pagination keeps the choice
        $status = (string) $request->input('status', 'pending_l2');

        $periodOptions = Schema::hasTable('assessment_periods')
            ? DB::table('assessment_periods')->orderByDesc('start_date')->pluck('name', 'id')->prepend('(Semua)', '')
            : collect(['' => '(Semua)']);
        // Default: tampilkan semua periode (requirement baru). Hanya terapkan filter jika user memilih.
        $periodId = $periodFilterRequested
            ? ($data['period_id'] ?? null)
            : null;

        $me = Auth::user();
        $unitId = $me?->unit_id;

        if (Schema::hasTable('assessment_approvals') && Schema::hasTable('performance_assessments') && Schema::hasTable('users')) {
            $builder = DB::table('assessment_approvals as aa')
                ->join('performance_assessments as pa', 'pa.id', '=', 'aa.performance_assessment_id')
                ->leftJoin('users as u', 'u.id', '=', 'pa.user_id')
                ->leftJoin('assessment_periods as ap', 'ap.id', '=', 'pa.assessment_period_id')
                ->selectRaw("aa.id, aa.status, aa.level, aa.note, aa.created_at, aa.acted_at, u.name as user_name, ap.name as period_name, pa.total_wsm_score, ap.status as period_status, ap.rejected_at as period_rejected_at,
                              EXISTS(SELECT 1 FROM assessment_approvals aa1 WHERE aa1.performance_assessment_id = aa.performance_assessment_id AND aa1.attempt = aa.attempt AND aa1.invalidated_at IS NULL AND aa1.level = 1 AND aa1.status = 'approved') as has_lvl1_approved")
                ->orderByDesc('aa.id');

            // Only show current attempt approvals + not invalidated
            $builder->whereNull('aa.invalidated_at')
                ->whereRaw('aa.attempt = COALESCE(ap.approval_attempt, 1)');

            // Scope to Kepala Unit's unit
            if ($unitId) {
                $builder->where('u.unit_id', $unitId);
            }

            // Hide any rows that belong to a period that is already marked rejected in approval.
            $builder->whereNull('ap.rejected_at');
            $statusNormalized = $status === 'all' ? '' : $status;
            $builder = $this->applyStatusFilter($builder, $statusNormalized);
            if ($q !== '') {
                $builder->where(function ($w) use ($q) {
                    $w->where('u.name', 'like', "%$q%")
                      ->orWhere('ap.name', 'like', "%$q%");
                });
            }
            if ($periodId) {
                $builder->where('pa.assessment_period_id', $periodId);
            }

            $items = $builder->paginate($perPage)->appends(array_merge($request->query(), ['status' => $status]));
        } else {
            $items = new LengthAwarePaginator(collect(), 0, $perPage, (int) $request->integer('page', 1), [
                'path' => $request->url(),
                'query' => $request->query(),
            ]);
        }

        return view('kepala_unit.assessments.index', [
            'items'   => $items,
            'q'       => $q,
            'status'  => $status,
            'perPage' => $perPage,
            'perPageOptions' => $perPageOptions,
            'periodOptions' => $periodOptions,
            'periodId' => $periodId,
        ]);
    }

    /** Approve selected approval (level 2). */
    public function approve(Request $request, AssessmentApproval $assessment, AssessmentApprovalService $svc): RedirectResponse
    {
        $this->authorizeAccess();
        $assessment->loadMissing('performanceAssessment.assessmentPeriod');
        try {
            $svc->approve($assessment, Auth::user(), (string) $request->input('note'));
            return back()->with('status', 'Penilaian disetujui.');
        } catch (\Throwable $e) {
            return back()->withErrors(['status' => $e->getMessage()]);
        }
    }

    /** Reject selected approval (level 2). */
    public function reject(Request $request, AssessmentApproval $assessment, AssessmentApprovalService $svc): RedirectResponse
    {
        $this->authorizeAccess();
        $request->validate(['note' => ['required','string','max:500']]);
        $assessment->loadMissing('performanceAssessment.assessmentPeriod');
        try {
            $svc->reject($assessment, Auth::user(), (string) $request->input('note'));
            return back()->with('status', 'Penilaian ditolak.');
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

        return view('shared.assessment_approval_detail', [
            'approval' => $assessment,
            'pa' => $pa,
            'breakdown' => $breakdown,
            'backUrl' => route('kepala_unit.assessments.pending'),
        ]);
    }

    private function authorizeAccess(): void
    {
        $user = Auth::user();
        if (!$user || $user->role !== 'kepala_unit') abort(403);
    }

    private function applyStatusFilter($builder, string $status)
    {
        $pending  = AssessmentApprovalStatus::PENDING->value;
        $approved = AssessmentApprovalStatus::APPROVED->value;
        $rejected = AssessmentApprovalStatus::REJECTED->value;
        switch ($status) {
            case 'pending_l2':
                $builder->where('aa.level', 2)
                    ->where('aa.status', $pending)
                    ->whereExists(function ($sub) {
                        $sub->select(DB::raw(1))
                            ->from('assessment_approvals as aa1')
                            ->whereColumn('aa1.performance_assessment_id', 'aa.performance_assessment_id')
                            ->whereColumn('aa1.attempt', 'aa.attempt')
                            ->whereNull('aa1.invalidated_at')
                            ->where('aa1.level', 1)
                            ->where('aa1.status', AssessmentApprovalStatus::APPROVED->value);
                    });
                break;
            case 'approved_l2':
                $builder->where('aa.level', 2)->where('aa.status', $approved);
                break;
            case 'rejected_l2':
                $builder->where('aa.level', 2)->where('aa.status', $rejected);
                break;
            case 'pending_all':
                $builder->where('aa.status', $pending);
                break;
            case 'approved_all':
                $builder->where('aa.status', $approved);
                break;
            case 'rejected_all':
                $builder->where('aa.status', $rejected);
                break;
            default:
                // '' => tampilkan semua level tanpa filter tambahan
                break;
        }

        return $builder;
    }
}
