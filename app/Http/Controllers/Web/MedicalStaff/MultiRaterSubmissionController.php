<?php

namespace App\Http\Controllers\Web\MedicalStaff;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Carbon;
use App\Models\AssessmentPeriod;
use App\Models\MultiRaterAssessment;
use App\Models\MultiRaterAssessmentDetail;
use App\Models\PerformanceCriteria;
use App\Models\Assessment360Window;
use App\Models\User;
use App\Services\MultiRater\CriteriaResolver;
use App\Services\MultiRater\SimpleFormData;
use App\Services\MultiRater\SummaryService;
use App\Support\AssessmentPeriodGuard;

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
        $canSubmit = false;
        if ($window) {
            $activePeriod = AssessmentPeriod::query()->find($window->assessment_period_id);
            $periodId = $window->assessment_period_id;
            $windowStartsAt = optional($window->start_date)?->copy()->startOfDay();
            $windowEndsAt = optional($window->end_date)?->copy()->endOfDay();
            if ($windowStartsAt && $windowEndsAt) {
                $windowIsActive = now()->between($windowStartsAt, $windowEndsAt, true);
            }
            $canSubmit = $windowIsActive && ($activePeriod?->status === AssessmentPeriod::STATUS_ACTIVE);
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
                $savedScores = \App\Models\MultiRaterAssessmentDetail::query()
                    ->join('multi_rater_assessments as mra', 'mra.id', '=', 'multi_rater_assessment_details.multi_rater_assessment_id')
                    ->join('users as u', 'u.id', '=', 'mra.assessee_id')
                    ->leftJoin($criteriaTable . ' as pc', 'pc.id', '=', 'multi_rater_assessment_details.performance_criteria_id')
                    ->where('mra.assessment_period_id', $periodId)
                    ->where('mra.assessor_id', Auth::id())
                    ->where('pc.is_360', true)
                    ->where('u.unit_id', $unitId)
                    ->orderBy('u.name')
                    ->get([
                        'multi_rater_assessment_details.*',
                        'mra.assessee_id as target_user_id',
                        'mra.assessor_id as rater_user_id',
                        'mra.assessor_type',
                        'u.name as target_name',
                        'pc.name as criteria_name',
                        'pc.type as criteria_type',
                    ]);
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
            'windowIsActive',
            'canSubmit'
        ));
    }

    public function show(MultiRaterAssessment $assessment)
    {
        abort_unless($assessment->assessor_id === Auth::id(), 403);
        $period = AssessmentPeriod::query()->find((int) $assessment->assessment_period_id);
        $window = Assessment360Window::where('assessment_period_id', $assessment->assessment_period_id)
            ->where('is_active', true)
            ->whereDate('end_date', '>=', now()->toDateString())
            ->first();
        if (!$window) return redirect()->route('pegawai_medis.multi_rater.index')->with('error','Penilaian 360 belum dibuka.');
        $windowStartsAt = optional($window->start_date)?->copy()->startOfDay();
        $windowEndsAt = optional($window->end_date)?->copy()->endOfDay();
        $windowIsActive = $windowStartsAt && $windowEndsAt ? now()->between($windowStartsAt, $windowEndsAt, true) : false;
        $canSubmit = $windowIsActive && ($period?->status === AssessmentPeriod::STATUS_ACTIVE);
        $criterias = PerformanceCriteria::where('is_360', true)->where('is_active', true)->get();
        $details = $assessment->details()->get()->keyBy('performance_criteria_id');
        return view('pegawai_medis.multi_rater.show', compact('assessment','criterias','details','window','canSubmit'));
    }

    public function submit(Request $request, MultiRaterAssessment $assessment)
    {
        abort_unless($assessment->assessor_id === Auth::id(), 403);
        $period = AssessmentPeriod::query()->find((int) $assessment->assessment_period_id);
        AssessmentPeriodGuard::requireActive($period, 'Penilaian 360');
        $payload = $request->validate([
            'scores' => 'required|array',
            'scores.*' => 'nullable|numeric|min:0|max:100',
            'comments' => 'sometimes|array',
            'comments.*' => 'nullable|string|max:1000',
        ]);

        $criterias = PerformanceCriteria::where('is_360', true)->where('is_active', true)->get();
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
