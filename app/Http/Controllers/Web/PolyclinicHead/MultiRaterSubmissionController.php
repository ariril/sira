<?php

namespace App\Http\Controllers\Web\PolyclinicHead;

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
use App\Models\Role;
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
        $targets = collect();
        $criteriaOptions = collect();
        $remainingAssignments = 0;
        $completedAssignments = 0;
        $totalAssignments = 0;
        $savedScores = collect();
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
            if ($windowIsActive) {
                $rawMedics = User::query()
                    ->role(User::ROLE_PEGAWAI_MEDIS)
                    ->where('users.id', '!=', Auth::id())
                    ->with(['profession', 'unit', 'roles'])
                    ->orderBy('users.name')
                    ->get()
                    ->map(function ($u) {
                        $roles = $u->roles->pluck('slug')->all();
                        $parts = [];
                        if (in_array(User::ROLE_KEPALA_UNIT, $roles, true) && ($u->unit?->name)) {
                            $parts[] = 'Kepala Poli ' . $u->unit->name;
                        }
                        if ($u->profession?->name) {
                            $parts[] = $u->profession->name;
                        }
                        $label = $parts ? ($u->name . ' (' . implode(', ', $parts) . ')') : $u->name;

                        return (object) [
                            'id' => $u->id,
                            'name' => $u->name,
                            'label' => $label,
                            'unit_id' => $u->unit_id,
                            'unit_name' => $u->unit->name ?? null,
                            'employee_number' => $u->employee_number,
                        ];
                    });

                $medicForm = SimpleFormData::build(
                    $periodId,
                    Auth::id(),
                    $rawMedics,
                    fn ($target) => CriteriaResolver::forUnit($target->unit_id, $periodId)
                );
                $targets = collect($medicForm['targets']);
                $criteriaOptions = collect($medicForm['criteria_catalog']);
                $remainingAssignments = $medicForm['remaining_assignments'];
                $completedAssignments = $medicForm['completed_assignments'];
                $totalAssignments = $medicForm['total_assignments'];
            }

            if ($periodId) {
                $criteriaTable = (new PerformanceCriteria())->getTable();
                $rolePivotTable = 'role_user';
                $rolesTable = (new Role())->getTable();

                $savedScores = \App\Models\MultiRaterAssessmentDetail::query()
                    ->join('multi_rater_assessments as mra', 'mra.id', '=', 'multi_rater_assessment_details.multi_rater_assessment_id')
                    ->join('users as u', 'u.id', '=', 'mra.assessee_id')
                    ->join($rolePivotTable . ' as ru', 'ru.user_id', '=', 'u.id')
                    ->join($rolesTable . ' as r', 'r.id', '=', 'ru.role_id')
                    ->where('r.slug', User::ROLE_PEGAWAI_MEDIS)
                    ->leftJoin($criteriaTable . ' as pc', 'pc.id', '=', 'multi_rater_assessment_details.performance_criteria_id')
                    ->where('mra.assessment_period_id', $periodId)
                    ->where('mra.assessor_id', Auth::id())
                    ->where('pc.input_method', '360')
                    ->orderBy('u.name')
                    ->get([
                        'multi_rater_assessment_details.*',
                        'mra.assessee_id as target_user_id',
                        'mra.assessor_id as rater_user_id',
                        'mra.assessor_type',
                        'mra.assessment_period_id as period_id',
                        'u.name as target_name',
                        'pc.name as criteria_name',
                        'pc.type as criteria_type',
                    ]);
            }
        }
        $summary = SummaryService::build(Auth::id(), $request->get('summary_period_id'));

        return view('kepala_poli.multi_rater.index', compact(
            'assessments',
            'window',
            'periodId',
            'targets',
            'criteriaOptions',
            'remainingAssignments',
            'completedAssignments',
            'totalAssignments',
            'savedScores',
            'summary',
            'windowEndsAt',
            'windowIsActive',
            'activePeriod',
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
        if (!$window) return redirect()->route('kepala_poliklinik.multi_rater.index')->with('error','Penilaian 360 belum dibuka.');
        $windowStartsAt = optional($window->start_date)?->copy()->startOfDay();
        $windowEndsAt = optional($window->end_date)?->copy()->endOfDay();
        $windowIsActive = $windowStartsAt && $windowEndsAt ? now()->between($windowStartsAt, $windowEndsAt, true) : false;
        $canSubmit = $windowIsActive && ($period?->status === AssessmentPeriod::STATUS_ACTIVE);
        $criterias = PerformanceCriteria::where('input_method', '360')->where('is_active', true)->get();
        $details = $assessment->details()->get()->keyBy('performance_criteria_id');
        return view('kepala_poli.multi_rater.show', compact('assessment','criterias','details','window','canSubmit'));
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

        return redirect()->route('kepala_poliklinik.multi_rater.index')->with('status', 'Penilaian 360 berhasil dikirim.');
    }
}
