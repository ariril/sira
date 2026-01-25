<?php

namespace App\Http\Controllers\Web\PolyclinicHead;

use App\Http\Controllers\Controller;
use App\Models\AssessmentPeriod;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Collection;
use Illuminate\View\View;

class UnitRaterWeightMonitorController extends Controller
{
    /**
     * List all units (within scope) that have rater weights.
     */
    public function units(Request $request): View
    {
        $q = trim((string) $request->get('q', ''));
        $units = collect();
        $scopeUnitIds = $this->scopeUnitIds(Auth::user());

        if (Schema::hasTable('units') && Schema::hasTable('unit_rater_weights')) {
            $unitIdsSub = DB::table('unit_rater_weights')
                ->when($scopeUnitIds->isNotEmpty(), fn($w) => $w->whereIn('unit_id', $scopeUnitIds))
                ->select('unit_id')
                ->distinct();

            $units = DB::table('units as u')
                ->joinSub($unitIdsSub, 'rw', fn($j) => $j->on('rw.unit_id', '=', 'u.id'))
                ->when($q !== '', fn($w) => $w->where('u.name', 'like', "%{$q}%"))
                ->select('u.id', 'u.name', 'u.code', 'u.type')
                ->orderBy('u.name')
                ->paginate(20)
                ->withQueryString();
        }

        return view('kepala_poli.unit_rater_weights.units', [
            'units' => $units,
            'q' => $q,
        ]);
    }

    /**
     * Show detail list of rater weights for a unit.
     */
    public function unit(Request $request, int $unitId): View
    {
        $status = (string) $request->get('status', 'all');
        $allowedStatuses = ['draft', 'pending', 'active', 'rejected', 'archived', 'all'];
        if (!in_array($status, $allowedStatuses, true)) {
            $status = 'all';
        }

        $periodId = $request->get('period_id');
        $periodId = is_numeric($periodId) ? (int) $periodId : null;

        $scopeUnitIds = $this->scopeUnitIds(Auth::user());
        if ($scopeUnitIds->isNotEmpty() && !$scopeUnitIds->contains($unitId)) {
            abort(403);
        }

        $unit = Schema::hasTable('units') ? DB::table('units')->where('id', $unitId)->first() : null;
        if (!$unit) {
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
        if (Schema::hasTable('unit_rater_weights')) {
            $rowsQ = DB::table('unit_rater_weights as rw')
                ->leftJoin('performance_criterias as pc', 'pc.id', '=', 'rw.performance_criteria_id')
                ->leftJoin('assessment_periods as ap', 'ap.id', '=', 'rw.assessment_period_id')
                ->leftJoin('professions as prof', 'prof.id', '=', 'rw.assessee_profession_id')
                ->leftJoin('professions as apf', 'apf.id', '=', 'rw.assessor_profession_id')
                ->selectRaw('rw.id, rw.status, rw.weight, rw.assessor_type, rw.assessor_level, rw.assessee_profession_id, rw.assessor_profession_id, pc.name as criteria_name, ap.name as period_name, prof.name as assessee_profession_name, apf.name as assessor_profession_name, rw.assessment_period_id')
                ->where('rw.unit_id', $unitId)
                ->orderBy('pc.name')
                ->orderBy('rw.assessee_profession_id')
                ->orderBy('rw.assessor_type')
                ->orderBy('rw.assessor_level');

            if (!empty($periodId)) {
                $rowsQ->where('rw.assessment_period_id', $periodId);
            }
            if ($status !== 'all') {
                $rowsQ->where('rw.status', $status);
            }
            $rows = $rowsQ->paginate(50)->withQueryString();
        }

        return view('kepala_poli.unit_rater_weights.show', [
            'unit' => $unit,
            'items' => $rows,
            'periods' => $periods,
            'filters' => [
                'status' => $status,
                'period_id' => $periodId,
            ],
        ]);
    }

    /** @return Collection<int, int> */
    private function scopeUnitIds($user): Collection
    {
        $scopeUnitIds = collect();
        if (Schema::hasTable('units')) {
            if ($user?->unit_id) {
                $scopeUnitIds = DB::table('units')->where('parent_id', $user->unit_id)->pluck('id');
            }
            if ($scopeUnitIds->isEmpty()) {
                $scopeUnitIds = DB::table('units')->where('type', 'poliklinik')->pluck('id');
            }
        }

        return $scopeUnitIds;
    }
}
