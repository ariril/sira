<?php

namespace App\Http\Controllers\Web\MedicalStaff;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Carbon;
use App\Models\MultiRaterAssessment;
use App\Models\MultiRaterAssessmentDetail;
use App\Models\PerformanceCriteria;
use App\Models\Assessment360Window;

class MultiRaterSubmissionController extends Controller
{
    public function index(Request $request)
    {
        $today = now()->toDateString();
        $window = Assessment360Window::where('is_active', true)
            ->whereDate('end_date', '>=', $today)
            ->orderByDesc('end_date')
            ->first();

        $activePeriod = null;
        $assessments = collect();
        if ($window) {
            $activePeriod = \App\Models\AssessmentPeriod::find($window->assessment_period_id);
            $assessments = MultiRaterAssessment::with(['assessee.unit','period'])
                ->where('assessor_id', Auth::id())
                ->where('assessment_period_id', $window->assessment_period_id)
                ->whereIn('status', ['invited','in_progress'])
                ->orderByDesc('id')
                ->get();
        }

        // Summary: average received score per selected (closed) period
        $periods = \App\Models\AssessmentPeriod::orderByDesc('end_date')->get();
        $defaultSummaryPeriod = $periods->firstWhere(fn($p) => $p->end_date->toDateString() < $today) ?? $periods->first();
        $summaryPeriodId = (int)($request->get('summary_period_id') ?? optional($defaultSummaryPeriod)->id);
        $summaryPeriod = $periods->firstWhere('id', $summaryPeriodId);

        $avgScore = null;
        if ($summaryPeriod) {
            $avgScore = \App\Models\MultiRaterAssessmentDetail::whereHas('header', function ($q) use ($summaryPeriod) {
                    $q->where('assessee_id', Auth::id())
                      ->where('assessment_period_id', $summaryPeriod->id)
                      ->where('status', 'submitted');
                })
                ->avg('score');
        }

        return view('pegawai_medis.multi_rater.index', compact(
            'assessments','window','activePeriod','periods','summaryPeriod','avgScore'
        ));
    }

    public function show(MultiRaterAssessment $assessment)
    {
        abort_unless($assessment->assessor_id === Auth::id(), 403);
        $window = Assessment360Window::where('assessment_period_id', $assessment->assessment_period_id)
            ->where('is_active', true)
            ->whereDate('end_date', '>=', now()->toDateString())
            ->first();
        if (!$window) return redirect()->route('pegawai_medis.multi_rater.index')->with('error','Penilaian 360 belum dibuka.');
        $criterias = PerformanceCriteria::where('is_360_based', true)->where('is_active', true)->get();
        $details = $assessment->details()->get()->keyBy('performance_criteria_id');
        return view('pegawai_medis.multi_rater.show', compact('assessment','criterias','details','window'));
    }

    public function submit(Request $request, MultiRaterAssessment $assessment)
    {
        abort_unless($assessment->assessor_id === Auth::id(), 403);
        $payload = $request->validate([
            'scores' => 'required|array',
            'scores.*' => 'nullable|numeric|min:0|max:100',
            'comments' => 'sometimes|array',
            'comments.*' => 'nullable|string|max:1000',
        ]);

        $criterias = PerformanceCriteria::where('is_360_based', true)->where('is_active', true)->get();
        foreach ($criterias as $c) {
            $score = $payload['scores'][$c->id] ?? null;
            $comment = $payload['comments'][$c->id] ?? null;
            if ($score === null && $comment === null) continue;
            MultiRaterAssessmentDetail::updateOrCreate(
                [
                    'multi_rater_assessment_id' => $assessment->id,
                    'performance_criteria_id' => $c->id,
                ],
                [
                    'score' => $score,
                    'comment' => $comment,
                ]
            );
        }

        $assessment->status = 'submitted';
        $assessment->submitted_at = Carbon::now();
        $assessment->save();

        return redirect()->route('pegawai_medis.multi_rater.index')->with('status', 'Penilaian 360 berhasil dikirim.');
    }
}
