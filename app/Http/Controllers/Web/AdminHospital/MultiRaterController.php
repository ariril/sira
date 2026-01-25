<?php

namespace App\Http\Controllers\Web\AdminHospital;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use App\Models\MultiRaterAssessment;
use App\Models\AssessmentPeriod;
use App\Models\Assessment360Window;
use App\Support\AssessmentPeriodGuard;
use Illuminate\Support\Carbon;
use App\Services\Assessment360\Assessment360WindowService;

class MultiRaterController extends Controller
{
    public function index(Request $request, Assessment360WindowService $windowService)
    {
        $periods = AssessmentPeriod::orderByDesc('start_date')->get();
        $periodId = (int)($request->get('assessment_period_id') ?? $request->get('period_id') ?? optional($periods->first())->id);
        $period = $periodId ? $periods->firstWhere('id', $periodId) : null;

        $latestWindow = $period ? Assessment360Window::where('assessment_period_id', $period->id)
            ->orderByDesc('id')
            ->first() : null;

        // Consider window "active" purely by is_active flag, independent of date range
        $window = $period ? Assessment360Window::where('assessment_period_id', $period->id)
            ->where('is_active', true)
            ->first() : null;

        // No lifecycle state transitions; window is controlled by is_active only.

        $stats = [];
        $summary = [
            'active360Criteria' => 0,
            'unitCount' => 0,
            'isActiveWindow' => (bool) $window,
            'submittedCount' => 0,
            'neverOpened' => $latestWindow === null,
            'windowClosed' => $latestWindow && !$window && $latestWindow->is_active === false,
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
                ->where('is_360', true)
                ->count();
            // Unit count under hospital
            $summary['unitCount'] = \DB::table('units')->count();
            // Submitted assessments count
            $summary['submittedCount'] = MultiRaterAssessment::where('assessment_period_id', $period->id)
                ->where('status', 'submitted')
                ->count();
        }

        $completeness = null;
        if ($latestWindow) {
            $completeness = $windowService->checkCompleteness($latestWindow);
        }

        return view('admin_rs.multi_rater.index', compact('periods','period','window','latestWindow','stats','summary','completeness'));
    }

    public function openWindow(Request $request)
    {
        $data = $request->validate([
            'assessment_period_id' => ['required','integer','exists:assessment_periods,id'],
            'start_date' => ['required','date'],
            'end_date' => ['required','date','after_or_equal:start_date'],
        ]);
        $period = AssessmentPeriod::findOrFail($data['assessment_period_id']);
        AssessmentPeriodGuard::forbidWhenApprovalRejected($period, 'Buka Jadwal Penilaian 360');
        AssessmentPeriodGuard::requireActiveOrRevision($period, 'Buka Jadwal Penilaian 360');

        $isRevision = (string) ($period->status ?? '') === AssessmentPeriod::STATUS_REVISION;

        $pStart = Carbon::parse($period->getRawOriginal('start_date'))->toDateString();
        $pEnd   = Carbon::parse($period->getRawOriginal('end_date'));
        $today  = now()->toDateString();

        // REVISION: allow extending the 360 window until the 10th of the next month.
        // Example: period ends 31 Jan -> max 10 Feb.
        $revisionMaxEnd = $pEnd->copy()->addMonthNoOverflow()->day(10)->toDateString();
        $maxEndAllowed = $isRevision ? $revisionMaxEnd : $pEnd->toDateString();

        if ($data['start_date'] < $pStart || $data['end_date'] > $maxEndAllowed) {
            $suffix = $isRevision
                ? 'Rentang tanggal harus berada di dalam periode ' . $period->name . ' (maksimal sampai ' . $maxEndAllowed . ').'
                : 'Rentang tanggal harus berada di dalam periode ' . $period->name . '.';
            return back()->with('error', $suffix);
        }
        if (!$isRevision && $data['start_date'] < $today) {
            return back()->with('error', 'Tanggal mulai minimal hari ini.');
        }

        // Enforce: only one window record per period. Admin always edits the existing record.
        // If the period doesn't have a record yet, create it once.
        $windowToEdit = Assessment360Window::where('assessment_period_id', $period->id)
            ->orderBy('id')
            ->first();

        if ($windowToEdit && $windowToEdit->is_active === false && !$isRevision) {
            return back()->with('error', 'Penilaian 360 untuk periode ini sudah ditutup dan tidak dapat dibuka kembali.');
        }

        if (!$windowToEdit) {
            $windowToEdit = Assessment360Window::create([
                'assessment_period_id' => $period->id,
                'start_date' => $data['start_date'],
                'end_date' => $data['end_date'],
                'is_active' => true,
                'opened_by' => auth()->id(),
            ]);
        } else {
            $windowToEdit->update([
                'start_date' => $data['start_date'],
                'end_date' => $data['end_date'],
                'is_active' => true,
                'opened_by' => auth()->id(),
            ]);
        }

        // Safety: if duplicates exist from older logic, keep them closed.
        Assessment360Window::where('assessment_period_id', $period->id)
            ->where('id', '!=', $windowToEdit->id)
            ->where('is_active', true)
            ->update(['is_active' => false]);

        return redirect()->route('admin_rs.multi_rater.index', ['assessment_period_id' => $period->id])
            ->with('status', 'Jadwal penilaian 360 dibuka.');
    }


    public function generate(Request $request)
    {
        $request->validate(['assessment_period_id' => 'required|integer|exists:assessment_periods,id']);
        $periodId = (int)$request->assessment_period_id;
        $period = AssessmentPeriod::query()->find($periodId);
        AssessmentPeriodGuard::forbidWhenApprovalRejected($period, 'Generate Undangan 360');
        AssessmentPeriodGuard::requireActiveOrRevision($period, 'Generate Undangan 360');
        $window = Assessment360Window::where('assessment_period_id', $periodId)->where('is_active', true)->first();
        if (!$window) return back()->with('error','Buka jadwal 360 terlebih dahulu.');

        Artisan::call('mra:generate', ['period_id' => $periodId]);
        return redirect()->route('admin_rs.multi_rater.index', ['assessment_period_id' => $periodId])
            ->with('status', trim(Artisan::output()) ?: 'Undangan 360 dibuat/di-update.');
    }
}
