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
use App\Services\MultiRater\AssessorProfessionResolver;
use App\Services\MultiRater\AssessorTypeResolver;
use App\Services\MultiRater\AssessorLevelResolver;
use App\Services\MultiRater\SimpleFormData;
use App\Services\MultiRater\SummaryService;
use App\Support\AssessmentPeriodGuard;
use Illuminate\Support\Facades\DB;

class MultiRaterSubmissionController extends Controller
{
    public function index(Request $request)
    {
        $today = now()->toDateString();

        // Prefer REVISION period as the working period, else ACTIVE.
        $activePeriod = AssessmentPeriod::query()
            ->whereIn('status', [AssessmentPeriod::STATUS_REVISION, AssessmentPeriod::STATUS_ACTIVE])
            ->orderByDesc(\DB::raw("status='" . AssessmentPeriod::STATUS_REVISION . "'"))
            ->orderByDesc('start_date')
            ->first();

        $window = null;
        if ($activePeriod) {
            // ACTIVE: prefer active window; REVISION: allow latest window even if closed.
            $window = Assessment360Window::where('assessment_period_id', (int) $activePeriod->id)
                ->when((string) $activePeriod->status === AssessmentPeriod::STATUS_ACTIVE, fn($q) => $q->where('is_active', true))
                ->orderByDesc('id')
                ->first();
        }

        if ($window && method_exists($window, 'loadMissing')) {
            $window->loadMissing('period');
        }

        $assessments = collect();
        $selfTargets = collect();
        $selfCriteriaOptions = collect();
        $selfRemainingAssignments = 0;
        $selfCompletedAssignments = 0;
        $selfTotalAssignments = 0;

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
        if ($window && $activePeriod) {
            $periodId = (int) $window->assessment_period_id;
            $windowStartsAt = optional($window->start_date)?->copy()->startOfDay();
            $windowEndsAt = optional($window->end_date)?->copy()->endOfDay();
            if ($windowStartsAt && $windowEndsAt) {
                $windowIsActive = now()->between($windowStartsAt, $windowEndsAt, true);
            }

            if ((string) ($activePeriod->status ?? '') === AssessmentPeriod::STATUS_ACTIVE) {
                $canSubmit = $windowIsActive && (bool) ($window->is_active ?? false);
            } else {
                // REVISION: allow submission even if window already closed.
                $canSubmit = true;
            }

            $assessments = MultiRaterAssessment::with(['assessee.unit','period'])
                ->where('assessor_id', Auth::id())
                ->where('assessment_period_id', $window->assessment_period_id)
                ->whereIn('assessor_type', ['self', 'peer', 'subordinate'])
                ->whereIn('status', ['invited','in_progress'])
                ->orderByDesc('id')
                ->get();

            // Simple form targets: peers in same unit excluding self
            if ($unitId && $canSubmit) {
                $assessor = Auth::user()?->loadMissing(['profession', 'unit']);
                $assessorProfessionId = $assessor ? AssessorProfessionResolver::resolve($assessor, 'pegawai_medis') : null;

                $criteriaList = CriteriaResolver::forUnit($unitId, $periodId);
                $criteriaByType = collect(AssessorTypeResolver::TYPES)
                    ->mapWithKeys(fn($t) => [$t => CriteriaResolver::filterCriteriaByAssessorType($criteriaList, $t)])
                    ->all();

                $assessorHasKepalaPoliklinik = $assessor ? (bool) $assessor->hasRole('kepala_poliklinik') : false;
                $reportingRows = DB::table('profession_reporting_lines')
                    ->where('is_active', true)
                    ->get(['assessee_profession_id', 'assessor_profession_id', 'relation_type']);
                $reportingMap = [];
                foreach ($reportingRows as $row) {
                    $ap = (int) $row->assessee_profession_id;
                    $rp = (int) $row->assessor_profession_id;
                    $rt = (string) $row->relation_type;
                    $reportingMap[$ap][$rt][$rp] = true;
                }

                $contextResolver = function ($target) use ($assessorProfessionId, $criteriaByType, $assessorHasKepalaPoliklinik, $reportingMap) {
                    $assessorType = AssessorTypeResolver::resolveByIds(
                        (int) Auth::id(),
                        $assessorProfessionId ? (int) $assessorProfessionId : null,
                        (int) $target->id,
                        !empty($target->profession_id) ? (int) $target->profession_id : null,
                        $assessorHasKepalaPoliklinik
                    );

                    if (in_array($assessorType, ['supervisor', 'peer', 'subordinate'], true)) {
                        $assesseeProfessionId = !empty($target->profession_id) ? (int) $target->profession_id : 0;
                        $assessorProfessionIdInt = $assessorProfessionId ? (int) $assessorProfessionId : 0;
                        if ($assesseeProfessionId <= 0 || $assessorProfessionIdInt <= 0 || empty($reportingMap[$assesseeProfessionId][$assessorType][$assessorProfessionIdInt])) {
                            return [
                                'assessor_type' => $assessorType,
                                'assessor_level' => 0,
                                'criteria' => collect(),
                            ];
                        }
                    }

                    $assessorLevel = 0;
                    if ($assessorType === 'supervisor' && $assessorProfessionId && !empty($target->profession_id)) {
                        $resolved = AssessorLevelResolver::resolveSupervisorLevel((int) $target->profession_id, (int) $assessorProfessionId, true);
                        $assessorLevel = (int) ($resolved ?? 1);
                    }

                    return [
                        'assessor_type' => $assessorType,
                        'assessor_level' => $assessorLevel,
                        'criteria' => $criteriaByType[$assessorType] ?? collect(),
                    ];
                };

                // Self assessment (distinct section)
                $selfTarget = $assessor ? (object) [
                    'id' => $assessor->id,
                    'name' => $assessor->name,
                    'label' => $assessor->name . ' (Penilaian Diri)',
                    'unit_name' => $assessor->unit?->name,
                    'employee_number' => $assessor->employee_number,
                    'profession_id' => $assessor->profession_id,
                ] : null;

                if ($selfTarget) {
                    $selfForm = SimpleFormData::build($periodId, Auth::id(), $assessorProfessionId, collect([$selfTarget]), $contextResolver, true);
                    $selfTargets = collect($selfForm['targets']);
                    $selfCriteriaOptions = collect($selfForm['criteria_catalog']);
                    $selfRemainingAssignments = $selfForm['remaining_assignments'];
                    $selfCompletedAssignments = $selfForm['completed_assignments'];
                    $selfTotalAssignments = $selfForm['total_assignments'];
                }

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
                            'profession_id' => $u->profession_id,
                        ];
                    });

