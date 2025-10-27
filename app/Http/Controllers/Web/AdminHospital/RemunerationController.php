<?php

namespace App\Http\Controllers\Web\AdminHospital;

use App\Http\Controllers\Controller;
use App\Models\AssessmentPeriod;
use App\Models\Remuneration;
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
                ->where('role', User::ROLE_PEGAWAI_MEDIS)
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
        return view('admin_rs.remunerations.show', [
            'item' => $remuneration->load(['user:id,name,unit_id','assessmentPeriod:id,name']),
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
}
