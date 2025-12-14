<?php

namespace App\Http\Controllers\Web\PolyclinicHead;

use App\Enums\AssessmentApprovalStatus as AStatus;
use App\Http\Controllers\Controller;
use App\Models\AssessmentApproval;
use App\Services\AssessmentApprovalFlow;
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
        $status = (string) $request->input('status', 'pending_l3');

        $periodOptions = Schema::hasTable('assessment_periods')
            ? DB::table('assessment_periods')->orderByDesc('start_date')->pluck('name', 'id')->prepend('(Semua)', '')
            : collect(['' => '(Semua)']);
        $activePeriodId = Schema::hasTable('assessment_periods')
            ? DB::table('assessment_periods')->where('status', 'active')->value('id')
            : null;
        // Default requirement: tampilkan semua periode (no filter) sampai user memilih
        $periodId = $periodFilterRequested
            ? ($data['period_id'] ?? null)
            : null;

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
                ->selectRaw('aa.id, aa.status, aa.level, aa.note, aa.created_at, aa.acted_at, u.name as user_name, ap.name as period_name, pa.total_wsm_score')
                ->orderByDesc('aa.id');

            // Scope to units under Poliklinik Head
            if ($scopeUnitIds->isNotEmpty()) {
                $builder->whereIn('u.unit_id', $scopeUnitIds);
            }
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
            'periodOptions' => $periodOptions,
            'periodId' => $periodId,
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
        // Enum-safe comparisons (status is cast to enum in model)
        if ($assessment->status === AStatus::APPROVED) {
            return back()->with('status', 'Penilaian sudah disetujui.');
        }
        if ($assessment->status !== AStatus::PENDING) {
            return back()->withErrors(['status' => 'Status saat ini tidak dapat disetujui.']);
        }
        // Require Level 2 approval before Level 3 can approve
        $hasLvl2Approved = DB::table('assessment_approvals')
            ->where('performance_assessment_id', $assessment->performance_assessment_id)
            ->where('level', 2)
            ->where('status', AStatus::APPROVED->value)
            ->exists();
        if (!$hasLvl2Approved) {
            return back()->withErrors(['status' => 'Belum dapat menyetujui: menunggu persetujuan Level 2.']);
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
        if ($assessment->status !== AStatus::PENDING) {
            return back()->withErrors(['status' => 'Tidak dapat menolak karena status sudah ' . $assessment->status->value . '.']);
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
        if (!$user || $user->role !== 'kepala_poliklinik') abort(403);
    }

    private function applyStatusFilter($builder, string $status)
    {
        switch ($status) {
            case 'pending_l3':
                $builder->where('aa.level', 3)
                    ->where('aa.status', 'pending')
                    ->whereExists(function ($sub) {
                        $sub->select(DB::raw(1))
                            ->from('assessment_approvals as aa2')
                            ->whereColumn('aa2.performance_assessment_id', 'aa.performance_assessment_id')
                            ->where('aa2.level', 2)
                            ->where('aa2.status', 'approved');
                    });
                break;
            case 'approved_l3':
                $builder->where('aa.level', 3)->where('aa.status', 'approved');
                break;
            case 'rejected_l3':
                $builder->where('aa.level', 3)->where('aa.status', 'rejected');
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
            default:
                // '' => tampilkan semua level
                break;
        }

        return $builder;
    }
}