                $formData = SimpleFormData::build($periodId, Auth::id(), $assessorProfessionId, $rawPeers, $contextResolver, true);
                $unitPeers = collect($formData['targets']);
                $criteriaOptions = collect($formData['criteria_catalog']);
                $remainingAssignments = $formData['remaining_assignments'];
                $completedAssignments = $formData['completed_assignments'];
                $totalAssignments = $formData['total_assignments'];
            }

            if ($periodId && $unitId) {
                $criteriaTable = (new PerformanceCriteria())->getTable();
                $assessor = Auth::user()?->loadMissing('profession');
                $assessorProfessionId = $assessor ? AssessorProfessionResolver::resolve($assessor, 'pegawai_medis') : null;
                $savedScores = \App\Models\MultiRaterAssessmentDetail::query()
                    ->join('multi_rater_assessments as mra', 'mra.id', '=', 'multi_rater_assessment_details.multi_rater_assessment_id')
                    ->join('users as u', 'u.id', '=', 'mra.assessee_id')
                    ->leftJoin($criteriaTable . ' as pc', 'pc.id', '=', 'multi_rater_assessment_details.performance_criteria_id')
                    ->where('mra.assessment_period_id', $periodId)
                    ->where('mra.assessor_id', Auth::id())
                    ->whereIn('mra.assessor_type', ['self', 'peer', 'subordinate'])
                    ->when($assessorProfessionId, fn($q) => $q->where('mra.assessor_profession_id', $assessorProfessionId))
                    ->where('pc.is_360', true)
                    ->where('u.unit_id', $unitId)
                    ->orderBy('u.name')
                    ->get([
                        'multi_rater_assessment_details.*',
                        'mra.assessee_id as target_user_id',
                        'mra.assessor_id as rater_user_id',
                        'mra.assessor_type',
                        'mra.assessor_level',
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
            'selfTargets',
            'selfCriteriaOptions',
            'selfRemainingAssignments',
            'selfCompletedAssignments',
            'selfTotalAssignments',
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
        abort_unless(in_array((string) $assessment->assessor_type, ['self', 'peer', 'subordinate'], true), 403);
        $period = AssessmentPeriod::query()->find((int) $assessment->assessment_period_id);

        AssessmentPeriodGuard::forbidWhenApprovalRejected($period, 'Penilaian 360');

        $windowQuery = Assessment360Window::where('assessment_period_id', $assessment->assessment_period_id)->orderByDesc('id');
        if ((string) ($period?->status ?? '') === AssessmentPeriod::STATUS_ACTIVE) {
            $windowQuery->where('is_active', true)->whereDate('end_date', '>=', now()->toDateString());
        }
        $window = $windowQuery->first();
        if (!$window) {
            return redirect()->route('pegawai_medis.multi_rater.index')->with('error','Penilaian 360 belum dibuka.');
        }

        $windowStartsAt = optional($window->start_date)?->copy()->startOfDay();
        $windowEndsAt = optional($window->end_date)?->copy()->endOfDay();
        $windowIsActive = $windowStartsAt && $windowEndsAt ? now()->between($windowStartsAt, $windowEndsAt, true) : false;
        $canSubmit = ((string) ($period?->status ?? '') === AssessmentPeriod::STATUS_ACTIVE)
            ? ($windowIsActive && (bool) ($window->is_active ?? false))
            : ((string) ($period?->status ?? '') === AssessmentPeriod::STATUS_REVISION);

        $criterias = PerformanceCriteria::where('is_360', true)->where('is_active', true)->get();
        $details = $assessment->details()->get()->keyBy('performance_criteria_id');
        return view('pegawai_medis.multi_rater.show', compact('assessment','criterias','details','window','canSubmit'));
    }

    public function submit(Request $request, MultiRaterAssessment $assessment)
    {
        abort_unless($assessment->assessor_id === Auth::id(), 403);
        abort_unless(in_array((string) $assessment->assessor_type, ['self', 'peer', 'subordinate'], true), 403);
        $period = AssessmentPeriod::query()->find((int) $assessment->assessment_period_id);

        AssessmentPeriodGuard::forbidWhenApprovalRejected($period, 'Penilaian 360');
        AssessmentPeriodGuard::requireActiveOrRevision($period, 'Penilaian 360');

        if ((string) ($period?->status ?? '') === AssessmentPeriod::STATUS_ACTIVE) {
            $window = Assessment360Window::where('assessment_period_id', $assessment->assessment_period_id)
                ->where('is_active', true)
                ->whereDate('end_date', '>=', now()->toDateString())
                ->orderByDesc('id')
                ->first();
            $windowStartsAt = optional($window?->start_date)?->copy()->startOfDay();
            $windowEndsAt = optional($window?->end_date)?->copy()->endOfDay();
            $windowIsActive = $windowStartsAt && $windowEndsAt ? now()->between($windowStartsAt, $windowEndsAt, true) : false;
            if (!$window || !$windowIsActive) {
                abort(403, 'Penilaian 360 hanya dapat dilakukan saat jadwal 360 sedang aktif.');
            }
        }

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

        $assessment->status = 'in_progress';
        $assessment->submitted_at = null;
        $assessment->save();

        return redirect()->route('pegawai_medis.multi_rater.index')->with('status', 'Penilaian 360 berhasil disimpan. Status akan menjadi SUBMITTED saat periode penilaian berakhir.');
    }
}
