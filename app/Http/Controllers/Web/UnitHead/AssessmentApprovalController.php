<?php

namespace App\Http\Controllers\Web\UnitHead;

use App\Enums\AssessmentApprovalStatus;
use App\Http\Controllers\Controller;
use App\Models\AssessmentApproval;
use App\Services\AssessmentApprovalFlow;
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
                ->selectRaw("aa.id, aa.status, aa.level, aa.note, aa.created_at, aa.acted_at, u.name as user_name, ap.name as period_name, pa.total_wsm_score,
                              EXISTS(SELECT 1 FROM assessment_approvals aa1 WHERE aa1.performance_assessment_id = aa.performance_assessment_id AND aa1.level = 1 AND aa1.status = 'approved') as has_lvl1_approved")
                ->orderByDesc('aa.id');

            // Scope to Kepala Unit's unit
            if ($unitId) {
                $builder->where('u.unit_id', $unitId);
            }
            $builder = $this->applyStatusFilter($builder, $status);
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
    public function approve(Request $request, AssessmentApproval $assessment): RedirectResponse
    {
        $this->authorizeAccess();
        if ((int) ($assessment->level ?? 0) !== 2) abort(403);
        // Require Level 1 to be approved before Level 2 can approve
        $hasLvl1Approved = AssessmentApproval::query()
            ->where('performance_assessment_id', $assessment->performance_assessment_id)
            ->where('level', 1)
            ->where('status', AssessmentApprovalStatus::APPROVED->value)
            ->exists();
        if (!$hasLvl1Approved) {
            return back()->withErrors(['status' => 'Belum dapat menyetujui: menunggu persetujuan Level 1.']);
        }
        // Dengan cast enum, $assessment->status adalah instance enum.
        if ($assessment->status === AssessmentApprovalStatus::APPROVED) {
            return back()->with('status', 'Penilaian sudah disetujui.');
        }
        if ($assessment->status !== AssessmentApprovalStatus::PENDING) {
            return back()->withErrors(['status' => 'Status saat ini tidak dapat disetujui.']);
        }
        $assessment->update([
            'status'   => AssessmentApprovalStatus::APPROVED->value,
            'note'     => (string) $request->input('note'),
            'acted_at' => now(),
        ]);
        AssessmentApprovalFlow::ensureNextLevel($assessment, Auth::id());
        return back()->with('status', 'Penilaian disetujui.');
    }

    /** Reject selected approval (level 2). */
    public function reject(Request $request, AssessmentApproval $assessment): RedirectResponse
    {
        $this->authorizeAccess();
        if ((int) ($assessment->level ?? 0) !== 2) abort(403);
        $request->validate(['note' => ['required','string','max:500']]);
        if ($assessment->status !== AssessmentApprovalStatus::PENDING) {
            return back()->withErrors(['status' => 'Tidak dapat menolak karena status sudah ' . $assessment->status . '.']);
        }
        $hasLvl3Approved = DB::table('assessment_approvals')
            ->where('performance_assessment_id', $assessment->performance_assessment_id)
            ->where('level', 3)
            ->where('status', AssessmentApprovalStatus::APPROVED->value)
            ->exists();
        if ($hasLvl3Approved) {
            return back()->withErrors(['status' => 'Tidak dapat menolak, sudah disetujui pada level 3.']);
        }
        $assessment->update([
            'status'   => AssessmentApprovalStatus::REJECTED->value,
            'note'     => (string) $request->input('note'),
            'acted_at' => now(),
        ]);
        AssessmentApprovalFlow::removeFutureLevels($assessment);

        return back()->with('status', 'Penilaian ditolak.');
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
