<?php

namespace App\Http\Controllers\Web\AdminHospital;

use App\Http\Controllers\Controller;
use App\Models\AssessmentPeriod;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Http\Request;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class UnitCriteriaWeightController extends Controller
{
    /**
     * List all units with quick access to detail of criteria weights.
     */
    public function index(Request $request): View
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
        return view('admin_rs.unit_criteria_weights.index', [
            'units' => $units,
            'q' => $q,
        ]);
    }

    public function create(): View { return $this->index(request()); }
    public function store(Request $request): RedirectResponse { return back()->with('status','Belum diimplementasikan'); }

    /**
     * Show detail table of criteria and weights for a unit.
     * For simplicity, the {id} parameter is treated as unit_id.
     */
    public function show(string $id, Request $request): View
    {
        $unitId = (int)$id;
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
            $periods = DB::table('assessment_periods')
                ->orderByDesc(DB::raw("status='" . AssessmentPeriod::STATUS_ACTIVE . "'"))
                ->orderByDesc('id')
                ->get();
        }

        $rows = collect();
        if (Schema::hasTable('unit_criteria_weights')) {
            $rowsQ = DB::table('unit_criteria_weights as w')
                ->join('performance_criterias as pc','pc.id','=','w.performance_criteria_id')
                ->leftJoin('assessment_periods as ap','ap.id','=','w.assessment_period_id')
                ->selectRaw('w.id, pc.name as criteria_name, pc.type as criteria_type, w.weight, w.status, ap.name as period_name, w.assessment_period_id')
                ->where('w.unit_id', $unitId)
                ->orderBy('pc.name');
            if (!empty($filters['period_id'])) $rowsQ->where('w.assessment_period_id', (int)$filters['period_id']);
            if (!empty($filters['status']) && $filters['status'] !== 'all') $rowsQ->where('w.status',$filters['status']);
            $rows = $rowsQ->paginate(50)->withQueryString();
        }

        return view('admin_rs.unit_criteria_weights.show', [
            'unit' => $unit,
            'items' => $rows,
            'periods' => $periods,
            'filters' => [
                'status' => $filters['status'] ?? 'all',
                'period_id' => $filters['period_id'] ?? null,
            ],
        ]);
    }

    public function edit(string $id): View { return $this->show($id, request()); }
    public function update(Request $request, string $id): RedirectResponse { return back()->with('status','Belum diimplementasikan'); }
    public function destroy(string $id): RedirectResponse { return back()->with('status','Belum diimplementasikan'); }

    public function publishDraft(): RedirectResponse { return back()->with('status','Belum diimplementasikan'); }
}
