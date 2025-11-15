<?php

namespace App\Http\Controllers\Web\MedicalStaff;

use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Http\Request;
use Illuminate\View\View;

class UnitCriteriaWeightViewController extends Controller
{
    public function index(Request $request): View
    {
        $me = Auth::user();
        $unitId = $me?->unit_id;

        // Find active assessment period
        $activePeriodId = null; $activePeriodName = null;
        if (Schema::hasTable('assessment_periods')) {
            $active = DB::table('assessment_periods')->where('status','active')->first();
            if ($active) { $activePeriodId = $active->id; $activePeriodName = $active->name; }
        }

        $items = collect();
        if ($unitId && Schema::hasTable('unit_criteria_weights')) {
            $q = DB::table('unit_criteria_weights as w')
                ->join('performance_criterias as pc','pc.id','=','w.performance_criteria_id')
                ->selectRaw('pc.name as criteria_name, pc.type as criteria_type, w.weight')
                ->where('w.unit_id', $unitId)
                ->where('w.status','active');
            if ($activePeriodId) $q->where('w.assessment_period_id', $activePeriodId);
            $items = $q->orderBy('pc.name')->get();
        }

        return view('pegawai_medis.unit_criteria_weights.index', [
            'items' => $items,
            'periodName' => $activePeriodName,
        ]);
    }
}
