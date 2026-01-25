<?php

namespace App\Http\Controllers\Web\AdminHospital;

use App\Http\Controllers\Controller;
use App\Models\AssessmentPeriod;
use App\Models\Remuneration;
use App\Models\PerformanceAssessment;
use App\Models\PerformanceAssessmentDetail;
use App\Models\PerformanceCriteria;
use App\Models\UnitCriteriaWeight;
use App\Enums\PerformanceCriteriaType;
use App\Enums\RemunerationPaymentStatus;
use App\Enums\AssessmentValidationStatus;
use App\Models\UnitRemunerationAllocation as Allocation;
use App\Models\User;
use App\Models\Unit;
use App\Models\Profession;
use App\Support\AttachedRemunerationCalculator;
use App\Support\ProportionalAllocator;
use App\Services\Remuneration\RemunerationCalculationService;
use Dompdf\Dompdf;
use Illuminate\Http\Request;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Schema;
use Illuminate\View\View;

class RemunerationController extends Controller
{
    private const DISTRIBUTION_MODE_TOTAL_UNIT = 'total_unit_proportional';
    private const DISTRIBUTION_MODE_ATTACHED = 'attached_per_employee';

    /**
     * Decide how remuneration should be distributed for a unit+period.
     *
     * Rule (Stage 1):
     * - If ALL active weighted criteria use normalization_basis=total_unit => TOTAL_UNIT proportional (existing).
     * - If ANY active weighted criteria uses average_unit/max_unit/custom_target => ATTACHED per-employee.
     */
    private function resolveDistributionMode(int $periodId, int $unitId, ?AssessmentPeriod $period = null, ?int $professionId = null): string
    {
        $periodId = (int) $periodId;
        $unitId = (int) $unitId;
        if ($periodId <= 0 || $unitId <= 0) {
            return self::DISTRIBUTION_MODE_TOTAL_UNIT;
        }

        // Frozen period: prefer snapshots so decision stays stable even if admins
        // later change active weights/criteria configuration.
        if ($period && $period->isFrozen()) {
            $snapshotMode = $this->tryResolveDistributionModeFromSnapshot($period, $unitId, $professionId);
            if ($snapshotMode !== null) {
                return $snapshotMode;
            }
        }

        // Use active weights for the period as the definition of "configured WSM criteria".
        $bases = DB::table('unit_criteria_weights as ucw')
            ->join('performance_criterias as pc', 'pc.id', '=', 'ucw.performance_criteria_id')
            ->where('ucw.assessment_period_id', $periodId)
            ->where('ucw.unit_id', $unitId)
            ->where('ucw.status', 'active')
            ->where('pc.is_active', 1)
            ->distinct()
            ->pluck('pc.normalization_basis')
            ->map(fn($v) => (string) ($v ?? 'total_unit'))
            ->filter(fn($v) => $v !== '')
            ->values()
            ->all();

        if (empty($bases)) {
            return self::DISTRIBUTION_MODE_TOTAL_UNIT;
        }

        return $this->determineDistributionModeFromBases($bases);
    }

    /**
     * @param array<int,string> $bases
     */
    private function determineDistributionModeFromBases(array $bases): string
    {
        foreach ($bases as $b) {
            if (in_array((string) $b, ['average_unit', 'max_unit', 'custom_target'], true)) {
                return self::DISTRIBUTION_MODE_ATTACHED;
            }
        }
        return self::DISTRIBUTION_MODE_TOTAL_UNIT;
    }

    private function tryResolveDistributionModeFromSnapshot(AssessmentPeriod $period, int $unitId, ?int $professionId): ?string
    {
        if (!$period->isFrozen()) {
            return null;
        }

        if (!Schema::hasTable('assessment_period_user_membership_snapshots')) {
            return null;
        }

        if (!Schema::hasTable('performance_assessment_snapshots')) {
            return null;
        }

        $periodId = (int) $period->id;
        if ($periodId <= 0 || $unitId <= 0) {
            return null;
        }

        $q = DB::table('assessment_period_user_membership_snapshots')
            ->where('assessment_period_id', $periodId)
            ->where('unit_id', (int) $unitId);

        // If allocation is per-profession, pick a user from that profession.
        if ($professionId !== null) {
            $q->where('profession_id', (int) $professionId);
        }

        $sampleUserId = $q->orderBy('user_id')->value('user_id');
        if (!$sampleUserId) {
            return null;
        }

        $payload = DB::table('performance_assessment_snapshots')
            ->where('assessment_period_id', $periodId)
            ->where('user_id', (int) $sampleUserId)
            ->value('payload');

        if ($payload === null) {
            return null;
        }

        $decoded = is_string($payload) ? json_decode($payload, true) : (array) $payload;
        if (!is_array($decoded)) {
            return null;
        }

        $calc = (array) ($decoded['calc'] ?? []);
        $bases = array_values((array) ($calc['basis_by_criteria'] ?? []));
        $bases = array_values(array_filter(array_map(fn($v) => (string) ($v ?? ''), $bases), fn($v) => $v !== ''));
        if (empty($bases)) {
            return null;
        }

        return $this->determineDistributionModeFromBases($bases);
    }

    /**
     * @return array<int>
     */
    private function resolveRecipientUserIds(AssessmentPeriod $period, int $periodId, int $unitId, ?int $professionId): array
    {
        if ($period->isFrozen() && Schema::hasTable('assessment_period_user_membership_snapshots')) {
            $q = DB::table('assessment_period_user_membership_snapshots')
                ->where('assessment_period_id', (int) $periodId)
                ->where('unit_id', (int) $unitId);

            if ($professionId !== null) {
                $q->where('profession_id', (int) $professionId);
            }

            return $q->pluck('user_id')->map(fn($v) => (int) $v)->all();
        }

        return User::query()
            ->where('unit_id', (int) $unitId)
            ->when($professionId !== null, fn($q) => $q->where('profession_id', (int) $professionId))
            ->role(User::ROLE_PEGAWAI_MEDIS)
            ->pluck('id')
            ->map(fn($v) => (int) $v)
            ->all();
    }

