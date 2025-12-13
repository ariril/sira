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
use App\Models\User;
use App\Services\MultiRater\CriteriaResolver;
use App\Services\MultiRater\SimpleFormData;
use App\Services\MultiRater\SummaryService;

class MultiRaterSubmissionController extends Controller
{
    public function index(Request $request)
    {
        $today = now()->toDateString();
        $window = Assessment360Window::where('is_active', true)
            ->whereDate('end_date', '>=', $today)
            ->orderByDesc('end_date')
            ->first();
        if ($window) {
            $window->loadMissing('period');
        }

        $activePeriod = null;
        $assessments = collect();
        $unitPeers = collect();
        $criteriaOptions = collect();
        $remainingAssignments = 0;
        $completedAssignments = 0;
        $totalAssignments = 0;
        $savedScores = collect();
        $periodId = null;
        $unitId = Auth::user()?->unit_id;
        $windowEndsAt = null;
        $windowIsActive = false;
        if ($window) {
            $activePeriod = \App\Models\AssessmentPeriod::find($window->assessment_period_id);
            $periodId = $window->assessment_period_id;
            $windowStartsAt = optional($window->start_date)?->copy()->startOfDay();
            $windowEndsAt = optional($window->end_date)?->copy()->endOfDay();
            if ($windowStartsAt && $windowEndsAt) {
                $windowIsActive = now()->between($windowStartsAt, $windowEndsAt, true);
            }
            $assessments = MultiRaterAssessment::with(['assessee.unit','period'])
                ->where('assessor_id', Auth::id())
                ->where('assessment_period_id', $window->assessment_period_id)
                ->whereIn('status', ['invited','in_progress'])
                ->orderByDesc('id')
                ->get();

            // Simple form targets: peers in same unit excluding self
            if ($unitId && $windowIsActive) {
                $criteriaList = CriteriaResolver::forUnit($unitId, $periodId);

                $rawPeers = User::query()
                    ->where('unit_id', $unitId)
                    ->where('id', '!=', Auth::id())
                    ->with(['profession','unit'])
                    ->orderBy('name')
                    ->get()
                    ->map(function ($u) {
                        $roles = method_exists($u, 'getRoleNames') ? $u->getRoleNames()->toArray() : [];
                        $profession = $u->profession->name ?? null;
                        $parts = [];
                        foreach ($roles as $r) {
                            if ($r === 'kepala_unit' && $u->unit?->name) {
                                $parts[] = 'Kepala Poli ' . $u->unit->name;
                            } else {
                                $parts[] = ucwords(str_replace('_',' ', $r));
                            }
                        }
                        if ($profession) $parts[] = $profession;
                        $label = $parts ? ($u->name . ' (' . implode(', ', $parts) . ')') : $u->name;
                        return (object) [
                            'id' => $u->id,
                            'name' => $u->name,
                            'label' => $label,
                            'unit_name' => $u->unit->name ?? null,
                            'employee_number' => $u->employee_number,
                        ];
                    });

                $formData = SimpleFormData::build($periodId, Auth::id(), $rawPeers, fn () => $criteriaList);
                $unitPeers = collect($formData['targets']);
                $criteriaOptions = collect($formData['criteria_catalog']);
                $remainingAssignments = $formData['remaining_assignments'];
                $completedAssignments = $formData['completed_assignments'];
                $totalAssignments = $formData['total_assignments'];
            }

            if ($periodId && $unitId) {
                $criteriaTable = (new PerformanceCriteria())->getTable();
                $savedScores = \App\Models\MultiRaterScore::query()
                    ->where('period_id', $periodId)
                    ->where('rater_user_id', Auth::id())
                    ->join('users as u', 'u.id', '=', 'multi_rater_scores.target_user_id')
                    ->leftJoin($criteriaTable . ' as pc', 'pc.id', '=', 'multi_rater_scores.performance_criteria_id')
                    ->where('pc.input_method', '360')
                    ->where('u.unit_id', $unitId)
                    ->orderBy('u.name')
                    ->get(['multi_rater_scores.*','u.name as target_name','pc.name as criteria_name','pc.type as criteria_type']);
            }
        }

        $summary = SummaryService::build(Auth::id(), $request->get('summary_period_id'));

        return view('pegawai_medis.multi_rater.index', compact(
            'assessments',
            'window',
            'activePeriod',
            'unitPeers',
            'periodId',
            'unitId',
            'savedScores',
            'criteriaOptions',
            'remainingAssignments',
            'completedAssignments',
            'totalAssignments',
            'summary',
            'windowEndsAt',
            'windowIsActive'
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
        $criterias = PerformanceCriteria::where('input_method', '360')->where('is_active', true)->get();
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

        $criterias = PerformanceCriteria::where('input_method', '360')->where('is_active', true)->get();
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
