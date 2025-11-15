<?php

namespace App\Http\Controllers\Web\PolyclinicHead;

use App\Http\Controllers\Controller;
use App\Models\UnitCriteriaWeight as Weight;
use App\Enums\UnitCriteriaWeightStatus as UCWStatus;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
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

        $query = Weight::with(['unit:id,name','performanceCriteria:id,name,type'])
            ->when($scopeUnitIds->isNotEmpty(), fn($w) => $w->whereIn('unit_id', $scopeUnitIds));

        if ($filters['q'] !== '') {
            $q = $filters['q'];
            $query->where(function($w) use ($q) {
                $w->whereHas('unit', fn($u)=>$u->where('name','like',"%$q%"))
                  ->orWhereHas('performanceCriteria', fn($pc)=>$pc->where('name','like',"%$q%"));
            });
        }
        if (!empty($filters['status']) && $filters['status'] !== 'all') {
            $query->where('status', $filters['status']);
        }

        $items = $query->orderByDesc('id')->paginate(20)->withQueryString();

        return view('kepala_poli.unit_criteria_weights.index', compact('items','filters'));
    }

    public function approve(Request $request, Weight $weight)
    {
        // IU: Sesuai kebutuhan saat ini, Kepala Poliklinik dapat menyetujui semua unit
        $me = Auth::user();

    $weight->status = UCWStatus::ACTIVE;
        $weight->polyclinic_head_id = $me->id;
        $weight->save();
        return back()->with('status','Bobot kriteria disetujui.');
    }

    public function reject(Request $request, Weight $weight)
    {
        $data = $request->validate(['reason' => ['required','string','max:255']]);

        $me = Auth::user();

    $weight->status = UCWStatus::REJECTED;
        $weight->polyclinic_head_id = $me->id;
        // Simpan alasan di policy_note agar tercatat
        $weight->policy_note = trim('Rejected: '.$data['reason']);
        $weight->save();
        return back()->with('status','Bobot kriteria ditolak.');
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
            $periods = DB::table('assessment_periods')->orderByDesc(DB::raw("status='active'"))->orderByDesc('id')->get();
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