    /**
     * @param array<int> $userIds
     * @return array{relative: array<int,?float>, value: array<int,?float>}|null
     */
    private function tryLoadSnapshotWsmScores(int $periodId, array $userIds): ?array
    {
        if ($periodId <= 0 || empty($userIds)) {
            return null;
        }

        if (!Schema::hasTable('performance_assessment_snapshots')) {
            return null;
        }

        $rows = DB::table('performance_assessment_snapshots')
            ->where('assessment_period_id', (int) $periodId)
            ->whereIn('user_id', array_map('intval', $userIds))
            ->get(['user_id', 'payload']);

        if ($rows->isEmpty()) {
            return null;
        }

        $relative = [];
        $value = [];
        foreach ($rows as $r) {
            $uid = (int) $r->user_id;
            $payload = is_string($r->payload) ? json_decode($r->payload, true) : (array) $r->payload;
            if (!is_array($payload)) {
                continue;
            }

            $calc = (array) ($payload['calc'] ?? []);
            $rel = $calc['total_wsm_relative'] ?? null;
            $val = $calc['total_wsm_value'] ?? null;

            // Backward compat with older snapshot shapes where totals live under calc.user.
            if ($rel === null) {
                $rel = $calc['user']['total_wsm_relative'] ?? ($calc['user']['total_wsm'] ?? null);
            }
            if ($val === null) {
                $val = $calc['user']['total_wsm_value'] ?? null;
            }

            $relative[$uid] = $rel === null ? null : (float) $rel;
            $value[$uid] = $val === null ? null : (float) $val;
        }

        foreach ($userIds as $uid) {
            $uid = (int) $uid;
            if (!array_key_exists($uid, $relative) || !array_key_exists($uid, $value)) {
                return null;
            }
        }

        return ['relative' => $relative, 'value' => $value];
    }

