<?php

namespace App\Http\Controllers\Web\UnitHead;

use App\Enums\RaterWeightStatus;
use App\Http\Controllers\Controller;
use App\Models\AssessmentPeriod;
use App\Models\CriteriaRaterRule;
use App\Models\PerformanceCriteria;
use App\Models\Profession;
use App\Models\RaterWeight;
use App\Services\RaterWeights\RaterWeightGenerator;
use App\Services\RaterWeights\RaterWeightSummaryService;
use App\Support\AssessmentPeriodGuard;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class RaterWeightController extends Controller
{
    private const ASSESSOR_TYPES = [
        'self' => 'Diri sendiri',
        'supervisor' => 'Atasan',
        'peer' => 'Rekan', 
        'subordinate' => 'Bawahan',
    ];

    public function __construct(
        private readonly RaterWeightGenerator $raterWeightGenerator,
        private readonly RaterWeightSummaryService $raterWeightSummaryService,
    ) {
    }

    public function index(Request $request): View
    {
        $this->authorizeAccess();

        $filters = $request->validate([
            'assessment_period_id' => ['nullable', 'integer'],
            'performance_criteria_id' => ['nullable', 'integer'],
            'assessee_profession_id' => ['nullable', 'integer'],
            'status' => ['nullable', Rule::in(array_map(fn($e) => $e->value, RaterWeightStatus::cases()))],
        ]);

        $periods = AssessmentPeriod::orderByDesc('start_date')->get();

        $me = Auth::user();
        $unitId = (int) ($me?->unit_id ?? 0);

        $unitName = null;
        if ($unitId > 0 && Schema::hasTable('units')) {
            $unitName = DB::table('units')->where('id', $unitId)->value('name');
        }

        $professionIds = $this->resolveProfessionIdsForUnit($unitId);
        $professions = Profession::query()
            ->when(!empty($professionIds), fn($q) => $q->whereIn('id', $professionIds))
            ->orderBy('name')
            ->get(['id', 'name']);

        $revisionPeriod = AssessmentPeriod::query()->where('status', AssessmentPeriod::STATUS_REVISION)->orderByDesc('id')->first();
        $activePeriod = AssessmentPeriod::query()->where('status', AssessmentPeriod::STATUS_ACTIVE)->orderByDesc('start_date')->first();
        $workingPeriod = $revisionPeriod ?: $activePeriod;
        $activePeriodId = (int) ($workingPeriod?->id ?? 0);
        $this->archiveNonActivePeriods($unitId, $activePeriodId);

        // Working period is the active period only (unless user explicitly filters a period for browsing).
        // When there is no active period and no filter is provided, we do not treat any period as "current".
        $currentPeriodId = (int) (($filters['assessment_period_id'] ?? null) ?: $activePeriodId);

        $hasProfession = $professions->isNotEmpty();
        $relevantCriteriaIds = $currentPeriodId > 0
            ? $this->resolveRelevant360CriteriaIdsForUnit($unitId, $currentPeriodId)
            : [];
        $hasRelevant360Criteria = !empty($relevantCriteriaIds);

        $hasRules = false;
        $singleRuleTypeByCriteriaId = [];
        if ($hasRelevant360Criteria && Schema::hasTable('criteria_rater_rules')) {
            $ruleCounts = DB::table('criteria_rater_rules')
                ->whereIn('performance_criteria_id', $relevantCriteriaIds)
                ->selectRaw('performance_criteria_id, COUNT(*) as cnt')
                ->groupBy('performance_criteria_id')
                ->get();

            $hasRules = $ruleCounts->isNotEmpty();

            $singleCriteriaIds = $ruleCounts->filter(fn($r) => (int) $r->cnt === 1)->pluck('performance_criteria_id')->map(fn($v) => (int) $v)->values()->all();
            if (!empty($singleCriteriaIds)) {
                $singleRuleTypeByCriteriaId = DB::table('criteria_rater_rules')
                    ->whereIn('performance_criteria_id', $singleCriteriaIds)
                    ->pluck('assessor_type', 'performance_criteria_id')
                    ->mapWithKeys(fn($v, $k) => [(int) $k => (string) $v])
                    ->all();
            }
        }

        if ($currentPeriodId > 0) {
            $this->sync360DraftForPeriod($unitId, $currentPeriodId);
        }

        $shouldSync = $activePeriodId > 0
            && $currentPeriodId === $activePeriodId
            && $hasRelevant360Criteria
            && $hasRules
            && $hasProfession;

        // Ensure history can still show even if current period prerequisites are incomplete.
        // If the rater weight table is empty for this unit, try to backfill from previous periods
        // that already have 360 criteria weights (pending/active/archived).
        $requestedPeriodId = (int) (!empty($filters['assessment_period_id'] ?? null) ? $filters['assessment_period_id'] : 0);
        if ($unitId > 0 && Schema::hasTable('unit_rater_weights') && Schema::hasTable('unit_criteria_weights') && Schema::hasTable('performance_criterias')) {
            if ($requestedPeriodId > 0 && $requestedPeriodId !== $activePeriodId) {
                $hasAnyForRequested = DB::table('unit_rater_weights')
                    ->where('unit_id', $unitId)
                    ->where('assessment_period_id', $requestedPeriodId)
                    ->exists();

                if (!$hasAnyForRequested) {
                    $this->raterWeightGenerator->syncForUnitPeriod($unitId, $requestedPeriodId);
                    $this->archiveNonActivePeriods($unitId, $activePeriodId);
                }
            } elseif ($requestedPeriodId <= 0) {
                $hasAnyHistory = DB::table('unit_rater_weights')
                    ->where('unit_id', $unitId)
                    ->when($activePeriodId > 0, fn($q) => $q->where('assessment_period_id', '!=', $activePeriodId))
                    ->exists();

                if (!$hasAnyHistory) {
                    $candidatePeriodIds = DB::table('unit_criteria_weights as ucw')
                        ->join('performance_criterias as pc', 'pc.id', '=', 'ucw.performance_criteria_id')
                        ->where('ucw.unit_id', $unitId)
                        ->whereNotNull('ucw.assessment_period_id')
                        ->where('pc.is_360', 1)
                        ->whereIn('ucw.status', ['pending', 'active', 'archived'])
                        ->when($activePeriodId > 0, fn($q) => $q->where('ucw.assessment_period_id', '!=', $activePeriodId))
                        ->select('ucw.assessment_period_id')
                        ->distinct()
                        ->orderByDesc('ucw.assessment_period_id')
                        ->limit(6)
                        ->pluck('ucw.assessment_period_id')
                        ->map(fn($v) => (int) $v)
                        ->filter(fn($v) => $v > 0)
                        ->values()
                        ->all();

                    foreach ($candidatePeriodIds as $pid) {
                        $this->raterWeightGenerator->syncForUnitPeriod($unitId, $pid);
                    }
                    $this->archiveNonActivePeriods($unitId, $activePeriodId);
                }
            }
        }

        // If a profession is requested but not in this unit's set, reset to null (avoid confusing empty results).
        if (!empty($filters['assessee_profession_id'] ?? null) && $hasProfession) {
            $requestedProfessionId = (int) $filters['assessee_profession_id'];
            if (!$professions->contains(fn($p) => (int) $p->id === $requestedProfessionId)) {
                $filters['assessee_profession_id'] = null;
            }
        }

        $base = RaterWeight::query()
            ->with(['period:id,name,start_date', 'unit:id,name', 'criteria:id,name,is_360', 'assesseeProfession:id,name', 'assessorProfession:id,name', 'proposedBy:id,name', 'decidedBy:id,name'])
            ->where('unit_id', $unitId)
            ->when(!empty($filters['performance_criteria_id'] ?? null), fn($q) => $q->where('performance_criteria_id', (int) $filters['performance_criteria_id']))
            ->when(!empty($filters['assessee_profession_id'] ?? null), fn($q) => $q->where('assessee_profession_id', (int) $filters['assessee_profession_id']))
            ->when(!empty($filters['status'] ?? null), fn($q) => $q->where('status', (string) $filters['status']));

        // Table A: Draft & Periode Berjalan (active period only)
        $workingQuery = (clone $base)
            ->when(
                empty($filters['assessment_period_id'] ?? null) && $activePeriodId > 0,
                fn($q) => $q->where('assessment_period_id', $activePeriodId)
            )
            ->when(
                !empty($filters['assessment_period_id'] ?? null) && (int) $filters['assessment_period_id'] !== $activePeriodId,
                fn($q) => $q->whereRaw('1=0')
            )
            ->when(
                !empty($filters['assessment_period_id'] ?? null) && (int) $filters['assessment_period_id'] === $activePeriodId,
                fn($q) => $q->where('assessment_period_id', $activePeriodId)
            )
            ->when(
                empty($filters['assessment_period_id'] ?? null) && $activePeriodId <= 0,
                fn($q) => $q->whereRaw('1=0')
            )
            ->whereIn('status', [
                RaterWeightStatus::DRAFT->value,
                RaterWeightStatus::PENDING->value,
                RaterWeightStatus::ACTIVE->value,
            ])
            ->orderByDesc('id');

        // Table B: Riwayat
        // - Default (awal buka / "Semua"): tampilkan data selain periode aktif.
        // - Jika user memilih periode tertentu: tampilkan data periode tersebut.
        $historyQuery = (clone $base)
            ->whereIn('status', [
                RaterWeightStatus::ARCHIVED->value,
                RaterWeightStatus::REJECTED->value,
            ])
            ->when(
                !empty($filters['assessment_period_id'] ?? null) && (int) $filters['assessment_period_id'] !== $activePeriodId,
                fn($q) => $q->where('assessment_period_id', (int) $filters['assessment_period_id'])
            )
            ->when(
                !empty($filters['assessment_period_id'] ?? null) && (int) $filters['assessment_period_id'] === $activePeriodId,
                fn($q) => $q->where('assessment_period_id', $activePeriodId)
            )
            ->when(
                empty($filters['assessment_period_id'] ?? null) && $activePeriodId > 0,
                function ($q) use ($activePeriodId) {
                    $rejectedVal = RaterWeightStatus::REJECTED->value;
                    $q->where(function ($inner) use ($activePeriodId, $rejectedVal) {
                        $inner->where('assessment_period_id', '!=', $activePeriodId)
                              ->orWhere(function ($activeRejected) use ($activePeriodId, $rejectedVal) {
                                  $activeRejected->where('assessment_period_id', $activePeriodId)
                                                 ->where('status', $rejectedVal);
                              });
                    });
                }
            )
            ->orderByDesc('assessment_period_id')
            ->orderByDesc('id');

        $usingFallback = false;
        $workingCount = (clone $workingQuery)->count();
        if ($workingCount === 0 && $activePeriodId > 0) {
            $period = AssessmentPeriod::query()->find((int) $activePeriodId);
            if ($period && $period->isFrozen()) {
                $previous = $this->resolvePreviousPeriod($period);
                if ($previous) {
                    $hasWasActiveBefore = Schema::hasColumn('unit_rater_weights', 'was_active_before');
                    $workingQuery = (clone $base)
                        ->where('assessment_period_id', (int) $previous->id)
                        ->where(function ($q) use ($hasWasActiveBefore) {
                            $q->where('status', RaterWeightStatus::ACTIVE->value)
                                ->orWhere(function ($sub) use ($hasWasActiveBefore) {
                                    $sub->where('status', RaterWeightStatus::ARCHIVED->value);
                                    if ($hasWasActiveBefore) {
                                        $sub->where('was_active_before', 1);
                                    }
                                });
                        })
                        ->orderByDesc('id');
                    $usingFallback = true;
                }
            }
        }

        $itemsWorking = $workingQuery->paginate(20, ['*'], 'page_working')->withQueryString();
        $itemsHistory = $historyQuery->paginate(20, ['*'], 'page_history')->withQueryString();

        $tempWeights = (array) session('rater_weights.temp_weights', []);

        // Hydrate current page rows with cached weights to persist values across pagination before final submit.
        if (!empty($tempWeights)) {
            $itemsWorking->getCollection()->transform(function ($row) use ($tempWeights) {
                if (array_key_exists((string) $row->id, $tempWeights)) {
                    $row->weight = $tempWeights[(string) $row->id];
                }
                return $row;
            });
        }

        // Auto-100 lock helper: if a (period, criteria, assessee profession) group has exactly 1 row,
        // and that row is 100, we treat it as auto/single-line and lock it in UI + server.
        $groupCountsByKey = [];
        $workingKeys = $itemsWorking
            ->getCollection()
            ->map(fn($r) => (int) $r->assessment_period_id . ':' . (int) $r->performance_criteria_id . ':' . (int) $r->assessee_profession_id)
            ->unique()
            ->values()
            ->all();

        if (!empty($workingKeys) && Schema::hasTable('unit_rater_weights')) {
            $groupCountsByKey = DB::table('unit_rater_weights')
                ->where('unit_id', $unitId)
                ->whereIn(DB::raw("CONCAT(assessment_period_id,':',performance_criteria_id,':',assessee_profession_id)"), $workingKeys)
                ->selectRaw("CONCAT(assessment_period_id,':',performance_criteria_id,':',assessee_profession_id) as k, COUNT(*) as cnt")
                ->groupBy('k')
                ->pluck('cnt', 'k')
                ->mapWithKeys(fn($v, $k) => [(string) $k => (int) $v])
                ->all();
        }

        $criteriaOptions = PerformanceCriteria::query()
            ->when($hasRelevant360Criteria, fn($q) => $q->whereIn('id', $relevantCriteriaIds), fn($q) => $q->whereRaw('1=0'))
            ->orderBy('name')
            ->pluck('name', 'id');

        // Checklist for submit-all readiness: per (Criteria, Assessee Profession) must total 100%.
        $checklist = collect();
        if ($shouldSync) {
            $checklist = $this->raterWeightSummaryService->summarizeForUnitPeriod(
                unitId: $unitId,
                periodId: $currentPeriodId,
                criteriaIds: $relevantCriteriaIds,
                criteriaFilterId: !empty($filters['performance_criteria_id'] ?? null) ? (int) $filters['performance_criteria_id'] : null,
                professionFilterId: !empty($filters['assessee_profession_id'] ?? null) ? (int) $filters['assessee_profession_id'] : null,
                statuses: [RaterWeightStatus::DRAFT->value],
                weightOverrides: $tempWeights,
            );
        }

        if ($currentPeriodId > 0) {
            $parentCriteriaCounts = $this->countParent360CriteriaByStatus($unitId, $currentPeriodId);
            $raterWeightCounts = $this->countRaterWeightsByStatus($unitId, $currentPeriodId);

            Log::debug('RaterWeight index snapshot', [
                'unit_id' => $unitId,
                'assessment_period_id' => $currentPeriodId,
                'parent_360_criteria_count_by_status' => $parentCriteriaCounts,
                'draft_rater_weight_count' => $raterWeightCounts['draft'] ?? 0,
                'active_rater_weight_count' => $raterWeightCounts['active'] ?? 0,
                'archived_rater_weight_count' => $raterWeightCounts['archived'] ?? 0,
            ]);
        }

            $canSubmitAll = $checklist->isNotEmpty() && $checklist->every(fn($r) => (bool) ($r['ok'] ?? false));

            // Banner status (mimic Unit Criteria Weights): pending info / all-approved info.
            $pendingGroupCount = 0;
            $activeGroupCount = 0;
            $totalGroupCount = 0;
            $submittedGroupPercent = 0.0;
            $allGroupsActive = false;
            $hasAnyGroup = false;
            $rejectedWorkingCount = 0;

            if ($hasRelevant360Criteria && $currentPeriodId > 0 && !empty($relevantCriteriaIds)) {
                $pendingVal = RaterWeightStatus::PENDING->value;
                $activeVal = RaterWeightStatus::ACTIVE->value;
                $draftVal = RaterWeightStatus::DRAFT->value;
                $rejectedVal = RaterWeightStatus::REJECTED->value;

                $groupRows = DB::table('unit_rater_weights')
                    ->where('unit_id', $unitId)
                    ->where('assessment_period_id', $currentPeriodId)
                    ->whereIn('performance_criteria_id', $relevantCriteriaIds)
                    ->select(['performance_criteria_id', 'assessee_profession_id'])
                    // Avoid parameter placeholders in CASE for MariaDB compatibility.
                    ->selectRaw("SUM(CASE WHEN status = '{$pendingVal}' THEN 1 ELSE 0 END) as pending_cnt")
                    ->selectRaw("SUM(CASE WHEN status = '{$activeVal}' THEN 1 ELSE 0 END) as active_cnt")
                    ->selectRaw("SUM(CASE WHEN status = '{$draftVal}' THEN 1 ELSE 0 END) as draft_cnt")
                    ->selectRaw("SUM(CASE WHEN status = '{$rejectedVal}' THEN 1 ELSE 0 END) as rejected_cnt")
                    ->groupBy('performance_criteria_id', 'assessee_profession_id')
                    ->get();

                $totalGroupCount = (int) $groupRows->count();
                $hasAnyGroup = $totalGroupCount > 0;

                foreach ($groupRows as $g) {
                    $pendingCnt = (int) ($g->pending_cnt ?? 0);
                    $draftCnt = (int) ($g->draft_cnt ?? 0);
                    $rejectedCnt = (int) ($g->rejected_cnt ?? 0);
                    $activeCnt = (int) ($g->active_cnt ?? 0);

                    $rejectedWorkingCount += $rejectedCnt;

                    if ($pendingCnt > 0) {
                        $pendingGroupCount++;
                        continue;
                    }
                    if (($draftCnt + $rejectedCnt) > 0) {
                        continue;
                    }
                    if ($activeCnt > 0) {
                        $activeGroupCount++;
                    }
                }

                if ($totalGroupCount > 0) {
                    $submittedGroupPercent = (($pendingGroupCount + $activeGroupCount) / $totalGroupCount) * 100.0;
                    $allGroupsActive = ($activeGroupCount === $totalGroupCount) && $pendingGroupCount === 0;
                }
            }

            $canCopyPrevious = false;
            $activePeriodName = $activePeriod?->name;
            $previousPeriod = null;
            if ($activePeriod && $unitId > 0) {
                $previousPeriod = $this->previousPeriodWithRaterWeights($activePeriod, $unitId);
                $canCopyPrevious = (bool) $previousPeriod;
            }

            if ($allGroupsActive) {
                $canCopyPrevious = false;
            }

            $rejectedCountActive = 0;
            if ($currentPeriodId > 0) {
                $rejectedCountActive = (int) DB::table('unit_rater_weights')
                    ->where('unit_id', $unitId)
                    ->where('assessment_period_id', $currentPeriodId)
                    ->where('status', RaterWeightStatus::REJECTED->value)
                    ->count();
            }

        return view('kepala_unit.rater_weights.index', [
            'itemsWorking' => $itemsWorking,
            'itemsHistory' => $itemsHistory,
            'usingFallback' => $usingFallback,
            'hasFallbackActive' => $hasFallbackActive,
            'periods' => $periods,
            'criteriaOptions' => $criteriaOptions,
            'professions' => $professions,
            'assessorTypes' => self::ASSESSOR_TYPES,
            'statuses' => array_combine(
                array_map(fn($e) => $e->value, RaterWeightStatus::cases()),
                array_map(fn($e) => ucfirst($e->value), RaterWeightStatus::cases()),
            ),
            'filters' => $filters,
            'hasRules' => $hasRules,
            'singleRuleTypeByCriteriaId' => $singleRuleTypeByCriteriaId,
            'rulesUrl' => route('admin_rs.criteria_rater_rules.index'),
            'unitCriteriaWeightsUrl' => route('kepala_unit.unit-criteria-weights.index'),
            'hasProfession' => $hasProfession,
            'hasRelevant360Criteria' => $hasRelevant360Criteria,
            'currentPeriodId' => $currentPeriodId,
            'activePeriodId' => $activePeriodId,
            'activePeriodName' => $activePeriodName,
            'unitName' => $unitName,
            'submitChecklist' => $checklist,
            'groupCountsByKey' => $groupCountsByKey,
            'canSubmitAll' => $canSubmitAll,
            'canCopyPrevious' => $canCopyPrevious,
            'pendingGroupCount' => $pendingGroupCount,
            'activeGroupCount' => $activeGroupCount,
            'totalGroupCount' => $totalGroupCount,
            'submittedGroupPercent' => $submittedGroupPercent,
            'allGroupsActive' => $allGroupsActive,
            'hasAnyGroup' => $hasAnyGroup,
            'rejectedWorkingCount' => $rejectedWorkingCount,
            'rejectedCountActive' => $rejectedCountActive,
        ]);
    }

    /** Salin bobot aktif periode sebelumnya menjadi draft periode aktif (menimpa draft/rejected yang ada). */
    public function copyFromPrevious(Request $request): RedirectResponse
    {
        $this->authorizeAccess();

        $me = Auth::user();
        $unitId = (int) ($me?->unit_id ?? 0);
        if ($unitId <= 0) {
            abort(403);
        }

        if (!Schema::hasTable('assessment_periods') || !Schema::hasTable('unit_rater_weights')) {
            return back()->withErrors(['status' => 'Tabel periode atau bobot penilai belum tersedia.']);
        }

        $revisionPeriod = AssessmentPeriod::query()->where('status', AssessmentPeriod::STATUS_REVISION)->orderByDesc('id')->first();
        $activePeriod = AssessmentPeriod::query()->where('status', AssessmentPeriod::STATUS_ACTIVE)->orderByDesc('start_date')->first();
        $activePeriod = $revisionPeriod ?: $activePeriod;
        if (!$activePeriod) {
            return back()->withErrors(['status' => 'Tidak ada periode aktif/revisi.']);
        }

        AssessmentPeriodGuard::forbidWhenApprovalRejected($activePeriod, 'Salin Bobot Penilai 360');
        AssessmentPeriodGuard::requireActiveOrRevision($activePeriod, 'Salin Bobot Penilai 360');

        $previousPeriod = $this->previousPeriodWithRaterWeights($activePeriod, $unitId);
        if (!$previousPeriod) {
            return back()->withErrors(['status' => 'Tidak ada periode sebelumnya untuk disalin.']);
        }

        // Ensure destination rows exist.
        $this->raterWeightGenerator->syncForUnitPeriod($unitId, (int) $activePeriod->id);

        // Only copy for criteria that are relevant in the active period.
        $criteriaIds = $this->resolveActive360CriteriaIdsForUnit($unitId, (int) $activePeriod->id);
        if (empty($criteriaIds)) {
            return back()->withErrors(['status' => 'Bobot kriteria unit belum aktif, tidak dapat generate bobot penilai.']);
        }

        $this->ensureDraftRaterWeightsExists($unitId, (int) $activePeriod->id, $criteriaIds);

        // Source: prefer active rows; fallback progressively for demo/dev scenarios.
        $sourceStatusPriority = [
            RaterWeightStatus::ACTIVE->value,
            RaterWeightStatus::ARCHIVED->value,
            RaterWeightStatus::PENDING->value,
            RaterWeightStatus::DRAFT->value,
        ];

        $sourceRows = collect();
        foreach ($sourceStatusPriority as $st) {
            $q = DB::table('unit_rater_weights')
                ->where('unit_id', $unitId)
                ->where('assessment_period_id', (int) $previousPeriod->id)
                ->whereIn('performance_criteria_id', $criteriaIds)
                ->where('status', $st);

            // Untuk status archived, hanya gunakan baris yang memang pernah aktif.
            if ($st === RaterWeightStatus::ARCHIVED->value && Schema::hasColumn('unit_rater_weights', 'was_active_before')) {
                $q->where('was_active_before', 1);
            }

            $sourceRows = $q->get();
            if ($sourceRows->isNotEmpty()) {
                break;
            }
        }

        if ($sourceRows->isEmpty()) {
            return back()->withErrors(['status' => 'Tidak ada bobot aktif/arsip pada periode sebelumnya.']);
        }

        $makeKey = function ($r): string {
            $assessorProfessionId = (int) ($r->assessor_profession_id ?? 0);
            $assessorLevel = (int) ($r->assessor_level ?? 0);
            return (int) ($r->performance_criteria_id ?? 0)
                . ':' . (int) ($r->assessee_profession_id ?? 0)
                . ':' . (string) ($r->assessor_type ?? '')
                . ':' . $assessorProfessionId
                . ':' . $assessorLevel;
        };

        $sourceByKey = $sourceRows
            ->mapWithKeys(fn($r) => [$makeKey($r) => $r])
            ->all();

        $destRows = RaterWeight::query()
            ->where('unit_id', $unitId)
            ->where('assessment_period_id', (int) $activePeriod->id)
            ->whereIn('performance_criteria_id', $criteriaIds)
            ->whereIn('status', [RaterWeightStatus::DRAFT->value, RaterWeightStatus::REJECTED->value])
            ->get();

        $updated = 0;
        DB::transaction(function () use ($destRows, $sourceByKey, $makeKey, &$updated) {
            foreach ($destRows as $rw) {
                // Skip auto/single-line 100 (server-side safety).
                $groupCount = (int) RaterWeight::query()
                    ->where('assessment_period_id', (int) $rw->assessment_period_id)
                    ->where('unit_id', (int) $rw->unit_id)
                    ->where('performance_criteria_id', (int) $rw->performance_criteria_id)
                    ->where('assessee_profession_id', (int) $rw->assessee_profession_id)
                    ->count();
                $isAutoSingle = $groupCount === 1 && (float) ($rw->weight ?? 0) === 100.0;
                if ($isAutoSingle) {
                    continue;
                }

                $key = $makeKey($rw);
                if (!array_key_exists($key, $sourceByKey)) {
                    continue;
                }
                $src = $sourceByKey[$key];
                $rw->weight = $src->weight;
                $rw->status = RaterWeightStatus::DRAFT;
                $rw->proposed_by = null;
                $rw->decided_by = null;
                $rw->decided_at = null;
                $rw->decided_note = null;
                $rw->save();
                $updated++;
            }
        });

        // Clear temp cache: the copy action becomes the new baseline.
        session()->forget('rater_weights.temp_weights');

        if ($updated <= 0) {
            return back()->with('status', 'Tidak ada pasangan baris yang cocok. Aturan penilai pada periode ini berbeda dengan sumber yang disalin.');
        }

        return back()->with('status', "Berhasil menyalin bobot dari periode sebelumnya (diperbarui {$updated} baris draft)." );
    }

    /** Salin bobot ditolak (batch terakhir) menjadi draft periode aktif. */
    public function copyFromRejected(Request $request): RedirectResponse
    {
        $this->authorizeAccess();

        $me = Auth::user();
        $unitId = (int) ($me?->unit_id ?? 0);
        if ($unitId <= 0) {
            abort(403);
        }

        if (!Schema::hasTable('assessment_periods') || !Schema::hasTable('unit_rater_weights')) {
            return back()->withErrors(['status' => 'Tabel periode atau bobot penilai belum tersedia.']);
        }

        $revisionPeriod = AssessmentPeriod::query()->where('status', AssessmentPeriod::STATUS_REVISION)->orderByDesc('id')->first();
        $activePeriod = AssessmentPeriod::query()->where('status', AssessmentPeriod::STATUS_ACTIVE)->orderByDesc('start_date')->first();
        $activePeriod = $revisionPeriod ?: $activePeriod;
        if (!$activePeriod) {
            return back()->withErrors(['status' => 'Tidak ada periode aktif/revisi.']);
        }

        AssessmentPeriodGuard::forbidWhenApprovalRejected($activePeriod, 'Salin Bobot Ditolak');
        AssessmentPeriodGuard::requireActiveOrRevision($activePeriod, 'Salin Bobot Ditolak');

        $hasWorking = RaterWeight::query()
            ->where('unit_id', $unitId)
            ->where('assessment_period_id', (int) $activePeriod->id)
            ->whereIn('status', [RaterWeightStatus::DRAFT->value, RaterWeightStatus::PENDING->value, RaterWeightStatus::ACTIVE->value])
            ->exists();
        if ($hasWorking) {
            return back()->withErrors(['status' => 'Periode aktif sudah memiliki bobot. Hapus atau arsipkan terlebih dahulu.']);
        }

        // Ensure destination rows exist.
        $this->raterWeightGenerator->syncForUnitPeriod($unitId, (int) $activePeriod->id);

        $criteriaIds = $this->resolveActive360CriteriaIdsForUnit($unitId, (int) $activePeriod->id);
        if (empty($criteriaIds)) {
            return back()->withErrors(['status' => 'Bobot kriteria unit belum aktif, tidak dapat generate bobot penilai.']);
        }

        $latest = DB::table('unit_rater_weights')
            ->where('unit_id', $unitId)
            ->where('assessment_period_id', (int) $activePeriod->id)
            ->whereIn('performance_criteria_id', $criteriaIds)
            ->where('status', RaterWeightStatus::REJECTED->value)
            ->orderByRaw('COALESCE(decided_at, updated_at, created_at) DESC')
            ->first();

        if (!$latest) {
            return back()->withErrors(['status' => 'Tidak ada bobot ditolak pada periode aktif.']);
        }

        $latestDecidedAt = $latest->decided_at ?? null;
        $latestUpdatedAt = $latest->updated_at ?? $latest->created_at ?? null;

        $sourceRows = DB::table('unit_rater_weights')
            ->where('unit_id', $unitId)
            ->where('assessment_period_id', (int) $activePeriod->id)
            ->whereIn('performance_criteria_id', $criteriaIds)
            ->where('status', RaterWeightStatus::REJECTED->value)
            ->when($latestDecidedAt, fn($q) => $q->where('decided_at', $latestDecidedAt))
            ->when(!$latestDecidedAt && $latestUpdatedAt, fn($q) => $q->where('updated_at', $latestUpdatedAt))
            ->get();

        if ($sourceRows->isEmpty()) {
            return back()->withErrors(['status' => 'Tidak ada batch penolakan yang dapat disalin.']);
        }

        $this->ensureDraftRaterWeightsExists($unitId, (int) $activePeriod->id, $criteriaIds);

        $makeKey = function ($r): string {
            $assessorProfessionId = (int) ($r->assessor_profession_id ?? 0);
            $assessorLevel = (int) ($r->assessor_level ?? 0);
            return (int) ($r->performance_criteria_id ?? 0)
                . ':' . (int) ($r->assessee_profession_id ?? 0)
                . ':' . (string) ($r->assessor_type ?? '')
                . ':' . $assessorProfessionId
                . ':' . $assessorLevel;
        };

        $sourceByKey = $sourceRows
            ->mapWithKeys(fn($r) => [$makeKey($r) => $r])
            ->all();

        $destRows = RaterWeight::query()
            ->where('unit_id', $unitId)
            ->where('assessment_period_id', (int) $activePeriod->id)
            ->whereIn('performance_criteria_id', $criteriaIds)
            ->whereIn('status', [RaterWeightStatus::DRAFT->value, RaterWeightStatus::REJECTED->value])
            ->get();

        $updated = 0;
        DB::transaction(function () use ($destRows, $sourceByKey, $makeKey, &$updated) {
            foreach ($destRows as $rw) {
                $groupCount = (int) RaterWeight::query()
                    ->where('assessment_period_id', (int) $rw->assessment_period_id)
                    ->where('unit_id', (int) $rw->unit_id)
                    ->where('performance_criteria_id', (int) $rw->performance_criteria_id)
                    ->where('assessee_profession_id', (int) $rw->assessee_profession_id)
                    ->count();
                $isAutoSingle = $groupCount === 1 && (float) ($rw->weight ?? 0) === 100.0;
                if ($isAutoSingle) {
                    continue;
                }

                $key = $makeKey($rw);
                if (!array_key_exists($key, $sourceByKey)) {
                    continue;
                }
                $src = $sourceByKey[$key];
                $rw->weight = $src->weight;
                $rw->status = RaterWeightStatus::DRAFT;
                $rw->proposed_by = null;
                $rw->decided_by = null;
                $rw->decided_at = null;
                $rw->decided_note = null;
                $rw->save();
                $updated++;
            }
        });

        session()->forget('rater_weights.temp_weights');

        if ($updated <= 0) {
            return back()->with('status', 'Tidak ada pasangan baris yang cocok. Aturan penilai pada periode ini berbeda dengan sumber yang disalin.');
        }

        return back()->with('status', "Berhasil menyalin bobot ditolak terakhir (diperbarui {$updated} baris draft)." );
    }

    /** Ajukan perubahan saat seluruh bobot sudah aktif. */
    public function requestChange(Request $request): RedirectResponse
    {
        $this->authorizeAccess();

        $me = Auth::user();
        $unitId = (int) ($me?->unit_id ?? 0);
        if ($unitId <= 0) {
            abort(403);
        }

        $periodId = (int) ($request->input('assessment_period_id') ?: 0);
        if ($periodId <= 0) {
            $periodId = (int) (AssessmentPeriod::query()->where('status', AssessmentPeriod::STATUS_ACTIVE)->orderByDesc('start_date')->value('id') ?? 0);
        }
        if ($periodId <= 0) {
            return back()->withErrors(['assessment_period_id' => 'Tidak ada periode aktif untuk diajukan ulang.']);
        }

        $period = AssessmentPeriod::query()->find((int) $periodId);
        AssessmentPeriodGuard::forbidWhenApprovalRejected($period, 'Ajukan Perubahan Bobot Penilai 360');
        AssessmentPeriodGuard::requireActive($period, 'Ajukan Perubahan Bobot Penilai 360');

        $criteriaIds = $this->resolveActive360CriteriaIdsForUnit($unitId, (int) $periodId);
        if (empty($criteriaIds)) {
            return back()->withErrors(['status' => 'Bobot kriteria unit belum aktif, tidak dapat generate bobot penilai.']);
        }

        $activeRows = DB::table('unit_rater_weights')
            ->where('unit_id', $unitId)
            ->where('assessment_period_id', (int) $periodId)
            ->whereIn('performance_criteria_id', $criteriaIds)
            ->where('status', RaterWeightStatus::ACTIVE->value)
            ->get();

        if ($activeRows->isEmpty()) {
            return back()->withErrors(['status' => 'Tidak ada bobot aktif yang bisa diajukan ulang.']);
        }

        $hasPendingOrDraft = DB::table('unit_rater_weights')
            ->where('unit_id', $unitId)
            ->where('assessment_period_id', (int) $periodId)
            ->whereIn('performance_criteria_id', $criteriaIds)
            ->whereIn('status', [RaterWeightStatus::DRAFT->value, RaterWeightStatus::PENDING->value, RaterWeightStatus::REJECTED->value])
            ->exists();

        if ($hasPendingOrDraft) {
            return back()->withErrors(['status' => 'Masih ada bobot draft/pending/ditolak. Selesaikan terlebih dahulu sebelum mengajukan perubahan.']);
        }

        DB::transaction(function () use ($activeRows, $unitId, $periodId, $me) {
            $hasWasActiveBefore = Schema::hasColumn('unit_rater_weights', 'was_active_before');
            $now = now();

            $activeBefore = (int) $activeRows->count();
            $archivedDeleted = 0;
            $draftDeleted = 0;
            $archivedAfter = 0;
            $draftInserted = 0;

            $snapshot = $activeRows->map(fn($row) => [
                'id' => (int) $row->id,
                'assessment_period_id' => (int) $row->assessment_period_id,
                'unit_id' => (int) $row->unit_id,
                'performance_criteria_id' => (int) $row->performance_criteria_id,
                'assessee_profession_id' => (int) $row->assessee_profession_id,
                'assessor_type' => (string) $row->assessor_type,
                'assessor_profession_id' => $row->assessor_profession_id,
                'assessor_level' => $row->assessor_level,
                'weight' => $row->weight,
            ])->values()->all();

            foreach ($snapshot as $row) {
                $baseQuery = DB::table('unit_rater_weights')
                    ->where('assessment_period_id', (int) $periodId)
                    ->where('unit_id', $unitId)
                    ->where('performance_criteria_id', (int) $row['performance_criteria_id'])
                    ->where('assessee_profession_id', (int) $row['assessee_profession_id'])
                    ->where('assessor_type', (string) $row['assessor_type'])
                    ->where('assessor_profession_id', $row['assessor_profession_id'])
                    ->where('assessor_level', $row['assessor_level']);

                $archivedDeleted += (int) (clone $baseQuery)
                    ->where('status', RaterWeightStatus::ARCHIVED->value)
                    ->delete();

                $draftDeleted += (int) (clone $baseQuery)
                    ->where('status', RaterWeightStatus::DRAFT->value)
                    ->delete();
            }

            foreach ($snapshot as $row) {
                DB::table('unit_rater_weights')
                    ->where('id', (int) $row['id'])
                    ->update(array_filter([
                        'status' => RaterWeightStatus::ARCHIVED->value,
                        'was_active_before' => $hasWasActiveBefore ? 0 : null,
                        'updated_at' => $now,
                    ], fn($v) => $v !== null));
                $archivedAfter++;
            }

            foreach ($snapshot as $row) {
                DB::table('unit_rater_weights')->insert([
                    'assessment_period_id' => (int) $row['assessment_period_id'],
                    'unit_id' => (int) $row['unit_id'],
                    'performance_criteria_id' => (int) $row['performance_criteria_id'],
                    'assessee_profession_id' => (int) $row['assessee_profession_id'],
                    'assessor_type' => (string) $row['assessor_type'],
                    'assessor_profession_id' => $row['assessor_profession_id'],
                    'assessor_level' => $row['assessor_level'],
                    'weight' => $row['weight'],
                    'status' => RaterWeightStatus::DRAFT->value,
                    'was_active_before' => $hasWasActiveBefore ? 0 : null,
                    'proposed_by' => (int) ($me?->id ?? 0),
                    'decided_by' => null,
                    'decided_at' => null,
                    'decided_note' => null,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
                $draftInserted++;
            }

            Log::info('REQUEST_CHANGE_360', [
                'unit_id' => $unitId,
                'assessment_period_id' => $periodId,
                'active_before' => $activeBefore,
                'archived_conflicts_deleted' => $archivedDeleted,
                'archived_after' => $archivedAfter,
                'draft_after' => $draftInserted,
                'draft_deleted_conflicts' => $draftDeleted,
            ]);
        });

        session()->forget('rater_weights.temp_weights');

        return back()->with('status', 'Perubahan berhasil diajukan. Silakan revisi bobot pada draft, lalu ajukan kembali.');
    }

    public function updateInline(Request $request, RaterWeight $raterWeight): RedirectResponse|JsonResponse
    {
        $this->authorizeAccess();
        $this->authorizeOwnedByUnit($raterWeight);
        $this->authorizeDraftOnly($raterWeight);

        $period = AssessmentPeriod::query()->find((int) $raterWeight->assessment_period_id);
        AssessmentPeriodGuard::forbidWhenApprovalRejected($period, 'Ubah Bobot Penilai 360');
        AssessmentPeriodGuard::requireActiveOrRevision($period, 'Ubah Bobot Penilai 360');

        $data = $request->validate([
            'weight' => ['nullable', 'integer', 'min:0', 'max:100'],
        ]);

        // Prevent editing auto-100 single-line groups (only 1 row exists for the group).
        $groupCount = (int) RaterWeight::query()
            ->where('assessment_period_id', (int) $raterWeight->assessment_period_id)
            ->where('unit_id', (int) $raterWeight->unit_id)
            ->where('performance_criteria_id', (int) $raterWeight->performance_criteria_id)
            ->where('assessee_profession_id', (int) $raterWeight->assessee_profession_id)
            ->count();

        $isAutoSingle = $groupCount === 1 && (float) ($raterWeight->weight ?? 0) === 100.0;
        if ($isAutoSingle) {
            if ($request->expectsJson()) {
                return response()->json(['ok' => false, 'message' => 'Bobot ini otomatis 100% dan tidak dapat diedit.'], 422);
            }
            return back()->withErrors(['weight' => 'Bobot ini otomatis 100% dan tidak dapat diedit.']);
        }

        $raterWeight->weight = $data['weight'];
        $raterWeight->status = RaterWeightStatus::DRAFT;
        $raterWeight->proposed_by = null;
        $raterWeight->decided_by = null;
        $raterWeight->decided_at = null;
        $raterWeight->decided_note = null;
        $raterWeight->save();

        if ($request->expectsJson()) {
            $groupSum = (float) RaterWeight::query()
                ->where('assessment_period_id', (int) $raterWeight->assessment_period_id)
                ->where('unit_id', (int) $raterWeight->unit_id)
                ->where('performance_criteria_id', (int) $raterWeight->performance_criteria_id)
                ->where('assessee_profession_id', (int) $raterWeight->assessee_profession_id)
                ->sum(DB::raw('COALESCE(weight,0)'));

            $groupHasNull = RaterWeight::query()
                ->where('assessment_period_id', (int) $raterWeight->assessment_period_id)
                ->where('unit_id', (int) $raterWeight->unit_id)
                ->where('performance_criteria_id', (int) $raterWeight->performance_criteria_id)
                ->where('assessee_profession_id', (int) $raterWeight->assessee_profession_id)
                ->whereNull('weight')
                ->exists();

            return response()->json([
                'ok' => true,
                'message' => 'Bobot diperbarui (draft).',
                'weight' => (float) ($raterWeight->weight ?? 0),
                'group' => [
                    'key' => (int) $raterWeight->performance_criteria_id . ':' . (int) $raterWeight->assessee_profession_id,
                    'sum' => $groupSum,
                    'has_null' => (bool) $groupHasNull,
                    'ok' => !$groupHasNull && ((int) round($groupSum, 0) === 100),
                ],
            ]);
        }
        return back()->with('status', 'Bobot diperbarui (draft).');
    }

    public function submitAll(Request $request): RedirectResponse
    {
        $this->authorizeAccess();

        $me = Auth::user();
        $unitId = (int) ($me?->unit_id ?? 0);
        if ($unitId <= 0) {
            abort(403);
        }

        $periodId = (int) ($request->input('assessment_period_id') ?: 0);
        if ($periodId <= 0) {
            $revisionId = (int) (AssessmentPeriod::query()->where('status', AssessmentPeriod::STATUS_REVISION)->orderByDesc('id')->value('id') ?? 0);
            $periodId = $revisionId > 0
                ? $revisionId
                : (int) (AssessmentPeriod::query()->where('status', AssessmentPeriod::STATUS_ACTIVE)->orderByDesc('start_date')->value('id') ?? 0);
        }
        if ($periodId <= 0) {
            return back()->withErrors(['assessment_period_id' => 'Tidak ada periode aktif/revisi.']);
        }

        $period = AssessmentPeriod::query()->find((int) $periodId);
        AssessmentPeriodGuard::forbidWhenApprovalRejected($period, 'Ajukan Bobot Penilai 360');
        AssessmentPeriodGuard::requireActiveOrRevision($period, 'Ajukan Bobot Penilai 360');

        // Block submission if any related unit criteria weights are not approved.
        $hasUnapproved = DB::table('unit_criteria_weights as ucw')
            ->join('performance_criterias as pc', 'pc.id', '=', 'ucw.performance_criteria_id')
            ->where('ucw.unit_id', $unitId)
            ->where('ucw.assessment_period_id', $periodId)
            ->whereIn('ucw.status', ['draft', 'pending', 'rejected'])
            ->where('pc.is_360', 1)
            ->where('pc.is_active', 1)
            ->exists();

        if ($hasUnapproved) {
            return back()->withErrors(['status' => 'Bobot penilai 360 belum dapat diajukan: bobot kriteria unit masih draft/pending/ditolak.']);
        }

        $criteriaIds = DB::table('unit_criteria_weights as ucw')
            ->join('performance_criterias as pc', 'pc.id', '=', 'ucw.performance_criteria_id')
            ->join('criteria_rater_rules as crr', 'crr.performance_criteria_id', '=', 'pc.id')
            ->where('ucw.unit_id', $unitId)
            ->where('ucw.assessment_period_id', $periodId)
            ->where('ucw.status', 'active')
            ->where('pc.is_360', 1)
            ->where('pc.is_active', 1)
            ->distinct()
            ->pluck('pc.id')
            ->map(fn($v) => (int) $v)
            ->values()
            ->all();

        if (empty($criteriaIds)) {
            return back()->withErrors(['status' => 'Tidak ada kriteria 360 aktif untuk unit pada periode ini.']);
        }

        // Ensure rows exist before validation
        $this->raterWeightGenerator->syncForUnitPeriod($unitId, $periodId);

        // Build allowed assessor types per criteria
        $ruleTypesByCriteria = DB::table('criteria_rater_rules')
            ->whereIn('performance_criteria_id', $criteriaIds)
            ->get(['performance_criteria_id', 'assessor_type'])
            ->groupBy('performance_criteria_id')
            ->map(fn($rows) => $rows->pluck('assessor_type')->filter()->unique()->values()->all())
            ->all();

        $rows = RaterWeight::query()
            ->with(['criteria:id,name', 'assesseeProfession:id,name', 'assessorProfession:id,name', 'unit:id,name'])
            ->where('assessment_period_id', $periodId)
            ->where('unit_id', $unitId)
            ->whereIn('performance_criteria_id', $criteriaIds)
            ->whereIn('status', [RaterWeightStatus::DRAFT->value])
            ->get();

        if ($rows->isEmpty()) {
            return back()->withErrors(['status' => 'Tidak ada bobot draft yang bisa diajukan.']);
        }

        // Validate totals per (unit, period, criteria, profession)
        $errors = [];
        $groups = $rows->groupBy(fn($r) => (int) $r->performance_criteria_id . ':' . (int) $r->assessee_profession_id);

        foreach ($groups as $key => $groupRows) {
            [$criteriaIdStr, $professionIdStr] = explode(':', (string) $key);
            $criteriaId = (int) $criteriaIdStr;
            $professionId = (int) $professionIdStr;

            // When hierarchy master expands supervisor levels, there can be multiple rows per assessor_type.
            $sum = (float) $groupRows->sum(fn($r) => (float) ($r->weight ?? 0));
            $hasNull = $groupRows->contains(fn($r) => $r->weight === null);

            if ($hasNull) {
                $cName = (string) ($groupRows->first()?->criteria?->name ?? ('Kriteria #' . $criteriaId));
                $pName = (string) ($groupRows->first()?->assesseeProfession?->name ?? ('Profesi #' . $professionId));
                $uName = (string) ($groupRows->first()?->unit?->name ?? 'Unit');
                $errors[] = sprintf('Masih ada bobot kosong untuk %s / %s / %s. Lengkapi hingga total 100%%.', $uName, $cName, $pName);
                continue;
            }

            if ((int) round($sum, 2) !== 100) {
                $cName = (string) ($groupRows->first()?->criteria?->name ?? ('Kriteria #' . $criteriaId));
                $pName = (string) ($groupRows->first()?->assesseeProfession?->name ?? ('Profesi #' . $professionId));
                $uName = (string) ($groupRows->first()?->unit?->name ?? 'Unit');
                $errors[] = sprintf('Total bobot harus 100%% untuk %s / %s / %s (saat ini %.2f%%).', $uName, $cName, $pName, $sum);
            }
        }

        if (!empty($errors)) {
            return back()->withErrors($errors);
        }

        // Submit all draft rows for this unit+period+criteria set
        DB::transaction(function () use ($rows) {
            foreach ($rows as $rw) {
                $rw->status = RaterWeightStatus::PENDING;
                $rw->proposed_by = auth()->id();
                $rw->decided_by = null;
                $rw->decided_at = null;
                $rw->decided_note = null;
                $rw->save();
            }
        });

        // Clear temporary cache setelah submit semua.
        session()->forget('rater_weights.temp_weights');

        return back()->with('status', 'Semua bobot penilai 360 berhasil diajukan (pending).');
    }

    public function bulkCheck(Request $request): RedirectResponse
    {
        $this->authorizeAccess();

        $me = Auth::user();
        $unitId = (int) ($me?->unit_id ?? 0);
        if ($unitId <= 0) {
            abort(403);
        }

        $data = $request->validate([
            'weights' => ['required', 'array'],
            'weights.*' => ['nullable', 'integer', 'min:0', 'max:100'],
        ]);

        /** @var array<string, int|null> $weights */
        $weights = $data['weights'] ?? [];
        if (empty($weights)) {
            return back();
        }

        // Only allow updating rows owned by this unit and in draft.
        $ids = collect(array_keys($weights))
            ->map(fn($v) => (int) $v)
            ->filter(fn($v) => $v > 0)
            ->values()
            ->all();

        if (empty($ids)) {
            return back();
        }

        $rows = RaterWeight::query()
            ->where('unit_id', $unitId)
            ->whereIn('id', $ids)
            ->get();

        $periodIds = $rows->pluck('assessment_period_id')->filter()->unique()->values();
        foreach ($periodIds as $pid) {
            $period = AssessmentPeriod::query()->find((int) $pid);
            AssessmentPeriodGuard::forbidWhenApprovalRejected($period, 'Ubah Bobot Penilai 360');
            AssessmentPeriodGuard::requireActiveOrRevision($period, 'Ubah Bobot Penilai 360');
        }

        // Fail fast if user attempts to update rows outside the allowed set.
        if ($rows->count() !== count($ids)) {
            abort(403);
        }

        $errors = [];

        // Cache sementara lintas halaman: simpan input terakhir per ID.
        $temp = (array) session('rater_weights.temp_weights', []);

        DB::transaction(function () use ($rows, $weights, &$errors) {
            foreach ($rows as $raterWeight) {
                // status guard (same as updateInline)
                if (!($raterWeight->status === RaterWeightStatus::DRAFT)) {
                    $errors[] = 'Ada baris yang tidak bisa diedit karena statusnya bukan draft.';
                    continue;
                }

                $incoming = $weights[(string) $raterWeight->id] ?? null;

                // Prevent editing auto-100 single-line groups (only 1 row exists for the group).
                $groupCount = (int) RaterWeight::query()
                    ->where('assessment_period_id', (int) $raterWeight->assessment_period_id)
                    ->where('unit_id', (int) $raterWeight->unit_id)
                    ->where('performance_criteria_id', (int) $raterWeight->performance_criteria_id)
                    ->where('assessee_profession_id', (int) $raterWeight->assessee_profession_id)
                    ->count();

                $isAutoSingle = $groupCount === 1 && (float) ($raterWeight->weight ?? 0) === 100.0;
                if ($isAutoSingle) {
                    continue;
                }

                // Simpan sementara ke session cache (lintas halaman).
                session()->put("rater_weights.temp_weights.{$raterWeight->id}", $incoming);

                $raterWeight->weight = $incoming;
                $raterWeight->status = RaterWeightStatus::DRAFT;
                $raterWeight->proposed_by = null;
                $raterWeight->decided_by = null;
                $raterWeight->decided_at = null;
                $raterWeight->save();
            }
        });

        if (!empty($errors)) {
            return back()->withErrors($errors);
        }

        return back()->with('status', 'Cek berhasil: semua nilai bobot pada halaman ini sudah disimpan sebagai draft.');
    }

    private function authorizeOwnedByUnit(RaterWeight $raterWeight): void
    {
        $me = Auth::user();
        $unitId = (int) ($me?->unit_id ?? 0);
        abort_unless((int) $raterWeight->unit_id === $unitId, 403);
    }

    private function authorizeDraftOnly(RaterWeight $raterWeight): void
    {
        abort_unless($raterWeight->status === RaterWeightStatus::DRAFT, 403);
    }

    private function authorizeAccess(): void
    {
        $me = Auth::user();
        abort_unless($me && (string) $me->role === 'kepala_unit', 403);
    }


    private function ensureDraftRaterWeightsExists(int $unitId, int $periodId, array $criteriaIds): void
    {
        if ($unitId <= 0 || $periodId <= 0 || empty($criteriaIds)) {
            Log::warning('ensureDraftRaterWeightsExists: missing criteriaIds for generation.', [
                'unit_id' => $unitId,
                'assessment_period_id' => $periodId,
            ]);
            throw ValidationException::withMessages([
                'status' => 'Bobot kriteria unit belum aktif, tidak dapat generate bobot penilai.'
            ]);
        }

        $hasWorking = RaterWeight::query()
            ->where('unit_id', $unitId)
            ->where('assessment_period_id', $periodId)
            ->whereIn('performance_criteria_id', $criteriaIds)
            ->whereIn('status', [RaterWeightStatus::DRAFT->value, RaterWeightStatus::PENDING->value])
            ->exists();

        if ($hasWorking) {
            return;
        }

        $this->raterWeightGenerator->syncForUnitPeriod($unitId, $periodId);
    }

    /**
     * Resolve criteria IDs for ACTIVE unit criteria weights only.
     *
     * @return array<int>
     */
    private function resolveActive360CriteriaIdsForUnit(int $unitId, int $periodId): array
    {
        if ($unitId <= 0 || $periodId <= 0) {
            return [];
        }

        if (!Schema::hasTable('unit_criteria_weights') || !Schema::hasTable('performance_criterias')) {
            return [];
        }

        return DB::table('unit_criteria_weights as ucw')
            ->join('performance_criterias as pc', 'pc.id', '=', 'ucw.performance_criteria_id')
            ->where('ucw.unit_id', $unitId)
            ->where('ucw.assessment_period_id', $periodId)
            ->where('ucw.status', 'active')
            ->where('pc.is_360', 1)
            ->where('pc.is_active', 1)
            ->distinct()
            ->pluck('pc.id')
            ->map(fn($v) => (int) $v)
            ->filter(fn($v) => $v > 0)
            ->values()
            ->all();
    }

    private function archiveNonActivePeriods(int $unitId, int $activePeriodId): void
    {
        if ($unitId <= 0) return;
        if (!Schema::hasTable('unit_rater_weights')) return;

        $hasWasActiveBefore = Schema::hasColumn('unit_rater_weights', 'was_active_before');

        // If there's no active period, treat all existing rater weights as historical.
        if ($activePeriodId <= 0) {
            $updates = [
                'status' => RaterWeightStatus::ARCHIVED->value,
                'updated_at' => now(),
            ];
            if ($hasWasActiveBefore) {
                $updates['was_active_before'] = DB::raw("CASE WHEN status='active' THEN 1 ELSE was_active_before END");
            }

            DB::table('unit_rater_weights')
                ->where('unit_id', $unitId)
                ->where('status', '!=', RaterWeightStatus::ARCHIVED->value)
                ->update($updates);
            return;
        }

        if (!Schema::hasTable('assessment_periods')) return;

        DB::table('unit_rater_weights')
            ->join('assessment_periods as ap', 'ap.id', '=', 'unit_rater_weights.assessment_period_id')
            ->where('unit_rater_weights.unit_id', $unitId)
            ->where('unit_rater_weights.status', '!=', RaterWeightStatus::ARCHIVED->value)
            ->where('unit_rater_weights.assessment_period_id', '!=', $activePeriodId)
            ->whereNotIn('ap.status', [AssessmentPeriod::STATUS_ACTIVE, AssessmentPeriod::STATUS_REVISION])
            ->update(array_filter([
                'unit_rater_weights.status' => RaterWeightStatus::ARCHIVED->value,
                'unit_rater_weights.was_active_before' => $hasWasActiveBefore
                    ? DB::raw("CASE WHEN unit_rater_weights.status='active' THEN 1 ELSE unit_rater_weights.was_active_before END")
                    : null,
                'unit_rater_weights.updated_at' => now(),
            ], fn($v) => $v !== null));
    }

    /**
     * @return array<int>
     */
    private function resolveProfessionIdsForUnit(int $unitId): array
    {
        if ($unitId <= 0 || !Schema::hasTable('users') || !Schema::hasTable('professions')) {
            return [];
        }

        return DB::table('users')
            ->where('unit_id', $unitId)
            ->whereNotNull('profession_id')
            ->distinct()
            ->pluck('profession_id')
            ->map(fn($v) => (int) $v)
            ->filter(fn($v) => $v > 0)
            ->values()
            ->all();
    }

    /**
     * 360 criteria considered "relevant" for this unit+period are those present in unit_criteria_weights
     * (non-archived) AND marked is_360.
     * @return array<int, int>
     */
    private function resolveRelevant360CriteriaIdsForUnit(int $unitId, int $periodId): array
    {
        if ($unitId <= 0 || $periodId <= 0) {
            return [];
        }
        if (!Schema::hasTable('unit_criteria_weights') || !Schema::hasTable('performance_criterias')) {
            return [];
        }

        $rows = DB::table('unit_criteria_weights as ucw')
            ->join('performance_criterias as pc', 'pc.id', '=', 'ucw.performance_criteria_id')
            ->where('ucw.unit_id', $unitId)
            ->where('ucw.assessment_period_id', $periodId)
            // Consider criteria selected for this period (draft/pending/active).
            ->whereIn('ucw.status', ['draft', 'pending', 'active'])
            ->where('pc.is_360', 1)
            ->where('pc.is_active', 1)
            ->distinct()
            ->pluck('pc.id')
            ->map(fn($v) => (int) $v)
            ->values()
            ->all();

        return $rows;
    }

    private function sync360DraftForPeriod(int $unitId, int $periodId): void
    {
        if ($unitId <= 0 || $periodId <= 0) {
            return;
        }

        if (!Schema::hasTable('unit_criteria_weights') || !Schema::hasTable('performance_criterias') || !Schema::hasTable('unit_rater_weights')) {
            return;
        }

        $parentRows = DB::table('unit_criteria_weights as ucw')
            ->join('performance_criterias as pc', 'pc.id', '=', 'ucw.performance_criteria_id')
            ->where('ucw.unit_id', $unitId)
            ->where('ucw.assessment_period_id', $periodId)
            ->whereIn('ucw.status', ['draft', 'pending', 'active'])
            ->where('pc.is_360', 1)
            ->where('pc.is_active', 1)
            ->get(['ucw.performance_criteria_id', 'ucw.status']);

        if ($parentRows->isEmpty()) {
            return;
        }

        $draftCriteriaIds = $parentRows
            ->where('status', 'draft')
            ->pluck('performance_criteria_id')
            ->map(fn($v) => (int) $v)
            ->filter(fn($v) => $v > 0)
            ->values()
            ->all();

        if (!empty($draftCriteriaIds)) {
            $hasWasActiveBefore = Schema::hasColumn('unit_rater_weights', 'was_active_before');
            $now = now();

            DB::transaction(function () use ($unitId, $periodId, $draftCriteriaIds, $hasWasActiveBefore, $now) {
                $toArchive = DB::table('unit_rater_weights')
                    ->where('unit_id', $unitId)
                    ->where('assessment_period_id', $periodId)
                    ->whereIn('performance_criteria_id', $draftCriteriaIds)
                    ->whereIn('status', [RaterWeightStatus::ACTIVE->value, RaterWeightStatus::PENDING->value])
                    ->get();

                if ($toArchive->isEmpty()) {
                    Log::debug('SYNC360: no rows to archive', [
                        'unit_id' => $unitId,
                        'assessment_period_id' => $periodId,
                        'criteria_ids' => $draftCriteriaIds,
                    ]);
                    return;
                }

                $snapshot = $toArchive->map(fn($row) => [
                    'id' => (int) $row->id,
                    'assessment_period_id' => (int) $row->assessment_period_id,
                    'unit_id' => (int) $row->unit_id,
                    'performance_criteria_id' => (int) $row->performance_criteria_id,
                    'assessee_profession_id' => (int) $row->assessee_profession_id,
                    'assessor_type' => (string) $row->assessor_type,
                    'assessor_profession_id' => $row->assessor_profession_id,
                    'assessor_level' => $row->assessor_level,
                    'weight' => $row->weight,
                ])->values()->all();

                $archivedConflictsDeleted = 0;
                $draftDeleted = 0;
                $archivedUpdated = 0;
                $draftInserted = 0;

                foreach ($snapshot as $row) {
                    $baseQuery = DB::table('unit_rater_weights')
                        ->where('assessment_period_id', (int) $periodId)
                        ->where('unit_id', $unitId)
                        ->where('performance_criteria_id', (int) $row['performance_criteria_id'])
                        ->where('assessee_profession_id', (int) $row['assessee_profession_id'])
                        ->where('assessor_type', (string) $row['assessor_type'])
                        ->where('assessor_profession_id', $row['assessor_profession_id'])
                        ->where('assessor_level', $row['assessor_level']);

                    $archivedConflictsDeleted += (int) (clone $baseQuery)
                        ->where('status', RaterWeightStatus::ARCHIVED->value)
                        ->delete();

                    $draftDeleted += (int) (clone $baseQuery)
                        ->where('status', RaterWeightStatus::DRAFT->value)
                        ->delete();
                }

                foreach ($snapshot as $row) {
                    DB::table('unit_rater_weights')
                        ->where('id', (int) $row['id'])
                        ->update(array_filter([
                            'status' => RaterWeightStatus::ARCHIVED->value,
                            'was_active_before' => $hasWasActiveBefore ? 0 : null,
                            'updated_at' => $now,
                        ], fn($v) => $v !== null));
                    $archivedUpdated++;
                }

                foreach ($snapshot as $row) {
                    DB::table('unit_rater_weights')->insert([
                        'assessment_period_id' => (int) $row['assessment_period_id'],
                        'unit_id' => (int) $row['unit_id'],
                        'performance_criteria_id' => (int) $row['performance_criteria_id'],
                        'assessee_profession_id' => (int) $row['assessee_profession_id'],
                        'assessor_type' => (string) $row['assessor_type'],
                        'assessor_profession_id' => $row['assessor_profession_id'],
                        'assessor_level' => $row['assessor_level'],
                        'weight' => $row['weight'],
                        'status' => RaterWeightStatus::DRAFT->value,
                        'was_active_before' => $hasWasActiveBefore ? 0 : null,
                        'proposed_by' => null,
                        'decided_by' => null,
                        'decided_at' => null,
                        'decided_note' => null,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ]);
                    $draftInserted++;
                }

                Log::debug('SYNC360: archive and draft refresh', [
                    'unit_id' => $unitId,
                    'assessment_period_id' => $periodId,
                    'to_archive_count' => count($snapshot),
                    'archived_conflicts_deleted' => $archivedConflictsDeleted,
                    'archived_updated' => $archivedUpdated,
                    'draft_deleted' => $draftDeleted,
                    'draft_inserted' => $draftInserted,
                ]);
            });
        }

        $this->raterWeightGenerator->syncForUnitPeriod($unitId, $periodId);
    }

    private function countParent360CriteriaByStatus(int $unitId, int $periodId): array
    {
        if ($unitId <= 0 || $periodId <= 0) {
            return [];
        }
        if (!Schema::hasTable('unit_criteria_weights') || !Schema::hasTable('performance_criterias')) {
            return [];
        }

        return DB::table('unit_criteria_weights as ucw')
            ->join('performance_criterias as pc', 'pc.id', '=', 'ucw.performance_criteria_id')
            ->where('ucw.unit_id', $unitId)
            ->where('ucw.assessment_period_id', $periodId)
            ->where('pc.is_360', 1)
            ->where('pc.is_active', 1)
            ->groupBy('ucw.status')
            ->selectRaw('ucw.status, COUNT(*) as cnt')
            ->pluck('cnt', 'status')
            ->mapWithKeys(fn($v, $k) => [(string) $k => (int) $v])
            ->all();
    }

    private function countRaterWeightsByStatus(int $unitId, int $periodId): array
    {
        if ($unitId <= 0 || $periodId <= 0) {
            return [];
        }
        if (!Schema::hasTable('unit_rater_weights')) {
            return [];
        }

        return DB::table('unit_rater_weights')
            ->where('unit_id', $unitId)
            ->where('assessment_period_id', $periodId)
            ->groupBy('status')
            ->selectRaw('status, COUNT(*) as cnt')
            ->pluck('cnt', 'status')
            ->mapWithKeys(fn($v, $k) => [(string) $k => (int) $v])
            ->all();
    }

    /**
     * Allowed assessor types are derived from criteria_rater_rules for the given criteria ids.
     * @param array<int, int> $criteriaIds
     * @return array<int, string>
     */
    private function resolveAllowedAssessorTypes(array $criteriaIds): array
    {
        if (empty($criteriaIds)) {
            return [];
        }
        if (!Schema::hasTable('criteria_rater_rules')) {
            return [];
        }

        return CriteriaRaterRule::query()
            ->whereIn('performance_criteria_id', $criteriaIds)
            ->distinct()
            ->orderBy('assessor_type')
            ->pluck('assessor_type')
            ->map(fn($v) => (string) $v)
            ->values()
            ->all();
    }

    private function resolveLatestPeriodIdWithUnit360Criteria(int $unitId): ?int
    {
        if ($unitId <= 0) {
            return null;
        }
        if (!Schema::hasTable('unit_criteria_weights') || !Schema::hasTable('performance_criterias') || !Schema::hasTable('assessment_periods')) {
            return null;
        }

        $periodId = DB::table('unit_criteria_weights as ucw')
            ->join('performance_criterias as pc', 'pc.id', '=', 'ucw.performance_criteria_id')
            ->join('assessment_periods as ap', 'ap.id', '=', 'ucw.assessment_period_id')
            ->where('ucw.unit_id', $unitId)
            ->where('ucw.status', '!=', 'archived')
            ->where('pc.is_360', 1)
            ->where('pc.is_active', 1)
            ->orderByDesc('ap.start_date')
            ->value('ap.id');

        return $periodId ? (int) $periodId : null;
    }

    private function previousPeriodWithRaterWeights(AssessmentPeriod $activePeriod, int $unitId)
    {
        if ($unitId <= 0) {
            return null;
        }
        if (!Schema::hasTable('assessment_periods') || !Schema::hasTable('unit_rater_weights')) {
            return null;
        }

        // Backfill legacy archived rows: if a row was approved (decided_by set), it used to be active.
        // This makes the "Salin periode sebelumnya" button appear even for older data.
        if (Schema::hasColumn('unit_rater_weights', 'was_active_before')) {
            DB::table('unit_rater_weights')
                ->where('unit_id', $unitId)
                ->where('status', 'archived')
                ->where('was_active_before', 0)
                ->where(function ($q) {
                    $q->whereNotNull('decided_by')
                      ->orWhereNotNull('decided_at');
                })
                ->update([
                    'was_active_before' => 1,
                    'updated_at' => now(),
                ]);
        }

        $periodStatuses = ['active', 'locked', 'approval', 'closed', 'archived'];

        $query = DB::table('assessment_periods')
            ->where('id', '!=', (int) $activePeriod->id)
            ->whereIn('status', $periodStatuses);

        if (Schema::hasColumn('assessment_periods', 'start_date') && !empty($activePeriod->start_date)) {
            $query->where('start_date', '<', $activePeriod->start_date)
                ->orderByDesc('start_date');
        } else {
            $query->where('id', '<', (int) $activePeriod->id)
                ->orderByDesc('id');
        }

        // Find the closest previous period that has rater weights for this unit.
        // If was_active_before exists, use it as the definitive signal.
        $hasWasActiveBefore = Schema::hasColumn('unit_rater_weights', 'was_active_before');

        return $query
            ->whereExists(function ($sub) use ($unitId, $hasWasActiveBefore) {
                $sub->select(DB::raw(1))
                    ->from('unit_rater_weights')
                    ->whereColumn('unit_rater_weights.assessment_period_id', 'assessment_periods.id')
                    ->where('unit_rater_weights.unit_id', $unitId);

                if ($hasWasActiveBefore) {
                    $sub->where('unit_rater_weights.was_active_before', 1);
                } else {
                    $sub->whereIn('unit_rater_weights.status', ['active', 'archived']);
                }
            })
            ->first();
    }

    private function resolvePreviousPeriod(AssessmentPeriod $period): ?object
    {
        if (!Schema::hasTable('assessment_periods')) {
            return null;
        }

        $query = DB::table('assessment_periods')
            ->where('id', '!=', (int) $period->id)
            ->whereIn('status', [
                AssessmentPeriod::STATUS_LOCKED,
                AssessmentPeriod::STATUS_APPROVAL,
                AssessmentPeriod::STATUS_CLOSED,
            ]);

        if (Schema::hasColumn('assessment_periods', 'end_date') && Schema::hasColumn('assessment_periods', 'start_date') && !empty($period->start_date)) {
            $query->where('end_date', '<', $period->start_date)
                ->orderByDesc('end_date');
        } else {
            $query->where('id', '<', (int) $period->id)
                ->orderByDesc('id');
        }

        return $query->first();
    }
}
