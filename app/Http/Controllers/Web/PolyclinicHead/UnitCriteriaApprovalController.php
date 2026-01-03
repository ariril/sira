<?php

namespace App\Http\Controllers\Web\PolyclinicHead;

use App\Http\Controllers\Controller;
use App\Models\UnitCriteriaWeight as Weight;
use App\Enums\UnitCriteriaWeightStatus as UCWStatus;
use App\Models\AssessmentPeriod;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class UnitCriteriaApprovalController extends Controller
{
    public function index(Request $request): View
    {
        $me = Auth::user();

        // Scope unit untuk Kepala Poliklinik: unit anak dari unit-nya, atau fallback semua unit bertipe poliklinik
        $scopeUnitIds = collect();
        if (Schema::hasTable('units')) {
            if ($me->unit_id) {
                $scopeUnitIds = DB::table('units')->where('parent_id', $me->unit_id)->pluck('id');
            }
            if ($scopeUnitIds->isEmpty()) {
                $scopeUnitIds = DB::table('units')->where('type','poliklinik')->pluck('id');
            }
        }

        $filters = [
            'q'      => trim((string)$request->get('q','')),
            'status' => $request->get('status','pending'),
        ];

        $baseQuery = Weight::query()
            ->when($scopeUnitIds->isNotEmpty(), fn($w) => $w->whereIn('unit_id', $scopeUnitIds));

        if ($filters['q'] !== '') {
            $q = $filters['q'];
            $baseQuery->where(function($w) use ($q) {
                $w->whereHas('unit', fn($u)=>$u->where('name','like',"%$q%"))
                  ->orWhereHas('performanceCriteria', fn($pc)=>$pc->where('name','like',"%$q%"));
            });
        }

        $filteredQuery = clone $baseQuery;
        if (!empty($filters['status']) && $filters['status'] !== 'all') {
            $filteredQuery->where('status', $filters['status']);
        }

        // Paginate by Unit to enable collapsible per-unit sections.
        // Important: do NOT mutate $filteredQuery's select(), otherwise subsequent queries
        // will only fetch unit_id and the UI will show blank criteria/weight/status.
        $unitIdsSub = (clone $filteredQuery)->select('unit_id')->distinct()->toBase();
        $units = DB::table('units as u')
            ->joinSub($unitIdsSub, 'w', fn($j) => $j->on('w.unit_id', '=', 'u.id'))
            ->select('u.id', 'u.name')
            ->orderBy('u.name')
            ->paginate(10)
            ->withQueryString();

        $pageUnitIds = collect($units->items())->pluck('id')->all();

        $rows = collect();
        if (!empty($pageUnitIds)) {
            $rows = (clone $filteredQuery)
                ->with(['performanceCriteria:id,name,type'])
                ->whereIn('unit_id', $pageUnitIds)
                ->orderBy('unit_id')
                ->orderBy('performance_criteria_id')
                ->get();
        }

        $itemsByUnit = $rows->groupBy('unit_id');

        $pendingByUnit = collect();
        if (!empty($pageUnitIds)) {
            $pendingByUnit = (clone $baseQuery)
                ->whereIn('unit_id', $pageUnitIds)
                ->where('status', UCWStatus::PENDING)
                ->selectRaw('unit_id, COUNT(*) as c')
                ->groupBy('unit_id')
                ->pluck('c', 'unit_id');
        }

        return view('kepala_poli.unit_criteria_weights.index', [
            'units' => $units,
            'itemsByUnit' => $itemsByUnit,
            'pendingByUnit' => $pendingByUnit,
            'filters' => $filters,
        ]);
    }

    public function approveUnit(Request $request, int $unitId): RedirectResponse
    {
        $me = Auth::user();

        $data = $request->validate([
            'q' => ['nullable','string','max:100'],
        ]);

        // Scope check
        $scopeUnitIds = collect();
        if (Schema::hasTable('units')) {
            if ($me->unit_id) {
                $scopeUnitIds = DB::table('units')->where('parent_id', $me->unit_id)->pluck('id');
            }
            if ($scopeUnitIds->isEmpty()) {
                $scopeUnitIds = DB::table('units')->where('type','poliklinik')->pluck('id');
            }
        }
        if ($scopeUnitIds->isNotEmpty() && !$scopeUnitIds->contains($unitId)) {
            abort(403);
        }

        $q = trim((string)($data['q'] ?? ''));

        $pendingQuery = Weight::query()
            ->where('unit_id', $unitId)
            ->where('status', UCWStatus::PENDING)
            ->when($q !== '', fn($w) => $w->whereHas('performanceCriteria', fn($pc)=>$pc->where('name','like',"%$q%")));

        $count = (clone $pendingQuery)->count();
        if ($count === 0) {
            return back()->with('status', 'Tidak ada bobot pending untuk disetujui pada unit ini.');
        }

        DB::transaction(function () use ($pendingQuery, $me) {
            $pendingQuery->update([
                'status' => UCWStatus::ACTIVE,
                'decided_by' => $me->id,
                'decided_at' => now(),
                'decided_note' => null,
            ]);
        });

        return back()->with('status', $count.' bobot pending disetujui untuk unit ini.');
    }

    public function rejectUnit(Request $request, int $unitId): RedirectResponse
    {
        $me = Auth::user();

        $data = $request->validate([
            'comment' => ['required','string','max:1000'],
            'q' => ['nullable','string','max:100'],
        ]);

        $comment = trim((string)($data['comment'] ?? ''));
        if ($comment === '') {
            return back()->withErrors(['comment' => 'Catatan penolakan wajib diisi.']);
        }

        // Scope check
        $scopeUnitIds = collect();
        if (Schema::hasTable('units')) {
            if ($me->unit_id) {
                $scopeUnitIds = DB::table('units')->where('parent_id', $me->unit_id)->pluck('id');
            }
            if ($scopeUnitIds->isEmpty()) {
                $scopeUnitIds = DB::table('units')->where('type','poliklinik')->pluck('id');
            }
        }
        if ($scopeUnitIds->isNotEmpty() && !$scopeUnitIds->contains($unitId)) {
            abort(403);
        }

        $q = trim((string)($data['q'] ?? ''));

        $pendingQuery = Weight::query()
            ->where('unit_id', $unitId)
            ->where('status', UCWStatus::PENDING)
            ->when($q !== '', fn($w) => $w->whereHas('performanceCriteria', fn($pc)=>$pc->where('name','like',"%$q%")));

        $count = (clone $pendingQuery)->count();
        if ($count === 0) {
            return back()->with('status', 'Tidak ada bobot pending untuk ditolak pada unit ini.');
        }

        $policyNote = trim('Rejected: '.$comment);

        DB::transaction(function () use ($pendingQuery, $me, $comment, $policyNote) {
            $pendingQuery->update([
                'status' => UCWStatus::REJECTED,
                'decided_by' => $me->id,
                'decided_at' => now(),
                'decided_note' => $comment,
                'policy_note' => $policyNote,
            ]);
        });

        return back()->with('status', $count.' bobot pending ditolak untuk unit ini.');
    }

    public function approve(Request $request, Weight $weight)
    {
        // IU: Sesuai kebutuhan saat ini, Kepala Poliklinik dapat menyetujui semua unit
        $me = Auth::user();

        $weight->status = UCWStatus::ACTIVE;
        $weight->decided_by = $me->id;
        $weight->decided_at = now();
        $weight->decided_note = null;
        $weight->save();
        return back()->with('status','Bobot kriteria disetujui.');
    }

    public function reject(Request $request, Weight $weight)
    {
        // Accept both legacy "reason" and new "comment" payload.
        $data = $request->validate([
            'comment' => ['nullable','string','max:1000'],
            'reason' => ['nullable','string','max:255'],
        ]);

        $comment = trim((string) ($data['comment'] ?? $data['reason'] ?? ''));
        if ($comment === '') {
            return back()->withErrors(['comment' => 'Komentar penolakan wajib diisi.']);
        }

        $me = Auth::user();

        $weight->status = UCWStatus::REJECTED;
        $weight->decided_by = $me->id;
        $weight->decided_at = now();
        $weight->decided_note = $comment;
        // Legacy: keep writing to policy_note so existing UI/exports don't break.
        $weight->policy_note = trim('Rejected: '.$comment);
        $weight->save();
        return back()->with('status','Bobot kriteria ditolak.');
    }

    public function approveAll(Request $request): RedirectResponse
    {
        $me = Auth::user();

        $scopeUnitIds = collect();
        if (Schema::hasTable('units')) {
            if ($me->unit_id) {
                $scopeUnitIds = DB::table('units')->where('parent_id', $me->unit_id)->pluck('id');
            }
            if ($scopeUnitIds->isEmpty()) {
                $scopeUnitIds = DB::table('units')->where('type','poliklinik')->pluck('id');
            }
        }

        $q = trim((string) $request->get('q',''));

        $pendingQuery = Weight::query()
            ->where('status', UCWStatus::PENDING)
            ->when($scopeUnitIds->isNotEmpty(), fn($w) => $w->whereIn('unit_id', $scopeUnitIds))
            ->when($q !== '', function ($w) use ($q) {
                $w->where(function ($inner) use ($q) {
                    $inner->whereHas('unit', fn($u) => $u->where('name', 'like', "%$q%"))
                        ->orWhereHas('performanceCriteria', fn($pc) => $pc->where('name', 'like', "%$q%"));
                });
            });

        $count = (clone $pendingQuery)->count();

        if ($count === 0) {
            return back()->with('status', 'Tidak ada bobot pending untuk disetujui.');
        }

        DB::transaction(function () use ($pendingQuery, $me) {
            $pendingQuery->update([
                'status' => UCWStatus::ACTIVE,
                'decided_by' => $me->id,
                'decided_at' => now(),
                'decided_note' => null,
            ]);
        });

        return back()->with('status', $count . ' bobot pending disetujui.');
    }

    /**
     * List all units (read-only view) and provide link to detail.
     */
    public function units(Request $request): View
    {
        $q = trim((string)$request->get('q',''));
        $units = collect();
        if (Schema::hasTable('units')) {
            $units = DB::table('units as u')
                ->where('u.type','poliklinik')
                ->when($q !== '', fn($w)=>$w->where('u.name','like',"%$q%"))
                ->select('u.id','u.name','u.code','u.type')
                ->orderBy('u.name')
                ->paginate(20)
                ->withQueryString();
        }
        return view('kepala_poli.unit_criteria_weights.units', compact('units','q'));
    }

    /** Read-only detail per unit */
    public function unit(Request $request, int $unitId): View
    {
        $filters = $request->validate([
            'status' => ['nullable','in:draft,pending,active,rejected,all'],
            'period_id' => ['nullable','integer'],
        ]);
        $unit = Schema::hasTable('units') ? DB::table('units')->where('id',$unitId)->first() : null;
        if (!$unit || (string)$unit->type !== 'poliklinik') {
            abort(404);
        }
        $periods = collect();
        if (Schema::hasTable('assessment_periods')) {
              $periods = DB::table('assessment_periods')->orderByDesc(DB::raw("status='" . AssessmentPeriod::STATUS_ACTIVE . "'"))->orderByDesc('id')->get();
        }
        $rows = collect();
        if (Schema::hasTable('unit_criteria_weights')) {
            $q = DB::table('unit_criteria_weights as w')
                ->join('performance_criterias as pc','pc.id','=','w.performance_criteria_id')
                ->leftJoin('assessment_periods as ap','ap.id','=','w.assessment_period_id')
                ->selectRaw('w.id, pc.name as criteria_name, pc.type as criteria_type, w.weight, w.status, ap.name as period_name, w.assessment_period_id')
                ->where('w.unit_id', $unitId)
                ->orderBy('pc.name');
            if (!empty($filters['period_id'])) $q->where('w.assessment_period_id', (int)$filters['period_id']);
            if (!empty($filters['status']) && $filters['status'] !== 'all') $q->where('w.status',$filters['status']);
            $rows = $q->paginate(50)->withQueryString();
        }
        return view('kepala_poli.unit_criteria_weights.show', [
            'unit' => $unit,
            'items' => $rows,
            'periods' => $periods,
            'filters' => [
                'status' => $filters['status'] ?? 'all',
                'period_id' => $filters['period_id'] ?? null,
            ],
        ]);
    }
}
