<?php

namespace App\Http\Controllers\Web\AdminHospital;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use App\Models\MultiRaterAssessment;
use App\Models\AssessmentPeriod;
use App\Models\Assessment360Window;

class MultiRaterController extends Controller
{
    public function index(Request $request)
    {
        $periods = AssessmentPeriod::orderByDesc('start_date')->get();
        $periodId = (int)($request->get('period_id') ?? optional($periods->first())->id);
        $period = $periodId ? $periods->firstWhere('id', $periodId) : null;

        $latestWindow = $period ? Assessment360Window::where('assessment_period_id', $period->id)
            ->orderByDesc('id')
            ->first() : null;

        // Consider window "active" purely by is_active flag, independent of date range
        $window = $period ? Assessment360Window::where('assessment_period_id', $period->id)
            ->where('is_active', true)
            ->first() : null;

        $stats = [];
        $summary = [
            'active360Criteria' => 0,
            'unitCount' => 0,
            'isActiveWindow' => (bool) $window,
            'submittedCount' => 0,
            'neverOpened' => $latestWindow === null,
            'windowClosed' => $latestWindow && $latestWindow->is_active === false,
        ];
        if ($period) {
            $stats = MultiRaterAssessment::selectRaw('status, COUNT(*) as total')
                ->where('assessment_period_id', $period->id)
                ->groupBy('status')
                ->pluck('total','status')
                ->toArray();

            // Active 360 criteria within this period
            $summary['active360Criteria'] = \App\Models\PerformanceCriteria::query()
                ->where('is_active', true)
                ->where('input_method', '360')
                ->count();
            // Unit count under hospital
            $summary['unitCount'] = \DB::table('units')->count();
            // Submitted assessments count
            $summary['submittedCount'] = MultiRaterAssessment::where('assessment_period_id', $period->id)
                ->where('status', 'submitted')
                ->count();
        }

        return view('admin_rs.multi_rater.index', compact('periods','period','window','stats','summary'));
    }

    public function openWindow(Request $request)
    {
        $data = $request->validate([
            'assessment_period_id' => ['required','integer','exists:assessment_periods,id'],
            'start_date' => ['required','date'],
            'end_date' => ['required','date','after_or_equal:start_date'],
        ]);
        $period = AssessmentPeriod::findOrFail($data['assessment_period_id']);
        $pStart = $period->getRawOriginal('start_date');
        $pEnd   = $period->getRawOriginal('end_date');
        $today  = now()->toDateString();
        if ($data['start_date'] < $pStart || $data['end_date'] > $pEnd) {
            return back()->with('error', 'Rentang tanggal harus berada di dalam periode '.$period->name.'.');
        }
        if ($data['start_date'] < $today) {
            return back()->with('error', 'Tanggal mulai minimal hari ini.');
        }

        $existingWindow = Assessment360Window::where('assessment_period_id', $period->id)
            ->orderByDesc('id')
            ->first();

        // If there was a window before and it's already closed, do not allow re-opening for the same period
        if ($existingWindow && $existingWindow->is_active === false) {
            return back()->with('error', 'Penilaian 360 untuk periode ini sudah ditutup dan tidak dapat dibuka kembali.');
        }

        if ($existingWindow && $existingWindow->is_active) {
            // Update the existing active window dates instead of creating a new one
            $existingWindow->update([
                'start_date' => $data['start_date'],
                'end_date' => $data['end_date'],
            ]);
        } else {
            // Create first-time window for this period
            Assessment360Window::create([
                'assessment_period_id' => $period->id,
                'start_date' => $data['start_date'],
                'end_date' => $data['end_date'],
                'is_active' => true,
                'opened_by' => auth()->id(),
            ]);
        }

        return redirect()->route('admin_rs.multi_rater.index', ['period_id' => $period->id])
            ->with('status', 'Jadwal penilaian 360 dibuka.');
    }

    public function closeWindow(Request $request)
    {
        $request->validate(['assessment_period_id' => ['required','integer','exists:assessment_periods,id']]);
        Assessment360Window::where('assessment_period_id', (int)$request->assessment_period_id)
            ->where('is_active', true)->update(['is_active' => false]);
        return redirect()->route('admin_rs.multi_rater.index', ['period_id' => (int)$request->assessment_period_id])
            ->with('status', 'Jadwal penilaian 360 ditutup.');
    }

    public function generate(Request $request)
    {
        $request->validate(['assessment_period_id' => 'required|integer|exists:assessment_periods,id']);
        $periodId = (int)$request->assessment_period_id;
        $window = Assessment360Window::where('assessment_period_id', $periodId)->where('is_active', true)->first();
        if (!$window) return back()->with('error','Buka jadwal 360 terlebih dahulu.');

        Artisan::call('mra:generate', ['period_id' => $periodId]);
        return redirect()->route('admin_rs.multi_rater.index', ['period_id' => $periodId])
            ->with('status', trim(Artisan::output()) ?: 'Undangan 360 dibuat/di-update.');
    }
}
