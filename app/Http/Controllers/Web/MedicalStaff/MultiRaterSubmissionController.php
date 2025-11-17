<?php

namespace App\Http\Controllers\Web\MedicalStaff;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Carbon;
use App\Models\MultiRaterAssessment;
use App\Models\MultiRaterAssessmentDetail;
use App\Models\PerformanceCriteria;

class MultiRaterSubmissionController extends Controller
{
    public function index()
    {
        $assessments = MultiRaterAssessment::where('assessor_id', Auth::id())
            ->whereIn('status', ['invited','in_progress'])
            ->orderByDesc('id')
            ->get();
        return view('pegawai_medis.multi_rater.index', compact('assessments'));
    }

    public function show(MultiRaterAssessment $assessment)
    {
        abort_unless($assessment->assessor_id === Auth::id(), 403);
        $criterias = PerformanceCriteria::where('is_360_based', true)->where('is_active', true)->get();
        $details = $assessment->details()->get()->keyBy('performance_criteria_id');
        return view('pegawai_medis.multi_rater.show', compact('assessment','criterias','details'));
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