    /**
     * Audit calculation inputs/outputs for a period (alokasi vs WSM vs remunerations stored).
     * This is a web equivalent of the artisan debug command.
     */
    public function auditCalculation(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'period_id' => ['required', 'integer', 'exists:assessment_periods,id'],
        ]);

        $periodId = (int) $data['period_id'];
        $period = AssessmentPeriod::findOrFail($periodId);

        if (method_exists($period, 'isRejectedApproval') && $period->isRejectedApproval()) {
            return back()->with('danger', 'Audit tidak dapat dijalankan: periode sedang DITOLAK (approval rejected).');
        }

        $allocations = Allocation::query()
            ->with(['unit:id,name', 'profession:id,name'])
            ->where('assessment_period_id', $periodId)
            ->whereNotNull('published_at')
            ->orderBy('unit_id')
            ->orderBy('profession_id')
            ->get();

        if ($allocations->isEmpty()) {
            return back()->with('danger', 'Audit: tidak ada alokasi remunerasi yang sudah dipublish untuk periode ini.');
        }

        $rows = [];
        $hasIssue = false;

        foreach ($allocations as $alloc) {
            $users = $this->resolveRecipientUserIds($period, $periodId, (int) $alloc->unit_id, $alloc->profession_id === null ? null : (int) $alloc->profession_id);

            $userCount = count($users);
            if ($userCount === 0) {
                $hasIssue = true;
                $rows[] = [
                    'unit' => $alloc->unit?->name ?? ('Unit#' . (string) $alloc->unit_id),
                    'profession' => $alloc->profession?->name ?? ($alloc->profession_id ? ('Profesi#' . (string) $alloc->profession_id) : 'Semua'),
                    'allocation' => (float) $alloc->amount,
                    'mode' => null,
                    'users' => 0,
                    'wsm_total' => 0.0,
                    'wsm_null' => 0,
                    'sum_expected' => 0.0,
                    'sum_actual' => 0.0,
                    'sum_diff' => 0.0,
                    'diff_users' => 0,
                ];
                continue;
            }

            $wsmRelative = [];
            $wsmValue = [];

            $snapWsm = $period->isFrozen() ? $this->tryLoadSnapshotWsmScores($periodId, $users) : null;
            if ($snapWsm !== null) {
                $wsmRelative = (array) ($snapWsm['relative'] ?? []);
                $wsmValue = (array) ($snapWsm['value'] ?? []);
            } else {
                $wsmRows = DB::table('performance_assessments')
                    ->where('assessment_period_id', $periodId)
                    ->whereIn('user_id', $users)
                    ->get(['user_id', 'total_wsm_score', 'total_wsm_value_score']);

                foreach ($wsmRows as $r) {
                    $uid = (int) $r->user_id;
                    $wsmRelative[$uid] = $r->total_wsm_score === null ? null : (float) $r->total_wsm_score;
                    $wsmValue[$uid] = $r->total_wsm_value_score === null ? null : (float) $r->total_wsm_value_score;
                }
            }

            $weights = [];
            $nullCount = 0;
            $groupTotal = 0.0;

            foreach ($users as $uid) {
                $val = array_key_exists($uid, $wsmRelative) ? $wsmRelative[$uid] : null;
                if ($val === null) {
                    $nullCount++;
                    $w = 0.0;
                } else {
                    $w = (float) $val;
                }
                $weights[$uid] = $w;
                $groupTotal += $w;
            }

            $mode = $this->resolveDistributionMode($periodId, (int) $alloc->unit_id, $period, $alloc->profession_id === null ? null : (int) $alloc->profession_id);
            if ($mode === self::DISTRIBUTION_MODE_TOTAL_UNIT) {
                $expected = $groupTotal > 0
                    ? ProportionalAllocator::allocate((float) $alloc->amount, $weights)
                    : [];
            } else {
                // Attached mode: expected sum may be < allocation and leftover is NOT redistributed.
                $payoutPct = [];
                foreach ($users as $uid) {
                    $payoutPct[(int) $uid] = (float) (($wsmValue[(int) $uid] ?? null) ?? 0.0);
                }
                $calc = AttachedRemunerationCalculator::calculate((float) $alloc->amount, $payoutPct, 2);
                $expected = (array) ($calc['amounts'] ?? []);
            }

            $actualByUser = Remuneration::query()
                ->where('assessment_period_id', $periodId)
                ->whereIn('user_id', $users)
                ->pluck('amount', 'user_id')
                ->map(fn($v) => $v === null ? null : (float) $v)
                ->all();

            $sumExpected = 0.0;
            $sumActual = 0.0;
            $diffUsers = 0;

            foreach ($users as $uid) {
                $e = (float) ($expected[$uid] ?? 0.0);
                $a = $actualByUser[$uid] ?? null;
                $sumExpected += $e;
                $sumActual += (float) ($a ?? 0.0);

                if ($a === null || abs(((float) $a) - $e) >= 0.01) {
                    $diffUsers++;
                }
            }

            // Group is considered problematic only when:
            // - some WSM values are NULL (incomplete inputs), OR
            // - in TOTAL_UNIT mode the group total WSM is <= 0 (cannot distribute), OR
            // - expected vs actual totals do not match (nominal mismatch).
            // Per-user diffs can exist due to rounding/adjustments; keep it informational.
            $sumDiff = $sumExpected - $sumActual;
            $nominalMismatch = abs($sumDiff) >= 0.01;
            $groupHasIssue = $nullCount > 0
                || ($mode === self::DISTRIBUTION_MODE_TOTAL_UNIT && $groupTotal <= 0)
                || $nominalMismatch;

            if ($groupHasIssue) {
                $hasIssue = true;
            }

            $rows[] = [
                'unit' => $alloc->unit?->name ?? ('Unit#' . (string) $alloc->unit_id),
                'profession' => $alloc->profession?->name ?? ($alloc->profession_id ? ('Profesi#' . (string) $alloc->profession_id) : 'Semua'),
                'allocation' => (float) $alloc->amount,
                'mode' => $mode,
                'users' => $userCount,
                'wsm_total' => round($groupTotal, 2),
                'wsm_null' => $nullCount,
                'sum_expected' => round($sumExpected, 2),
                'sum_actual' => round($sumActual, 2),
                'sum_diff' => round($sumDiff, 2),
                'diff_users' => $diffUsers,
            ];
        }

        $msg = $hasIssue
            ? 'Audit selesai: ada grup yang bermasalah (skor kinerja kosong/0 atau nominal belum sesuai). Lihat tabel audit di bawah.'
            : 'Audit selesai: semua grup konsisten (alokasi = total remunerasi terhitung, tanpa selisih).';

        return back()
            ->with($hasIssue ? 'danger' : 'status', $msg)
            ->with('auditPeriodId', $periodId)
            ->with('auditRows', $rows);
    }

    /**
     * Purpose: Calculation workspace. Pick a period, run calculation,
     * and review summary of remunerations generated for that period.
     */
    public function calcIndex(Request $request): View
    {
        $periodId = (int) $request->integer('period_id');
        $periods = AssessmentPeriod::orderByDesc('start_date')->get(['id', 'name', 'start_date']);

        $summary = null;
        $allocSummary = null;
        $selectedPeriod = null;
        $prerequisites = null;
        $canRun = false;
        $needsRecalcConfirm = false;
        $lastCalculated = null;
        $remunerations = collect();
        if ($periodId) {
            $selectedPeriod = AssessmentPeriod::find($periodId);

            $isRejected = $selectedPeriod && method_exists($selectedPeriod, 'isRejectedApproval') && $selectedPeriod->isRejectedApproval();

            $remunerations = Remuneration::with('user:id,name,unit_id')
                ->where('assessment_period_id', $periodId)
                ->orderBy('user_id')
                ->get();
            $summary = [
                'count' => $remunerations->count(),
                'total' => (float) $remunerations->sum('amount'),
            ];
            $allocs = Allocation::where('assessment_period_id', $periodId)->whereNotNull('published_at')->get(['id', 'amount']);
            $allocSummary = [
                'count' => $allocs->count(),
                'total' => (float) $allocs->sum('amount'),
            ];

            // Prerequisites
            $isLocked = $selectedPeriod
                && in_array($selectedPeriod->status, [AssessmentPeriod::STATUS_LOCKED, AssessmentPeriod::STATUS_APPROVAL, AssessmentPeriod::STATUS_CLOSED], true)
                && !$isRejected;

            $assessmentCount = (int) PerformanceAssessment::query()
                ->where('assessment_period_id', $periodId)
                ->count();
            $wsmFilledCount = (int) PerformanceAssessment::query()
                ->where('assessment_period_id', $periodId)
                ->whereNotNull('total_wsm_score')
                ->count();
            $wsmNullCount = max($assessmentCount - $wsmFilledCount, 0);
            $validatedCount = (int) PerformanceAssessment::query()
                ->where('assessment_period_id', $periodId)
                ->where('validation_status', AssessmentValidationStatus::VALIDATED->value)
                ->count();
            $allValidated = $assessmentCount > 0 && $assessmentCount === $validatedCount;

            // WSM must exist for ALL assessments to fairly distribute (Excel porsi needs totals).
            $allWsmReady = $assessmentCount > 0 && $wsmFilledCount === $assessmentCount;

            $allocTotal = (int) Allocation::query()->where('assessment_period_id', $periodId)->count();
            $allocPublished = (int) Allocation::query()->where('assessment_period_id', $periodId)->whereNotNull('published_at')->count();
            $allAllocPublished = $allocTotal > 0 && $allocTotal === $allocPublished;

            $prerequisites = [
                [
                    'label' => 'Periode tidak sedang DITOLAK',
                    'ok' => !$isRejected,
                    'detail' => $isRejected ? 'Periode status APPROVAL namun ditandai DITOLAK. Buka REVISION terlebih dahulu.' : null,
                ],
                [
                    'label' => 'Periode status = LOCKED',
                    'ok' => $isLocked,
                    'detail' => $selectedPeriod ? ('Status saat ini: ' . strtoupper((string) $selectedPeriod->status)) : null,
                ],
                [
                    'label' => 'Semua penilaian sudah tervalidasi final',
                    'ok' => $allValidated,
                    'detail' => $assessmentCount > 0
                        ? ("Tervalidasi: {$validatedCount} / {$assessmentCount}")
                        : 'Belum ada data penilaian kinerja pada periode ini.',
                ],
                [
                    'label' => 'Skor Kinerja Pegawai telah tersedia',
                    'ok' => $allWsmReady,
                    'detail' => $assessmentCount > 0
                        ? ("Terisi: {$wsmFilledCount} / {$assessmentCount}" . ($wsmNullCount > 0 ? " (Kosong: {$wsmNullCount})" : ''))
                        : 'Belum ada data penilaian kinerja pada periode ini.',
                ],
                [
                    'label' => 'Semua alokasi remunerasi unit sudah dipublish',
                    'ok' => $allAllocPublished,
                    'detail' => $allocTotal > 0
                        ? ("Published: {$allocPublished} / {$allocTotal}")
                        : 'Belum ada alokasi pada periode ini.',
                ],
            ];

            $canRun = $isLocked && $allValidated && $allWsmReady && $allAllocPublished;
            $needsRecalcConfirm = Remuneration::query()
                ->where('assessment_period_id', $periodId)
                ->whereNull('published_at')
                ->exists();

            $lastCalculated = Remuneration::query()
                ->with('revisedBy:id,name')
                ->where('assessment_period_id', $periodId)
                ->whereNotNull('calculated_at')
                ->orderByDesc('calculated_at')
                ->first(['id', 'calculated_at', 'revised_by']);
        }

        return view('admin_rs.remunerations.calc_index', [
            'periods' => $periods,
            'selectedId' => $periodId ?: null,
            'selectedPeriod' => $selectedPeriod,
            'remunerations' => $remunerations,
            'summary' => $summary,
            'allocSummary' => $allocSummary,
            'prerequisites' => $prerequisites,
            'canRun' => $canRun,
            'needsRecalcConfirm' => $needsRecalcConfirm,
            'lastCalculated' => $lastCalculated,
        ]);
    }

    /**
     * Run remuneration calculation for a given period using configured WSM scores.
     * Each published unit allocation is distributed proporsional to skor WSM
     * (sumber: performance_assessments.total_wsm_score). Tetap menambahkan bonus
     * tugas tambahan yang disetujui sebagai penyesuaian akhir.
     */
    public function runCalculation(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'period_id' => ['required', 'integer', 'exists:assessment_periods,id'],
        ]);
        $periodId = (int) $data['period_id'];
        $period = AssessmentPeriod::findOrFail($periodId);

        $svc = app(RemunerationCalculationService::class);
        $result = $svc->runForPeriod($period, (int) Auth::id(), false, false);

        if (!($result['ok'] ?? false)) {
            return redirect()->route('admin_rs.remunerations.calc.index', ['period_id' => $periodId])
                ->with('danger', (string) ($result['message'] ?? 'Perhitungan remunerasi gagal.'));
        }

        return redirect()->route('admin_rs.remunerations.calc.index', ['period_id' => $periodId])
            ->with('status', (string) ($result['message'] ?? 'Perhitungan selesai.'));
    }

    /** Force calculation with zero fallback for missing data */
    public function forceCalculation(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'period_id' => ['required', 'integer', 'exists:assessment_periods,id'],
        ]);
        $periodId = (int) $data['period_id'];
        $period = AssessmentPeriod::findOrFail($periodId);

        $svc = app(RemunerationCalculationService::class);
        $result = $svc->runForPeriod($period, (int) Auth::id(), false, true);

        if (!($result['ok'] ?? false)) {
            return redirect()->route('admin_rs.remunerations.calc.index', ['period_id' => $periodId])
                ->with('danger', (string) ($result['message'] ?? 'Perhitungan remunerasi gagal.'));
        }

        return redirect()->route('admin_rs.remunerations.calc.index', ['period_id' => $periodId])
            ->with('status', (string) ($result['message'] ?? 'Perhitungan paksa selesai.'));
    }

    /**
     * @return array{ok:bool,message?:string}
     */
    private function distributeAllocation(Allocation $alloc, $period, int $periodId, ?int $professionId = null, ?float $overrideAmount = null, ?int $actorId = null): array
    {
        if (!($period instanceof AssessmentPeriod)) {
            $period = AssessmentPeriod::findOrFail($periodId);
        }

        $userIds = $this->resolveRecipientUserIds($period, $periodId, (int) $alloc->unit_id, $professionId);
        if (empty($userIds)) {
            return ['ok' => true];
        }

        $amount = $overrideAmount ?? (float) $alloc->amount;
        if ($amount <= 0) {
            return ['ok' => true];
        }

        // Load BOTH WSM types:
        // - RELATIVE (legacy) in total_wsm_score
        // - VALUE (new) in total_wsm_value_score
        $wsmRelativeByUserId = [];
        $wsmValueByUserId = [];

        $snapWsm = $period->isFrozen() ? $this->tryLoadSnapshotWsmScores($periodId, $userIds) : null;
        if ($snapWsm !== null) {
            $wsmRelativeByUserId = (array) ($snapWsm['relative'] ?? []);
            $wsmValueByUserId = (array) ($snapWsm['value'] ?? []);
        } else {
            $wsmRows = DB::table('performance_assessments')
                ->where('assessment_period_id', $periodId)
                ->whereIn('user_id', $userIds)
                ->get(['user_id', 'total_wsm_score', 'total_wsm_value_score']);

            foreach ($wsmRows as $r) {
                $uid = (int) $r->user_id;
                $wsmRelativeByUserId[$uid] = $r->total_wsm_score === null ? null : (float) $r->total_wsm_score;
                $wsmValueByUserId[$uid] = $r->total_wsm_value_score === null ? null : (float) $r->total_wsm_value_score;
            }
        }

        // TOTAL_UNIT distribution basis: RELATIVE WSM.
        $unitTotal = 0.0;
        foreach ($userIds as $userId) {
            $uid = (int) $userId;
            $unitTotal += (float) (($wsmRelativeByUserId[$uid] ?? null) ?? 0.0);
        }
        $mode = $this->resolveDistributionMode($periodId, (int) $alloc->unit_id, $period, $professionId);

        if ($mode === self::DISTRIBUTION_MODE_TOTAL_UNIT && $unitTotal <= 0) {
            // TOTAL_UNIT logic cannot compute shares if group total is 0.
            // Do NOT fall back to equal split; that hides configuration/data issues.
            $unitName = $alloc->unit?->name ?? ('Unit#' . (string) $alloc->unit_id);
            $profLabel = $professionId ? ('Profesi#' . (string) $professionId) : 'Semua profesi';
            return [
                'ok' => false,
                'message' => "{$unitName} ({$profLabel}): Total WSM grup = 0. Pastikan bobot kriteria ACTIVE tersedia dan total_wsm_score sudah terhitung.",
            ];
        }

        $weightsByUserId = [];
        foreach ($userIds as $userId) {
            $uid = (int) $userId;
            $weightsByUserId[$uid] = (float) (($wsmRelativeByUserId[$uid] ?? null) ?? 0.0);
        }

        if ($mode === self::DISTRIBUTION_MODE_TOTAL_UNIT) {
            // TOTAL_UNIT mode: allocate in cents with Largest Remainder so total distributed equals allocation amount.
            $allocatedAmounts = ProportionalAllocator::allocate((float) $amount, $weightsByUserId);
            $attachedMeta = null;
        } else {
            // ATTACHED mode: remunMax=headcount-based, payout%=WSM_VALUE, leftover is NOT redistributed.
            // Headcount must follow the dataset being calculated (this group) and should not count missing assessments.
            $payoutPctByUser = [];
            $missingValue = 0;
            foreach ($userIds as $userId) {
                $uid = (int) $userId;
                if (!array_key_exists($uid, $wsmValueByUserId) || $wsmValueByUserId[$uid] === null) {
                    $missingValue++;
                    $payoutPctByUser[$uid] = 0.0;
                } else {
                    $payoutPctByUser[$uid] = (float) $wsmValueByUserId[$uid];
                }
            }

            if ($missingValue > 0) {
                $unitName = $alloc->unit?->name ?? ('Unit#' . (string) $alloc->unit_id);
                $profLabel = $professionId ? ('Profesi#' . (string) $professionId) : 'Semua profesi';
                return [
                    'ok' => false,
                    'message' => "{$unitName} ({$profLabel}): total_wsm_value_score belum terisi untuk {$missingValue} pegawai. Jalankan recalculate penilaian (WSM) sebelum kalkulasi remunerasi.",
                ];
            }

            $attachedMeta = AttachedRemunerationCalculator::calculate((float) $amount, $payoutPctByUser, 2);
            $allocatedAmounts = (array) ($attachedMeta['amounts'] ?? []);
        }

        foreach ($userIds as $userId) {
            $userId = (int) $userId;
            $userScoreRelative = (float) (($wsmRelativeByUserId[$userId] ?? null) ?? 0.0);
            $userScoreValue = (float) (($wsmValueByUserId[$userId] ?? null) ?? 0.0);

            $sharePct = $unitTotal > 0 ? ($userScoreRelative / $unitTotal) : (1 / max(count($userIds), 1));
            $final = (float) ($allocatedAmounts[$userId] ?? 0.0);

            $rem = Remuneration::firstOrNew([
                'user_id' => $userId,
                'assessment_period_id' => $periodId,
            ]);

            // Never overwrite published remuneration amounts
            if (!empty($rem->published_at)) {
                continue;
            }

            $rem->amount = $final;
            $rem->calculated_at = now();
            if ($actorId) {
                $rem->revised_by = $actorId;
            }

            $allocationDetails = [
                'unit_id' => $alloc->unit_id,
                'unit_name' => $alloc->unit->name ?? null,
                'profession_id' => $professionId,
                'published_amount' => (float) $alloc->amount,
                'line_amount' => $amount,
                'recipients_source' => $period->isFrozen() && Schema::hasTable('assessment_period_user_membership_snapshots') ? 'snapshot_membership' : 'live_users',
                'wsm_source' => $period->isFrozen() && $snapWsm !== null ? 'snapshot' : 'performance_assessments',
                // Keep legacy keys for existing UI/debug consumers.
                'unit_total_wsm' => $unitTotal,
                'user_wsm_score' => $userScoreRelative,
                // New explicit fields.
                'unit_total_wsm_relative' => $unitTotal,
                'user_wsm_score_relative' => $userScoreRelative,
                'user_wsm_score_value' => $userScoreValue,
            ];

            if ($mode === self::DISTRIBUTION_MODE_TOTAL_UNIT) {
                $allocationDetails += [
                    'distribution_mode' => self::DISTRIBUTION_MODE_TOTAL_UNIT,
                    'share_percent' => round($sharePct * 100, 6),
                    'rounding' => [
                        'method' => 'largest_remainder_cents',
                        'precision' => 2,
                    ],
                ];
            } else {
                $remunMax = (float) (($attachedMeta['remuneration_max_per_employee'] ?? 0.0) ?: 0.0);
                $leftover = (float) (($attachedMeta['leftover_amount'] ?? 0.0) ?: 0.0);
                $headcount = (int) (($attachedMeta['headcount'] ?? 0) ?: 0);

                $payoutPct = max(0.0, min(100.0, (float) $userScoreValue));

                // Backward-compat: UI expects share_percent. In attached mode, share_percent is NOT used as amount source.
                // We set it equal to payout_percent (WSM_VALUE) to keep a meaningful percent on screen.
                $allocationDetails += [
                    'distribution_mode' => self::DISTRIBUTION_MODE_ATTACHED,
                    'headcount' => $headcount,
                    'remuneration_max_per_employee' => $remunMax,
                    'payout_percent' => round($payoutPct, 6),
                    'amount_final' => round($final, 2),
                    'leftover_amount' => round($leftover, 2),
                    'share_percent' => round($payoutPct, 6),
                    'rounding' => [
                        'method' => 'round',
                        'precision' => 2,
                        'note' => 'No remainder redistribution (leftover kept)',
                    ],
                ];
            }

            $rem->calculation_details = [
                'method' => $mode === self::DISTRIBUTION_MODE_TOTAL_UNIT
                    ? 'unit_profession_wsm_proportional'
                    : 'unit_profession_attached_percent',
                'period_id' => $periodId,
                'generated' => now()->toDateTimeString(),
                'allocation' => $allocationDetails,
                'wsm' => [
                    // Keep legacy fields as RELATIVE.
                    'user_total' => $userScoreRelative,
                    'unit_total' => $unitTotal,
                    'source' => 'performance_assessments.total_wsm_score',
                    // New explicit fields.
                    'user_total_relative' => $userScoreRelative,
                    'user_total_value' => $userScoreValue,
                    'source_value' => 'performance_assessments.total_wsm_value_score',
                ],
                // Komponen sederhana agar layar pegawai menampilkan rincian tanpa gaji dasar
                'komponen' => [
                    'dasar' => 0,
                    'pasien_ditangani' => [
                        'jumlah' => null,
                        'nilai' => $final,
                    ],
                    'review_pelanggan' => [
                        'jumlah' => 0,
                        'nilai' => 0,
                    ],
                    'kontribusi_tambahan' => [
                        'jumlah' => 0,
                        'nilai' => 0,
                    ],
                ],
            ];
            $rem->save();
        }

        return ['ok' => true];
    }

    /** Mark a remuneration as published */
    public function publish(Remuneration $remuneration): RedirectResponse
    {
        $period = AssessmentPeriod::query()->find((int) $remuneration->assessment_period_id);
        if ($period && method_exists($period, 'isRejectedApproval') && $period->isRejectedApproval()) {
            return back()->with('danger', 'Tidak dapat publish remunerasi: periode sedang DITOLAK (approval rejected).');
        }
        $remuneration->update(['published_at' => now()]);
        return back()->with('status', 'Remunerasi dipublish.');
    }
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request): View
    {
        $data = $request->validate([
            'period_id' => ['nullable', 'integer', 'exists:assessment_periods,id'],
            'unit_id' => ['nullable', 'integer', 'exists:units,id'],
            'profession_id' => ['nullable', 'integer', 'exists:professions,id'],
            'published' => ['nullable', 'in:yes,no'],
            'payment_status' => ['nullable', 'in:' . implode(',', array_map(fn($e) => $e->value, RemunerationPaymentStatus::cases()))],
        ]);

        $periodId = (int) ($data['period_id'] ?? 0);
        $unitId = (int) ($data['unit_id'] ?? 0);
        $professionId = (int) ($data['profession_id'] ?? 0);
        $published = $data['published'] ?? null;
        $paymentStatus = $data['payment_status'] ?? null;

        $periods = AssessmentPeriod::orderByDesc('start_date')->get(['id', 'name']);
        $units = Unit::orderBy('name')->get(['id', 'name']);
        $professions = Profession::orderBy('name')->get(['id', 'name']);

        // Base scope for both list and "Publish Semua" draft count.
        // Note: draftCount must ignore the current "published" filter so the CTA doesn't disappear
        // when viewing Published-only rows.
        $baseScope = Remuneration::query()
            ->when($periodId, fn($w) => $w->where('assessment_period_id', $periodId))
            ->when($paymentStatus, fn($w) => $w->where('payment_status', $paymentStatus))
            ->when($unitId || $professionId, function ($w) use ($unitId, $professionId) {
                $w->whereHas('user', function ($u) use ($unitId, $professionId) {
                    if ($unitId) {
                        $u->where('unit_id', $unitId);
                    }
                    if ($professionId) {
                        $u->where('profession_id', $professionId);
                    }
                });
            });

        $query = (clone $baseScope)
            ->with([
                'user:id,name,unit_id,profession_id',
                'assessmentPeriod:id,name',
            ])
            ->when($published === 'yes', fn($w) => $w->whereNotNull('published_at'))
            ->when($published === 'no', fn($w) => $w->whereNull('published_at'))
            ->orderByDesc('id');

        $items = $query->paginate(12)->withQueryString();

        $draftCount = (clone $baseScope)
            ->whereNull('published_at')
            ->whereNotNull('amount')
            ->count();

        return view('admin_rs.remunerations.index', [
            'items' => $items,
            'periods' => $periods,
            'units' => $units,
            'professions' => $professions,
            'periodId' => $periodId ?: null,
            'filters' => [
                'unit_id' => $unitId ?: null,
                'profession_id' => $professionId ?: null,
                'published' => $published,
                'payment_status' => $paymentStatus,
            ],
            'draftCount' => $draftCount,
        ]);
    }

    /** Publish all draft remunerations in the current filter scope */
    public function publishAll(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'period_id' => ['required', 'integer', 'exists:assessment_periods,id'],
            'unit_id' => ['nullable', 'integer', 'exists:units,id'],
            'profession_id' => ['nullable', 'integer', 'exists:professions,id'],
            'payment_status' => ['nullable', 'in:' . implode(',', array_map(fn($e) => $e->value, RemunerationPaymentStatus::cases()))],
        ]);

        $periodId = (int) $data['period_id'];

        $period = AssessmentPeriod::query()->findOrFail($periodId);
        if (method_exists($period, 'isRejectedApproval') && $period->isRejectedApproval()) {
            return back()->with('danger', 'Tidak dapat publish: periode sedang DITOLAK (approval rejected).');
        }

        $unitId = (int) ($data['unit_id'] ?? 0);
        $professionId = (int) ($data['profession_id'] ?? 0);
        $paymentStatus = $data['payment_status'] ?? null;

        $q = Remuneration::query()
            ->where('assessment_period_id', $periodId)
            ->whereNull('published_at')
            ->whereNotNull('amount')
            ->when($paymentStatus, fn($w) => $w->where('payment_status', $paymentStatus))
            ->when($unitId || $professionId, function ($w) use ($unitId, $professionId) {
                $w->whereHas('user', function ($u) use ($unitId, $professionId) {
                    if ($unitId)
                        $u->where('unit_id', $unitId);
                    if ($professionId)
                        $u->where('profession_id', $professionId);
                });
            });

        $count = (int) $q->count();
        if ($count === 0) {
            return back()->with('danger', 'Tidak ada remunerasi draft yang bisa dipublish.');
        }

        $q->update(['published_at' => now()]);

        return back()->with('status', "{$count} remunerasi dipublish.");
    }

    /** Export remunerations to PDF (full table, simple and user-friendly) */
    public function exportPdf(Request $request)
    {
        $data = $request->validate([
            'period_id' => ['nullable', 'integer', 'exists:assessment_periods,id'],
            'unit_id' => ['nullable', 'integer', 'exists:units,id'],
            'profession_id' => ['nullable', 'integer', 'exists:professions,id'],
            'published' => ['nullable', 'in:yes,no'],
            'payment_status' => ['nullable', 'in:' . implode(',', array_map(fn($e) => $e->value, RemunerationPaymentStatus::cases()))],
        ]);

        $periodId = (int) ($data['period_id'] ?? 0);
        $unitId = (int) ($data['unit_id'] ?? 0);
        $professionId = (int) ($data['profession_id'] ?? 0);
        $published = $data['published'] ?? null;
        $paymentStatus = $data['payment_status'] ?? null;

        $query = Remuneration::query()
            ->with([
                'user:id,name,unit_id,profession_id',
                'user.unit:id,name',
                'user.profession:id,name',
                'assessmentPeriod:id,name',
            ])
            ->when($periodId, fn($w) => $w->where('assessment_period_id', $periodId))
            ->when($published === 'yes', fn($w) => $w->whereNotNull('published_at'))
            ->when($published === 'no', fn($w) => $w->whereNull('published_at'))
            ->when($paymentStatus, fn($w) => $w->where('payment_status', $paymentStatus))
            ->when($unitId || $professionId, function ($w) use ($unitId, $professionId) {
                $w->whereHas('user', function ($u) use ($unitId, $professionId) {
                    if ($unitId)
                        $u->where('unit_id', $unitId);
                    if ($professionId)
                        $u->where('profession_id', $professionId);
                });
            })
            ->orderByDesc('id');

        $items = $query->get();

        $periodName = $periodId ? (AssessmentPeriod::find($periodId)?->name ?? '-') : 'Semua Periode';
        $unitName = $unitId ? (Unit::find($unitId)?->name ?? '-') : 'Semua Unit';
        $professionName = $professionId ? (Profession::find($professionId)?->name ?? '-') : 'Semua Profesi';

        $filters = [
            'period' => $periodName,
            'unit' => $unitName,
            'profession' => $professionName,
            'published' => $published === 'yes' ? 'Dipublikasikan' : ($published === 'no' ? 'Draft' : 'Semua'),
            'payment_status' => $paymentStatus ?: 'Semua',
        ];

        $html = view('admin_rs.remunerations.export_pdf', [
            'items' => $items,
            'filters' => $filters,
            'printedAt' => now(),
        ])->render();

        $dompdf = new Dompdf([
            'isRemoteEnabled' => true,
            'defaultFont' => 'DejaVu Sans',
        ]);
        $dompdf->loadHtml($html);
        $dompdf->setPaper('a4', 'landscape');
        $dompdf->render();

        $fileName = 'remunerasi-' . now()->format('Ymd-His') . '.pdf';
        return response($dompdf->output(), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="' . $fileName . '"',
        ]);
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        //
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        //
    }

    /**
     * Display the specified resource.
     */
    public function show(Remuneration $remuneration): View
    {
        $remuneration->load(['user:id,name,unit_id,profession_id', 'assessmentPeriod:id,name']);
        $wsm = $this->buildWsmBreakdown($remuneration);
        $calcDetails = null;

        if (!empty($wsm['rows'])) {
            $calcDetails = [
                'wsm' => [
                    'criteria' => collect($wsm['rows'])->map(function ($row) {
                        return [
                            'name' => $row['criteria_name'] ?? '-',
                            'weight' => $row['weight'] ?? 0,
                            'score' => $row['score'] ?? 0,
                            'contribution' => $row['contribution'] ?? 0,
                        ];
                    })->values()->all(),
                    'total' => $wsm['total'] ?? 0,
                ],
            ];
        } else {
            $calcDetails = $remuneration->calculation_details;
        }
        return view('admin_rs.remunerations.show', [
            'item' => $remuneration,
            'wsm' => $wsm,
            'calcDetails' => $calcDetails,
        ]);
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(string $id)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Remuneration $remuneration): RedirectResponse
    {
        $data = $request->validate([
            'payment_date' => ['nullable', 'date'],
            'payment_status' => ['nullable', 'string', 'max:50'],
        ]);

        if (!empty($data['payment_date'])) {
            $data['payment_status'] = RemunerationPaymentStatus::PAID->value;
        }

        $remuneration->update($data);
        return back()->with('status', 'Remunerasi diperbarui.');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
    }

    /**
     * Build WSM breakdown for a user's assessment in the same period as the remuneration.
     * Normalization:
     *  - benefit: score / max(score)
     *  - cost:    min(score) / score
     * Weights are taken from UnitCriteriaWeight with status ACTIVE for the user's unit and period.
     * Returns null if data not available.
     */
    private function buildWsmBreakdown(Remuneration $rem): ?array
    {
        $details = $rem->calculation_details ?? [];
        if (!empty($details['wsm']['criteria_rows'])) {
            // Jika calculation_details dibuat saat bobot belum tersedia (semua bobot = 0),
            // jangan pakai snapshot lama; hitung ulang dari unit_criteria_weights.
            $hasNonZeroWeight = false;
            foreach ($details['wsm']['criteria_rows'] as $r) {
                if ((float) ($r['weight'] ?? 0) > 0) {
                    $hasNonZeroWeight = true;
                    break;
                }
            }

            if (!$hasNonZeroWeight) {
                // Fall through ke perhitungan live.
                goto __WSM_LIVE__;
            }

            $rows = [];
            foreach ($details['wsm']['criteria_rows'] as $row) {
                $w = (float) ($row['weight'] ?? 0);

                // Kontribusi dihitung dari Nilai (score) langsung:
                // kontribusi = (score / 100) × bobot(%)
                $score = (float) ($row['raw'] ?? 0);
                $contrib = ($score * $w) / 100.0;
                $rows[] = [
                    'criteria_name' => $row['label'] ?? ($row['key'] ?? '-'),
                    'type' => $row['type'] ?? '-',
                    'weight' => $w,
                    'score' => $score,
                    'min' => 0,
                    'max' => (float) ($row['criteria_total'] ?? 0),
                    'contribution' => round($contrib, 2),
                ];
            }

            $total = 0.0;
            foreach ($rows as $r) {
                $total += (float) ($r['contribution'] ?? 0);
            }

            return [
                'rows' => $rows,
                'total' => round($total, 2),
            ];
        }

        __WSM_LIVE__:

        $userId = $rem->user_id;
        $periodId = $rem->assessment_period_id;
        $unitId = $rem->user->unit_id ?? null;
        $professionId = $rem->user->profession_id ?? null;
        if (!$unitId)
            return null;

        $assessment = PerformanceAssessment::with(['details.performanceCriteria:id,name,type'])
            ->where('user_id', $userId)
            ->where('assessment_period_id', $periodId)
            ->first();
        if (!$assessment)
            return null;

        $detailByCrit = [];
        $criteriaIds = [];
        foreach ($assessment->details as $d) {
            $cid = $d->performance_criteria_id;
            $criteriaIds[] = $cid;
            $detailByCrit[$cid] = [
                'score' => (float) $d->score,
                'criteria' => $d->performanceCriteria,
            ];
        }
        if (!$criteriaIds)
            return null;

        // Fetch ACTIVE weights for this unit & period
        $weightRows = UnitCriteriaWeight::query()
            ->where('unit_id', $unitId)
            ->whereIn('performance_criteria_id', $criteriaIds)
            ->where('assessment_period_id', $periodId)
            ->where(function ($q) {
                $q->where('status', 'active')
                    ->orWhere(function ($q) {
                        $q->where('status', 'archived')
                            ->where('was_active_before', true);
                    });
            })
            // Prioritaskan baris ACTIVE; jika tidak ada, pakai ARCHIVED yang pernah aktif.
            ->orderByRaw("CASE WHEN status='active' THEN 0 WHEN status='archived' AND was_active_before=1 THEN 1 ELSE 2 END")
            ->orderByDesc('updated_at')
            ->get(['performance_criteria_id', 'weight', 'status', 'was_active_before', 'updated_at']);

        $weights = [];
        foreach ($weightRows as $wr) {
            $cid = (int) ($wr->performance_criteria_id ?? 0);
            if ($cid <= 0) {
                continue;
            }
            if (!isset($weights[$cid])) {
                $weights[$cid] = $wr;
            }
        }

        // Compute min/max per criteria in this period for normalization
        $minMax = PerformanceAssessmentDetail::query()
            ->selectRaw('performance_criteria_id, MIN(score) as min_score, MAX(score) as max_score')
            ->whereIn('performance_criteria_id', $criteriaIds)
            ->whereHas('performanceAssessment', function ($q) use ($periodId, $unitId, $professionId) {
                $q->where('assessment_period_id', $periodId)
                    ->whereHas('user', function ($u) use ($unitId, $professionId) {
                        $u->where('unit_id', $unitId);
                        if ($professionId !== null) {
                            $u->where('profession_id', $professionId);
                        }
                    });
            })
            ->groupBy('performance_criteria_id')
            ->get()
            ->keyBy('performance_criteria_id');

        $rows = [];
        $total = 0.0;
        foreach ($criteriaIds as $cid) {
            $crit = $detailByCrit[$cid]['criteria'];
            $score = $detailByCrit[$cid]['score'];
            $w = (float) ($weights[$cid]->weight ?? 0); // percent
            $mm = $minMax[$cid] ?? null;
            $min = $mm ? (float) $mm->min_score : 0.0;
            $max = $mm ? (float) $mm->max_score : 0.0;
            // Kontribusi dihitung dari Nilai (score) langsung:
            // kontribusi = (score / 100) × bobot(%)
            $contrib = ($score * $w) / 100.0;
            $total += $contrib;
            $rows[] = [
                'criteria_name' => $crit?->name ?? ('Kriteria #' . $cid),
                'type' => $crit?->type?->value ?? '-',
                'weight' => $w,
                'score' => $score,
                'min' => $min,
                'max' => $max,
                'contribution' => round($contrib, 2),
            ];
        }

        return [
            'rows' => $rows,
            'total' => round($total, 2),
        ];
    }
}
