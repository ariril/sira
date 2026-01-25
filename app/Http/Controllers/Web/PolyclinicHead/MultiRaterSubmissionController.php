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
        $window = Assessment360Window::where('is_active', true)
            ->whereDate('end_date', '>=', now()->toDateString())
            ->orderByDesc('end_date')->first();

        if (!$window) {
            $revisionPeriod = AssessmentPeriod::query()
                ->where('status', AssessmentPeriod::STATUS_REVISION)
                ->orderByDesc('start_date')
                ->first();

            if ($revisionPeriod) {
                $window = Assessment360Window::query()
                    ->where('assessment_period_id', (int) $revisionPeriod->id)
                    ->orderByDesc('end_date')
                    ->first();
            }
        }
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
        $assessorProfessionId = null;
        if ($window) {
            $periodId = $window->assessment_period_id;
            $windowStartsAt = optional($window->start_date)?->copy()->startOfDay();
            $windowEndsAt = optional($window->end_date)?->copy()->endOfDay();
            if ($windowStartsAt && $windowEndsAt) {
                $windowIsActive = now()->between($windowStartsAt, $windowEndsAt, true);
            }
            $activePeriod = $window->period;
            $periodStatus = (string) ($activePeriod?->status ?? '');
            if ($periodStatus === AssessmentPeriod::STATUS_REVISION) {
                $canSubmit = true;
                $windowIsActive = true;
            } else {
                $canSubmit = $windowIsActive && $periodStatus === AssessmentPeriod::STATUS_ACTIVE;
            }
            $assessor = Auth::user()?->loadMissing(['profession', 'unit', 'roles']);
            $assessorProfessionId = $assessor ? AssessorProfessionResolver::resolve($assessor, 'kepala_poliklinik') : null;

            if ($assessorProfessionId) {
                $assessments = MultiRaterAssessment::query()
                    ->select('multi_rater_assessments.*', 'u.profession_id as assessee_profession_id')
                    ->join('users as u', 'u.id', '=', 'multi_rater_assessments.assessee_id')
                    ->where('multi_rater_assessments.assessor_id', Auth::id())
                    ->where('multi_rater_assessments.assessment_period_id', $window->assessment_period_id)
                    ->where('multi_rater_assessments.assessor_type', 'supervisor')
                    ->whereIn('multi_rater_assessments.status', ['invited', 'in_progress'])
                    ->orderByDesc('multi_rater_assessments.id')
                    ->get()
                    ->filter(function ($mra) use ($assessorProfessionId) {
                        $assesseeProfessionId = (int) ($mra->assessee_profession_id ?? 0);
                        $level = $this->resolveAndBackfillSupervisorLevelIfMissing($mra, $assesseeProfessionId, (int) $assessorProfessionId);
                        return $this->isValidSupervisorRelation($assesseeProfessionId, (int) $assessorProfessionId, $level);
                    })
                    ->values();
            }
            if ($windowIsActive) {
                $assessor = $assessor ?? Auth::user()?->loadMissing(['profession', 'unit', 'roles']);
                $assessorProfessionId = $assessorProfessionId ?? ($assessor ? AssessorProfessionResolver::resolve($assessor, 'kepala_poliklinik') : null);
                $assessorHasKepalaPoliklinik = true;

                $criteriaCacheByUnit = [];
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

                $contextResolver = function ($target) use ($periodId, $assessorProfessionId, $assessorHasKepalaPoliklinik, &$criteriaCacheByUnit, $reportingMap) {
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

                    $unitId = (int) ($target->unit_id ?? 0);
                    if (!isset($criteriaCacheByUnit[$unitId])) {
                        $criteriaCacheByUnit[$unitId] = CriteriaResolver::forUnit($unitId ?: null, $periodId);
                    }
                    $base = $criteriaCacheByUnit[$unitId];

                    return [
                        'assessor_type' => $assessorType,
                        'assessor_level' => $assessorLevel,
                        'criteria' => CriteriaResolver::filterCriteriaByAssessorType($base, $assessorType),
                    ];
                };

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
                            'profession_id' => $u->profession_id,
                        ];
                    });

                $medicForm = SimpleFormData::build(
                    $periodId,
                    Auth::id(),
                    $assessorProfessionId,
                    $rawMedics,
                    $contextResolver,
                    true
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
                $assessor = $assessor ?? Auth::user()?->loadMissing('profession');
                $assessorProfessionId = $assessorProfessionId ?? ($assessor ? AssessorProfessionResolver::resolve($assessor, 'kepala_poliklinik') : null);

                $savedScores = \App\Models\MultiRaterAssessmentDetail::query()
                    ->join('multi_rater_assessments as mra', 'mra.id', '=', 'multi_rater_assessment_details.multi_rater_assessment_id')
                    ->join('users as u', 'u.id', '=', 'mra.assessee_id')
                    ->join($rolePivotTable . ' as ru', 'ru.user_id', '=', 'u.id')
                    ->join($rolesTable . ' as r', 'r.id', '=', 'ru.role_id')
                    ->where('r.slug', User::ROLE_PEGAWAI_MEDIS)
                    ->leftJoin($criteriaTable . ' as pc', 'pc.id', '=', 'multi_rater_assessment_details.performance_criteria_id')
                    ->where('mra.assessment_period_id', $periodId)
                    ->where('mra.assessor_id', Auth::id())
                    ->where('mra.assessor_type', 'supervisor')
                    ->when($assessorProfessionId, fn($q) => $q->where('mra.assessor_profession_id', $assessorProfessionId))
                    ->where('pc.is_360', true)
                    ->orderBy('u.name')
                    ->get([
                        'multi_rater_assessment_details.*',
                        'mra.assessee_id as target_user_id',
                        'mra.assessor_id as rater_user_id',
                        'mra.assessor_type',
                        'mra.assessor_level',
                        'mra.id as assessment_id',
                        'u.profession_id as assessee_profession_id',
                        'u.name as target_name',
                        'pc.name as criteria_name',
                        'pc.type as criteria_type',
                    ])
                    ->filter(function ($row) use ($assessorProfessionId) {
                        $assesseeProfessionId = (int) ($row->assessee_profession_id ?? 0);
                        $level = $this->resolveAndBackfillSupervisorLevelIfMissing($row, $assesseeProfessionId, (int) $assessorProfessionId);
                        return $this->isValidSupervisorRelation($assesseeProfessionId, (int) $assessorProfessionId, $level);
                    })
                    ->values();
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
        abort_unless((string) $assessment->assessor_type === 'supervisor', 403);
        $assessment->loadMissing('assessee');
        $assesseeProfessionId = (int) ($assessment->assessee?->profession_id ?? 0);
        $assessorProfessionId = (int) ($assessment->assessor_profession_id ?? 0);
        $assessorLevel = $this->resolveAndBackfillSupervisorLevelIfMissing($assessment, $assesseeProfessionId, $assessorProfessionId);
        abort_unless($this->isValidSupervisorRelation($assesseeProfessionId, $assessorProfessionId, $assessorLevel), 403);
        $period = AssessmentPeriod::query()->find((int) $assessment->assessment_period_id);

        AssessmentPeriodGuard::forbidWhenApprovalRejected($period, 'Penilaian 360');

        $windowQuery = Assessment360Window::where('assessment_period_id', $assessment->assessment_period_id)->orderByDesc('id');
        if ((string) ($period?->status ?? '') === AssessmentPeriod::STATUS_ACTIVE) {
            $windowQuery->where('is_active', true)->whereDate('end_date', '>=', now()->toDateString());
        }
        $window = $windowQuery->first();
        if (!$window) {
            return redirect()->route('kepala_poliklinik.multi_rater.index')->with('error','Penilaian 360 belum dibuka.');
        }

        $windowStartsAt = optional($window->start_date)?->copy()->startOfDay();
        $windowEndsAt = optional($window->end_date)?->copy()->endOfDay();
        $windowIsActive = $windowStartsAt && $windowEndsAt ? now()->between($windowStartsAt, $windowEndsAt, true) : false;
        $canSubmit = ((string) ($period?->status ?? '') === AssessmentPeriod::STATUS_ACTIVE)
            ? ($windowIsActive && (bool) ($window->is_active ?? false))
            : ((string) ($period?->status ?? '') === AssessmentPeriod::STATUS_REVISION);
        $criterias = PerformanceCriteria::where('is_360', true)->where('is_active', true)->get();
        $details = $assessment->details()->get()->keyBy('performance_criteria_id');
        return view('kepala_poli.multi_rater.show', compact('assessment','criterias','details','window','canSubmit'));
    }

    public function submit(Request $request, MultiRaterAssessment $assessment)
    {
        abort_unless($assessment->assessor_id === Auth::id(), 403);
        abort_unless((string) $assessment->assessor_type === 'supervisor', 403);
        $assessment->loadMissing('assessee');
        $assesseeProfessionId = (int) ($assessment->assessee?->profession_id ?? 0);
        $assessorProfessionId = (int) ($assessment->assessor_profession_id ?? 0);
        $assessorLevel = $this->resolveAndBackfillSupervisorLevelIfMissing($assessment, $assesseeProfessionId, $assessorProfessionId);
        abort_unless($this->isValidSupervisorRelation($assesseeProfessionId, $assessorProfessionId, $assessorLevel), 403);
        $period = AssessmentPeriod::query()->find((int) $assessment->assessment_period_id);
        AssessmentPeriodGuard::forbidWhenApprovalRejected($period, 'Penilaian 360');
        AssessmentPeriodGuard::requireActiveOrRevision($period, 'Penilaian 360');

        if ($period && (string) ($period->status ?? '') === AssessmentPeriod::STATUS_ACTIVE) {
            $window = Assessment360Window::query()
                ->where('assessment_period_id', (int) $period->id)
                ->where('is_active', true)
                ->whereDate('end_date', '>=', now()->toDateString())
                ->orderByDesc('end_date')
                ->first();

            $windowStartsAt = optional($window?->start_date)?->copy()->startOfDay();
            $windowEndsAt = optional($window?->end_date)?->copy()->endOfDay();
            $windowIsActive = $windowStartsAt && $windowEndsAt ? now()->between($windowStartsAt, $windowEndsAt, true) : false;

            if (!$window || !$windowIsActive) {
                return back()->with('error', 'Penilaian 360 tidak dapat disubmit: window penilaian tidak aktif.');
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

        return redirect()->route('kepala_poliklinik.multi_rater.index')->with('status', 'Penilaian 360 berhasil disimpan. Status akan menjadi SUBMITTED saat periode penilaian berakhir.');
    }

    private function resolveAndBackfillSupervisorLevelIfMissing($assessment, int $assesseeProfessionId, int $assessorProfessionId): int
    {
        $level = (int) ($assessment->assessor_level ?? 0);
        if ($level > 0) {
            return $level;
        }

        if ($assesseeProfessionId <= 0 || $assessorProfessionId <= 0) {
            return 0;
        }

        $resolved = $this->resolveSupervisorLevelFromPrl($assesseeProfessionId, $assessorProfessionId);
        if ($resolved <= 0) {
            return 0;
        }

        if (isset($assessment->id) || isset($assessment->assessment_id)) {
            $assessmentId = (int) ($assessment->id ?? $assessment->assessment_id ?? 0);
            if ($assessmentId > 0) {
                DB::table('multi_rater_assessments')
                    ->where('id', $assessmentId)
                    ->update(['assessor_level' => $resolved]);
            }
        }

        $assessment->assessor_level = $resolved;
        return (int) $resolved;
    }

    private function resolveSupervisorLevelFromPrl(int $assesseeProfessionId, int $assessorProfessionId): int
    {
        $level = DB::table('profession_reporting_lines')
            ->where('relation_type', 'supervisor')
            ->where('is_active', true)
            ->where('is_required', true)
            ->where('assessee_profession_id', $assesseeProfessionId)
            ->where('assessor_profession_id', $assessorProfessionId)
            ->whereNotNull('level')
            ->orderBy('level')
            ->value('level');

        return $level !== null ? (int) $level : 0;
    }

    private function isValidSupervisorRelation(int $assesseeProfessionId, int $assessorProfessionId, int $level): bool
    {
        if ($assesseeProfessionId <= 0 || $assessorProfessionId <= 0 || $level <= 0) {
            return false;
        }

        return DB::table('profession_reporting_lines')
            ->where('relation_type', 'supervisor')
            ->where('is_active', true)
            ->where('is_required', true)
            ->where('assessee_profession_id', $assesseeProfessionId)
            ->where('assessor_profession_id', $assessorProfessionId)
            ->where('level', $level)
            ->exists();
    }
}
