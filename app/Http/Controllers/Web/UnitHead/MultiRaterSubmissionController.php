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

        // When there is no currently-open window, but the latest period is in REVISION,
        // allow editing 360 for that revision period (even if the window has ended).
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
        $unitId = Auth::user()?->unit_id;
        $assessorProfessionId = null;
        $assessorUnitId = (int) ($unitId ?? 0);
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
            $periodStatus = (string) ($activePeriod?->status ?? '');
            if ($periodStatus === AssessmentPeriod::STATUS_REVISION) {
                $canSubmit = true;
                $windowIsActive = true;
            } else {
                $canSubmit = $windowIsActive && $periodStatus === AssessmentPeriod::STATUS_ACTIVE;
            }
            $assessor = Auth::user()?->loadMissing(['profession', 'unit']);
            $assessorProfessionId = $assessor ? AssessorProfessionResolver::resolve($assessor, 'kepala_unit') : null;
            $assessorUnitId = (int) ($assessor?->unit_id ?? 0);

            if ($assessorProfessionId && $assessorUnitId > 0) {
                $assessments = MultiRaterAssessment::query()
                    ->select('multi_rater_assessments.*', 'u.profession_id as assessee_profession_id', 'u.unit_id as assessee_unit_id')
                    ->join('users as u', 'u.id', '=', 'multi_rater_assessments.assessee_id')
                    ->where('multi_rater_assessments.assessor_id', Auth::id())
                    ->where('multi_rater_assessments.assessment_period_id', $window->assessment_period_id)
                    ->where('multi_rater_assessments.assessor_type', 'supervisor')
                    ->where('u.unit_id', $assessorUnitId)
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
            if ($unitId && $windowIsActive) {
                $assessor = $assessor ?? Auth::user()?->loadMissing(['profession', 'unit']);
                $assessorProfessionId = $assessorProfessionId ?? ($assessor ? AssessorProfessionResolver::resolve($assessor, 'kepala_unit') : null);

                $criteriaList = CriteriaResolver::forUnit($unitId, $periodId);
                $criteriaByType = collect(AssessorTypeResolver::TYPES)
                    ->mapWithKeys(fn($t) => [$t => CriteriaResolver::filterCriteriaByAssessorType($criteriaList, $t)])
                    ->all();

                $reportingRows = DB::table('profession_reporting_lines')
                    ->where('is_active', true)
                    ->where('is_required', true)
                    ->where('relation_type', 'supervisor')
                    ->get(['assessee_profession_id', 'assessor_profession_id', 'relation_type']);
                $reportingMap = [];
                $supervisorLevelsMap = [];
                foreach ($reportingRows as $row) {
                    $ap = (int) $row->assessee_profession_id;
                    $rp = (int) $row->assessor_profession_id;
                    $rt = (string) $row->relation_type;
                    $reportingMap[$ap][$rt][$rp] = true;
                }

                $levelRows = DB::table('profession_reporting_lines')
                    ->where('is_active', true)
                    ->where('is_required', true)
                    ->where('relation_type', 'supervisor')
                    ->get(['assessee_profession_id', 'assessor_profession_id', 'level']);
                foreach ($levelRows as $row) {
                    $ap = (int) $row->assessee_profession_id;
                    $rp = (int) $row->assessor_profession_id;
                    $lvl = $row->level === null ? 0 : (int) $row->level;
                    if ($lvl > 0) {
                        $supervisorLevelsMap[$ap][$rp][] = $lvl;
                    }
                }

                $contextResolver = function ($target) use ($assessorProfessionId, $criteriaByType, $reportingMap, $supervisorLevelsMap) {
                    $assessorType = 'supervisor';
                    $assesseeProfessionId = !empty($target->profession_id) ? (int) $target->profession_id : 0;
                    $assessorProfessionIdInt = $assessorProfessionId ? (int) $assessorProfessionId : 0;

                    if ($assesseeProfessionId <= 0 || $assessorProfessionIdInt <= 0 || empty($reportingMap[$assesseeProfessionId][$assessorType][$assessorProfessionIdInt])) {
                        return [
                            'assessor_type' => $assessorType,
                            'assessor_level' => 0,
                            'criteria' => collect(),
                        ];
                    }

                    $levels = $supervisorLevelsMap[$assesseeProfessionId][$assessorProfessionIdInt] ?? [];
                    $assessorLevel = !empty($levels) ? (int) min($levels) : 0;
                    if ($assessorLevel <= 0) {
                        return [
                            'assessor_type' => $assessorType,
                            'assessor_level' => 0,
                            'criteria' => collect(),
                        ];
                    }

                    return [
                        'assessor_type' => $assessorType,
                        'assessor_level' => $assessorLevel,
                        'criteria' => $criteriaByType[$assessorType] ?? collect(),
                    ];
                };

                $rawStaff = User::query()
                    ->role('pegawai_medis')
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
                        if ($u->unit?->name) $parts[] = $u->unit->name;
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
                            'profession_id' => $u->profession_id,
                        ];
                    });

                $formData = SimpleFormData::build($periodId, Auth::id(), $assessorProfessionId, $rawStaff, $contextResolver, true);
                $unitStaff = collect($formData['targets']);
                $criteriaOptions = collect($formData['criteria_catalog']);
                $remainingAssignments = $formData['remaining_assignments'];
                $completedAssignments = $formData['completed_assignments'];
                $totalAssignments = $formData['total_assignments'];
            }
            // Saved simple 360 scores for this rater & period (limit to current unit)
            if ($periodId && $unitId && $assessorProfessionId) {
                $criteriaTable = (new PerformanceCriteria())->getTable();
                $assessor = $assessor ?? Auth::user()?->loadMissing('profession');
                $assessorProfessionId = $assessorProfessionId ?? ($assessor ? AssessorProfessionResolver::resolve($assessor, 'kepala_unit') : null);
                $savedScores = \App\Models\MultiRaterAssessmentDetail::query()
                    ->join('multi_rater_assessments as mra', 'mra.id', '=', 'multi_rater_assessment_details.multi_rater_assessment_id')
                    ->join('users as u', 'u.id', '=', 'mra.assessee_id')
                    ->leftJoin($criteriaTable . ' as pc', 'pc.id', '=', 'multi_rater_assessment_details.performance_criteria_id')
                    ->where('mra.assessment_period_id', $periodId)
                    ->where('mra.assessor_id', Auth::id())
                    ->where('mra.assessor_type', 'supervisor')
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
        abort_unless((string) $assessment->assessor_type === 'supervisor', 403);
        $assessment->loadMissing('assessee');
        $unitId = (int) (Auth::user()?->unit_id ?? 0);
        if ($unitId > 0) {
            $assesseeUnitId = (int) ($assessment->assessee?->unit_id ?? 0);
            abort_unless($assesseeUnitId === $unitId, 403);
        }
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
            return redirect()->route('kepala_unit.multi_rater.index')->with('error','Penilaian 360 belum dibuka.');
        }

        $windowStartsAt = optional($window->start_date)?->copy()->startOfDay();
        $windowEndsAt = optional($window->end_date)?->copy()->endOfDay();
        $windowIsActive = $windowStartsAt && $windowEndsAt ? now()->between($windowStartsAt, $windowEndsAt, true) : false;
        $canSubmit = ((string) ($period?->status ?? '') === AssessmentPeriod::STATUS_ACTIVE)
            ? ($windowIsActive && (bool) ($window->is_active ?? false))
            : ((string) ($period?->status ?? '') === AssessmentPeriod::STATUS_REVISION);
        $criterias = PerformanceCriteria::where('is_360', true)->where('is_active', true)->get();
        $details = $assessment->details()->get()->keyBy('performance_criteria_id');
        return view('kepala_unit.multi_rater.show', compact('assessment','criterias','details','window','canSubmit'));
    }

    public function submit(Request $request, MultiRaterAssessment $assessment, PeriodPerformanceAssessmentService $perfSvc)
    {
        abort_unless($assessment->assessor_id === Auth::id(), 403);
        abort_unless((string) $assessment->assessor_type === 'supervisor', 403);
        $assessment->loadMissing('assessee');
        $unitId = (int) (Auth::user()?->unit_id ?? 0);
        if ($unitId > 0) {
            $assesseeUnitId = (int) ($assessment->assessee?->unit_id ?? 0);
            abort_unless($assesseeUnitId === $unitId, 403);
        }
        $assesseeProfessionId = (int) ($assessment->assessee?->profession_id ?? 0);
        $assessorProfessionId = (int) ($assessment->assessor_profession_id ?? 0);
        $assessorLevel = $this->resolveAndBackfillSupervisorLevelIfMissing($assessment, $assesseeProfessionId, $assessorProfessionId);
        abort_unless($this->isValidSupervisorRelation($assesseeProfessionId, $assessorProfessionId, $assessorLevel), 403);
        $period = AssessmentPeriod::query()->find((int) $assessment->assessment_period_id);
        AssessmentPeriodGuard::forbidWhenApprovalRejected($period, 'Penilaian 360');
        AssessmentPeriodGuard::requireActiveOrRevision($period, 'Penilaian 360');

        // For ACTIVE periods, require an active window. For REVISION, allow regardless of window dates.
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

        $assessee = $assessment->assessee()->first(['id', 'unit_id', 'profession_id']);
        if ($assessee) {
            $perfSvc->recalculateForGroup(
                (int) $assessment->assessment_period_id,
                $assessee->unit_id ? (int) $assessee->unit_id : null,
                $assessee->profession_id ? (int) $assessee->profession_id : null
            );
        }

        return redirect()->route('kepala_unit.multi_rater.index')->with('status', 'Penilaian 360 berhasil disimpan. Status akan menjadi SUBMITTED saat periode penilaian berakhir.');
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
