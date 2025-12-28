<?php

namespace App\Http\Controllers\Web\UnitHead;

use App\Enums\RaterWeightStatus;
use App\Http\Controllers\Controller;
use App\Models\AssessmentPeriod;
use App\Models\CriteriaRaterRule;
use App\Models\PerformanceCriteria;
use App\Models\Profession;
use App\Models\RaterWeight;
use App\Services\RaterWeightGenerator;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
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

    public function index(Request $request): View
    {
        $this->authorizeAccess();

        $filters = $request->validate([
            'assessment_period_id' => ['nullable', 'integer'],
            'performance_criteria_id' => ['nullable', 'integer'],
            'assessee_profession_id' => ['nullable', 'integer'],
            'assessor_level' => ['nullable', 'integer', 'min:1', 'max:10'],
            'status' => ['nullable', Rule::in(array_map(fn($e) => $e->value, RaterWeightStatus::cases()))],
        ]);

        $periods = AssessmentPeriod::orderByDesc('start_date')->get();

        $me = Auth::user();
        $unitId = (int) ($me?->unit_id ?? 0);

        $professionIds = $this->resolveProfessionIdsForUnit($unitId);
        $professions = Profession::query()
            ->when(!empty($professionIds), fn($q) => $q->whereIn('id', $professionIds))
            ->orderBy('name')
            ->get(['id', 'name']);

        $activePeriodId = (int) (AssessmentPeriod::query()->active()->orderByDesc('start_date')->value('id') ?? 0);
        $fallbackPeriodId = (int) (AssessmentPeriod::query()->orderByDesc('start_date')->value('id') ?? 0);
        $currentPeriodId = (int) (($filters['assessment_period_id'] ?? null) ?: ($activePeriodId ?: $fallbackPeriodId));

        $hasProfession = $professions->isNotEmpty();
        $relevantCriteriaIds = $this->resolveRelevant360CriteriaIdsForUnit($unitId, $currentPeriodId);
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

        if ($hasRelevant360Criteria && $hasRules && $hasProfession) {
            app(RaterWeightGenerator::class)->syncForUnitPeriod($unitId, $currentPeriodId);
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
            ->when(!empty($filters['assessor_level'] ?? null), function ($q) use ($filters) {
                $q->where('assessor_type', 'supervisor')
                    ->where('assessor_level', (int) $filters['assessor_level']);
            })
            ->when(!empty($filters['status'] ?? null), fn($q) => $q->where('status', (string) $filters['status']));

        // If user picks a specific period filter, respect it for both tables.
        if (!empty($filters['assessment_period_id'] ?? null)) {
            $base->where('assessment_period_id', (int) $filters['assessment_period_id']);
        }

        // Table A: Draft & Periode Berjalan (default period = active)
        $workingQuery = (clone $base)
            ->when(empty($filters['assessment_period_id'] ?? null) && $currentPeriodId > 0, fn($q) => $q->where('assessment_period_id', $currentPeriodId))
            ->whereIn('status', [
                RaterWeightStatus::DRAFT->value,
                RaterWeightStatus::REJECTED->value,
                RaterWeightStatus::PENDING->value,
                RaterWeightStatus::ACTIVE->value,
            ])
            ->orderByDesc('id');

        // Table B: Riwayat (archived OR outside current period)
        $historyQuery = (clone $base)
            ->where(function ($q) use ($filters, $currentPeriodId) {
                $q->where('status', RaterWeightStatus::ARCHIVED->value);

                if (empty($filters['assessment_period_id'] ?? null) && $currentPeriodId > 0) {
                    $q->orWhere('assessment_period_id', '!=', $currentPeriodId);
                }
            })
            ->orderByDesc('id');

        $itemsWorking = $workingQuery->paginate(20, ['*'], 'page_working')->withQueryString();
        $itemsHistory = $historyQuery->paginate(20, ['*'], 'page_history')->withQueryString();

        $criteriaOptions = PerformanceCriteria::query()
            ->when($hasRelevant360Criteria, fn($q) => $q->whereIn('id', $relevantCriteriaIds), fn($q) => $q->whereRaw('1=0'))
            ->orderBy('name')
            ->pluck('name', 'id');

        $supervisorLevelOptions = [
            1 => 'Atasan L1',
            2 => 'Atasan L2',
            3 => 'Atasan L3',
        ];

        return view('kepala_unit.rater_weights.index', [
            'itemsWorking' => $itemsWorking,
            'itemsHistory' => $itemsHistory,
            'periods' => $periods,
            'criteriaOptions' => $criteriaOptions,
            'professions' => $professions,
            'supervisorLevelOptions' => $supervisorLevelOptions,
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
        ]);
    }

    public function updateInline(Request $request, RaterWeight $raterWeight): RedirectResponse
    {
        $this->authorizeAccess();
        $this->authorizeOwnedByUnit($raterWeight);
        $this->authorizeDraftOrRejected($raterWeight);

        $data = $request->validate([
            'weight' => ['nullable', 'numeric', 'min:0', 'max:100'],
        ]);

        // Prevent editing auto-100 single-rule rows.
        if (Schema::hasTable('criteria_rater_rules')) {
            $ruleCount = (int) DB::table('criteria_rater_rules')
                ->where('performance_criteria_id', (int) $raterWeight->performance_criteria_id)
                ->count();

            if ($ruleCount === 1) {
                $groupCount = (int) RaterWeight::query()
                    ->where('assessment_period_id', (int) $raterWeight->assessment_period_id)
                    ->where('unit_id', (int) $raterWeight->unit_id)
                    ->where('performance_criteria_id', (int) $raterWeight->performance_criteria_id)
                    ->where('assessee_profession_id', (int) $raterWeight->assessee_profession_id)
                    ->count();

                $isAutoSingle = $groupCount === 1 && (float) ($raterWeight->weight ?? 0) === 100.0;

                if ($isAutoSingle) {
                return back()->withErrors(['weight' => 'Bobot ini otomatis 100% (aturan hanya 1) dan tidak dapat diedit.']);
                }
            }
        }

        $raterWeight->weight = $data['weight'];
        $raterWeight->status = RaterWeightStatus::DRAFT;
        $raterWeight->proposed_by = null;
        $raterWeight->decided_by = null;
        $raterWeight->decided_at = null;
        $raterWeight->save();

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
            $periodId = (int) (AssessmentPeriod::query()->active()->orderByDesc('start_date')->value('id') ?? 0);
        }
        if ($periodId <= 0) {
            return back()->withErrors(['assessment_period_id' => 'Tidak ada periode aktif.']);
        }

        $criteriaIds = $this->resolveRelevant360CriteriaIdsForUnit($unitId, $periodId);
        if (empty($criteriaIds)) {
            return back()->withErrors(['status' => 'Tidak ada kriteria 360 yang dipilih untuk unit pada periode ini.']);
        }

        // Ensure rows exist before validation
        app(RaterWeightGenerator::class)->syncForUnitPeriod($unitId, $periodId);

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
            ->whereIn('status', [RaterWeightStatus::DRAFT->value, RaterWeightStatus::REJECTED->value])
            ->get();

        if ($rows->isEmpty()) {
            return back()->withErrors(['status' => 'Tidak ada bobot draft/ditolak yang bisa diajukan.']);
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

        // Submit all draft/rejected rows for this unit+period+criteria set
        DB::transaction(function () use ($rows) {
            foreach ($rows as $rw) {
                $rw->status = RaterWeightStatus::PENDING;
                $rw->proposed_by = auth()->id();
                $rw->decided_by = null;
                $rw->decided_at = null;
                $rw->save();
            }
        });

        return back()->with('status', 'Semua bobot penilai 360 berhasil diajukan (pending).');
    }

    private function authorizeOwnedByUnit(RaterWeight $raterWeight): void
    {
        $me = Auth::user();
        $unitId = (int) ($me?->unit_id ?? 0);
        abort_unless((int) $raterWeight->unit_id === $unitId, 403);
    }

    private function authorizeDraftOrRejected(RaterWeight $raterWeight): void
    {
        abort_unless(
            $raterWeight->status === RaterWeightStatus::DRAFT || $raterWeight->status === RaterWeightStatus::REJECTED,
            403
        );
    }

    private function authorizeAccess(): void
    {
        $me = Auth::user();
        abort_unless($me && (string) $me->role === 'kepala_unit', 403);
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
        if (!Schema::hasTable('unit_criteria_weights') || !Schema::hasTable('performance_criterias') || !Schema::hasTable('criteria_rater_rules')) {
            return [];
        }

        $rows = DB::table('unit_criteria_weights as ucw')
            ->join('performance_criterias as pc', 'pc.id', '=', 'ucw.performance_criteria_id')
            ->join('criteria_rater_rules as crr', 'crr.performance_criteria_id', '=', 'pc.id')
            ->where('ucw.unit_id', $unitId)
            ->where('ucw.assessment_period_id', $periodId)
            ->where('ucw.status', '!=', 'archived')
            ->where('pc.is_360', 1)
            ->where('pc.is_active', 1)
            ->distinct()
            ->pluck('pc.id')
            ->map(fn($v) => (int) $v)
            ->values()
            ->all();

        return $rows;
    }

    /**
     * Allowed assessor types are derived from criteria_rater_rules for the given criteria ids.
     * @param array<int, int> $criteriaIds
     * @return array<int, string>
     */
    private function resolveAllowedAssessorTypesForCriteriaIds(array $criteriaIds): array
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
}
