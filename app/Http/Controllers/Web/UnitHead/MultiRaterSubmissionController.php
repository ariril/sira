<?php

namespace App\Http\Controllers\Web\UnitHead;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Carbon;
use App\Models\MultiRaterAssessment;
use App\Models\MultiRaterAssessmentDetail;
use App\Models\PerformanceCriteria;
use App\Models\Assessment360Window;
use App\Models\AssessmentPeriod;
use App\Models\User;
use App\Services\AssessmentPeriods\PeriodPerformanceAssessmentService;
use App\Services\MultiRater\CriteriaResolver;
use App\Services\MultiRater\SimpleFormData;
use App\Services\MultiRater\SummaryService;
use App\Support\AssessmentPeriodGuard;

class MultiRaterSubmissionController extends Controller
{
    public function index(Request $request)
    {
        $window = Assessment360Window::where('is_active', true)
            ->whereDate('end_date', '>=', now()->toDateString())
            ->orderByDesc('end_date')->first();
        if ($window) {
            $window->loadMissing('period');
        }
        $assessments = collect();
        $periodId = null;
        $unitId = \Auth::user()?->unit_id;
        $savedScores = collect();
        $criteriaOptions = collect();
        $unitStaff = collect();
        $totalAssignments = 0;
        $completedAssignments = 0;
        $remainingAssignments = 0;
        $windowEndsAt = null;
        $windowIsActive = false;
        $activePeriod = null;
        $canSubmit = false;
        if ($window) {
            $periodId = $window->assessment_period_id;
            $windowStartsAt = optional($window->start_date)?->copy()->startOfDay();
            $windowEndsAt = optional($window->end_date)?->copy()->endOfDay();
            if ($windowStartsAt && $windowEndsAt) {
                $windowIsActive = now()->between($windowStartsAt, $windowEndsAt, true);
            }
            $activePeriod = $window->period;
            $canSubmit = $windowIsActive && ($activePeriod?->status === AssessmentPeriod::STATUS_ACTIVE);
            $assessments = MultiRaterAssessment::where('assessor_id', Auth::id())
                ->where('assessment_period_id', $window->assessment_period_id)
                ->whereIn('status', ['invited','in_progress'])
                ->orderByDesc('id')
                ->get();
            if ($unitId && $windowIsActive) {
                $criteriaList = CriteriaResolver::forUnit($unitId, $periodId);

                $rawStaff = User::query()
                    ->role('pegawai_medis')
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
                        $u->label = $label;
                        return $u;
                    })
                    ->map(function ($u) {
                        return (object) [
                            'id' => $u->id,
                            'name' => $u->name,
                            'label' => $u->label,
                            'unit_name' => $u->unit->name ?? null,
                            'employee_number' => $u->employee_number,
                        ];
                    });

                $formData = SimpleFormData::build($periodId, Auth::id(), $rawStaff, fn () => $criteriaList);
                $unitStaff = collect($formData['targets']);
                $criteriaOptions = collect($formData['criteria_catalog']);
                $remainingAssignments = $formData['remaining_assignments'];
                $completedAssignments = $formData['completed_assignments'];
                $totalAssignments = $formData['total_assignments'];
            }
            // Saved simple 360 scores for this rater & period (limit to current unit)
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

        return view('kepala_unit.multi_rater.index', compact(
            'assessments',
            'window',
            'unitStaff',
            'periodId',
            'unitId',
            'savedScores',
            'windowEndsAt',
            'criteriaOptions',
            'totalAssignments',
            'completedAssignments',
            'remainingAssignments',
            'summary',
            'activePeriod',
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
        if (!$window) return redirect()->route('kepala_unit.multi_rater.index')->with('error','Penilaian 360 belum dibuka.');
        $windowStartsAt = optional($window->start_date)?->copy()->startOfDay();
        $windowEndsAt = optional($window->end_date)?->copy()->endOfDay();
        $windowIsActive = $windowStartsAt && $windowEndsAt ? now()->between($windowStartsAt, $windowEndsAt, true) : false;
        $canSubmit = $windowIsActive && ($period?->status === AssessmentPeriod::STATUS_ACTIVE);
        $criterias = PerformanceCriteria::where('is_360', true)->where('is_active', true)->get();
        $details = $assessment->details()->get()->keyBy('performance_criteria_id');
        return view('kepala_unit.multi_rater.show', compact('assessment','criterias','details','window','canSubmit'));
    }

    public function submit(Request $request, MultiRaterAssessment $assessment, PeriodPerformanceAssessmentService $perfSvc)
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

        $assessee = $assessment->assessee()->first(['id', 'unit_id', 'profession_id']);
        if ($assessee) {
            $perfSvc->recalculateForGroup(
                (int) $assessment->assessment_period_id,
                $assessee->unit_id ? (int) $assessee->unit_id : null,
                $assessee->profession_id ? (int) $assessee->profession_id : null
            );
        }

        return redirect()->route('kepala_unit.multi_rater.index')->with('status', 'Penilaian 360 berhasil dikirim.');
    }
}
