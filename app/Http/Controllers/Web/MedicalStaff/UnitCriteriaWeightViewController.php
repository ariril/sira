<?php

namespace App\Http\Controllers\Web\MedicalStaff;

use App\Http\Controllers\Controller;
use App\Services\RaterWeights\RaterWeightSummaryService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use App\Models\AssessmentPeriod;
use Illuminate\Support\Facades\Schema;
use Illuminate\Http\Request;
use Illuminate\View\View;
use App\Support\AssessmentPeriodGuard;

class UnitCriteriaWeightViewController extends Controller
{
    public function __construct(
        private readonly RaterWeightSummaryService $raterWeightSummaryService,
    ) {
    }

    public function index(Request $request): View
    {
        $me = Auth::user();
        $unitId = $me?->unit_id;
        $professionId = (int) ($me?->profession_id ?? 0);

        // Find active assessment period
        $activePeriodId = null; $activePeriodName = null;
        $active = AssessmentPeriodGuard::resolveActive();
        if ($active) {
            $activePeriodId = $active->id;
            $activePeriodName = $active->name;
        }

        $items = collect();
        // IMPORTANT: only show weights when an active period exists AND weights are ACTIVE (approved).
        // Otherwise the page should be empty (matches UI expectation).
        if ($unitId && $activePeriodId && Schema::hasTable('unit_criteria_weights')) {
            $items = DB::table('unit_criteria_weights as w')
                ->join('performance_criterias as pc', 'pc.id', '=', 'w.performance_criteria_id')
                ->where('w.unit_id', $unitId)
                ->where('w.assessment_period_id', $activePeriodId)
                ->where('w.status', 'active')
                // Defensive: if duplicates exist in seed/legacy data, collapse to 1 row per criteria.
                ->groupBy('pc.id', 'pc.name', 'pc.type')
                ->selectRaw('pc.name as criteria_name, pc.type as criteria_type, MAX(w.weight) as weight')
                ->orderBy('pc.name')
                ->get();
        }

        // 360 rater weights summary for the staff's own profession (assessee_profession_id)
        $rater360 = collect();
        if ($unitId && $activePeriodId && $professionId > 0 && Schema::hasTable('unit_rater_weights') && Schema::hasTable('unit_criteria_weights') && Schema::hasTable('performance_criterias')) {
            $criteria360Ids = DB::table('unit_criteria_weights as ucw')
                ->join('performance_criterias as pc', 'pc.id', '=', 'ucw.performance_criteria_id')
                ->where('ucw.unit_id', $unitId)
                ->where('ucw.assessment_period_id', $activePeriodId)
                ->where('ucw.status', 'active')
                ->where('pc.is_360', 1)
                ->pluck('pc.id')
                ->map(fn ($v) => (int) $v)
                ->filter(fn ($v) => $v > 0)
                ->values()
                ->all();

            if (!empty($criteria360Ids)) {
                $rater360 = $this->raterWeightSummaryService->summarizeForUnitPeriod(
                    unitId: (int) $unitId,
                    periodId: (int) $activePeriodId,
                    criteriaIds: $criteria360Ids,
                    criteriaFilterId: null,
                    professionFilterId: $professionId,
                    statuses: ['active'],
                    weightOverrides: [],
                );
            }
        }

        return view('pegawai_medis.unit_criteria_weights.index', [
            'items' => $items,
            'periodName' => $activePeriodName,
            'rater360' => $rater360,
        ]);
    }
}
