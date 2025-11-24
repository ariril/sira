<?php

namespace App\Http\Controllers\Web\AdminHospital;

use App\Http\Controllers\Controller;
use App\Models\AssessmentPeriod;
use App\Models\Remuneration;
use App\Models\PerformanceAssessment;
use App\Models\PerformanceAssessmentDetail;
use App\Models\PerformanceCriteria;
use App\Models\UnitCriteriaWeight;
use App\Enums\PerformanceCriteriaType;
use App\Models\UnitRemunerationAllocation as Allocation;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class RemunerationController extends Controller
{
    /**
     * Purpose: Calculation workspace. Pick a period, run calculation,
     * and review summary of remunerations generated for that period.
     */
    public function calcIndex(Request $request): View
    {
        $periodId = (int) $request->integer('period_id');
        $periods  = AssessmentPeriod::orderByDesc('start_date')->get(['id','name','start_date']);

        $summary = null;
        $allocSummary = null;
        $remunerations = collect();
        if ($periodId) {
            $remunerations = Remuneration::with('user:id,name,unit_id')
                ->where('assessment_period_id', $periodId)
                ->orderBy('user_id')
                ->get();
            $summary = [
                'count' => $remunerations->count(),
                'total' => (float) $remunerations->sum('amount'),
            ];
            $allocs = Allocation::where('assessment_period_id', $periodId)->whereNotNull('published_at')->get(['id','amount']);
            $allocSummary = [
                'count' => $allocs->count(),
                'total' => (float) $allocs->sum('amount'),
            ];
        }

        return view('admin_rs.remunerations.calc_index', [
            'periods'       => $periods,
            'selectedId'    => $periodId ?: null,
            'remunerations' => $remunerations,
            'summary'       => $summary,
            'allocSummary'  => $allocSummary,
        ]);
    }

    /**
     * Run remuneration calculation for a given period.
     * Strategy: distribute each published unit allocation equally to
     * pegawai_medis in that unit. Upsert into remunerations table.
     */
    public function runCalculation(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'period_id' => ['required','integer','exists:assessment_periods,id'],
        ]);
        $periodId = (int) $data['period_id'];

        $allocations = Allocation::with('unit:id,name')
            ->where('assessment_period_id', $periodId)
            ->whereNotNull('published_at')
            ->get();

        // Build totals per user
        $totals = [];
        foreach ($allocations as $alloc) {
            $users = User::query()->where('unit_id', $alloc->unit_id)
                ->role(User::ROLE_PEGAWAI_MEDIS)
                ->get(['id']);
            $count = $users->count();
            if ($count <= 0) continue;
            $share = ((float)$alloc->amount) / $count;
            foreach ($users as $u) {
                $totals[$u->id] = ($totals[$u->id] ?? 0) + $share;
            }
        }

        DB::transaction(function () use ($totals, $periodId) {
            foreach ($totals as $userId => $amount) {
                $rem = Remuneration::firstOrNew([
                    'user_id' => $userId,
                    'assessment_period_id' => $periodId,
                ]);
                // Preserve published_at if already published
                $publishedAt = $rem->published_at;
                $rem->amount = round((float)$amount, 2);
                $rem->calculated_at = now();
                $rem->calculation_details = [
                    'method'     => 'equal_share_by_published_unit_allocation',
                    'period_id'  => $periodId,
                    'generated'  => now()->toDateTimeString(),
                ];
                if ($publishedAt) $rem->published_at = $publishedAt;
                $rem->save();
            }
        });

        return redirect()->route('admin_rs.remunerations.calc.index', ['period_id' => $periodId])
            ->with('status', 'Perhitungan selesai.');
    }

    /** Mark a remuneration as published */
    public function publish(Remuneration $remuneration): RedirectResponse
    {
        $remuneration->update(['published_at' => now()]);
        return back()->with('status','Remunerasi dipublish.');
    }
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request): View
    {
        $periodId = (int) $request->integer('period_id');
        $periods  = AssessmentPeriod::orderByDesc('start_date')->get(['id','name']);
        $items = Remuneration::with('user:id,name')
            ->when($periodId, fn($w) => $w->where('assessment_period_id', $periodId))
            ->orderByDesc('id')
            ->paginate(12)
            ->withQueryString();
        return view('admin_rs.remunerations.index', [
            'items'    => $items,
            'periods'  => $periods,
            'periodId' => $periodId ?: null,
        ]);
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        //
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        //
    }

    /**
     * Display the specified resource.
     */
    public function show(Remuneration $remuneration): View
    {
        $remuneration->load(['user:id,name,unit_id','assessmentPeriod:id,name']);
        $wsm = $this->buildWsmBreakdown($remuneration);
        return view('admin_rs.remunerations.show', [
            'item' => $remuneration,
            'wsm'  => $wsm,
        ]);
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(string $id)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Remuneration $remuneration): RedirectResponse
    {
        $data = $request->validate([
            'payment_date'   => ['nullable','date'],
            'payment_status' => ['nullable','string','max:50'],
        ]);
        $remuneration->update($data);
        return back()->with('status','Remunerasi diperbarui.');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id) {}

    /**
     * Build WSM breakdown for a user's assessment in the same period as the remuneration.
     * Normalization:
     *  - benefit: score / max(score)
     *  - cost:    min(score) / score
     * Weights are taken from UnitCriteriaWeight with status ACTIVE for the user's unit and period.
     * Returns null if data not available.
     */
    private function buildWsmBreakdown(Remuneration $rem): ?array
    {
        $userId = $rem->user_id;
        $periodId = $rem->assessment_period_id;
        $unitId = $rem->user->unit_id ?? null;
        if (!$unitId) return null;

        $assessment = PerformanceAssessment::with(['details.performanceCriteria:id,name,type'])
            ->where('user_id', $userId)
            ->where('assessment_period_id', $periodId)
            ->first();
        if (!$assessment) return null;

        $detailByCrit = [];
        $criteriaIds = [];
        foreach ($assessment->details as $d) {
            $cid = $d->performance_criteria_id;
            $criteriaIds[] = $cid;
            $detailByCrit[$cid] = [
                'score'    => (float) $d->score,
                'criteria' => $d->performanceCriteria,
            ];
        }
        if (!$criteriaIds) return null;

        // Fetch ACTIVE weights for this unit & period
        $weights = UnitCriteriaWeight::query()
            ->where('unit_id', $unitId)
            ->whereIn('performance_criteria_id', $criteriaIds)
            ->where('assessment_period_id', $periodId)
            ->where('status', 'active')
            ->get(['performance_criteria_id','weight'])
            ->keyBy('performance_criteria_id');

        // Compute min/max per criteria in this period for normalization
        $minMax = PerformanceAssessmentDetail::query()
            ->selectRaw('performance_criteria_id, MIN(score) as min_score, MAX(score) as max_score')
            ->whereIn('performance_criteria_id', $criteriaIds)
            ->whereHas('performanceAssessment', function ($q) use ($periodId) {
                $q->where('assessment_period_id', $periodId);
            })
            ->groupBy('performance_criteria_id')
            ->get()
            ->keyBy('performance_criteria_id');

        $rows = [];
        $total = 0.0;
        foreach ($criteriaIds as $cid) {
            $crit = $detailByCrit[$cid]['criteria'];
            $score = $detailByCrit[$cid]['score'];
            $w    = (float) ($weights[$cid]->weight ?? 0); // percent
            $mm   = $minMax[$cid] ?? null;
            $min  = $mm ? (float)$mm->min_score : 0.0;
            $max  = $mm ? (float)$mm->max_score : 0.0;
            $norm = 0.0;
            if ($crit && $crit->type === PerformanceCriteriaType::BENEFIT) {
                $norm = $max > 0 ? ($score / $max) : 0.0;
            } else {
                $norm = ($score > 0 && $min > 0) ? ($min / $score) : 0.0;
            }
            if ($norm < 0) $norm = 0.0; if ($norm > 1) $norm = 1.0;
            $contrib = $norm * ($w / 100.0);
            $total += $contrib;
            $rows[] = [
                'criteria_name' => $crit?->name ?? ('Kriteria #'.$cid),
                'type'          => $crit?->type?->value ?? '-',
                'weight'        => $w,
                'score'         => $score,
                'min'           => $min,
                'max'           => $max,
                'normalized'    => round($norm, 4),
                'contribution'  => round($contrib, 4),
            ];
        }

        return [
            'rows'  => $rows,
            'total' => round($total, 4),
        ];
    }
}
