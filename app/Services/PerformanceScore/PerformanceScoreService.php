<?php

namespace App\Services\PerformanceScore;

use App\Models\AssessmentPeriod;
use App\Models\PerformanceCriteria;
use App\Services\CriteriaEngine\CriteriaAggregator;
use App\Services\CriteriaEngine\CriteriaNormalizer;
use App\Services\CriteriaEngine\CriteriaRegistry;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class PerformanceScoreService
{
    private function clampPct(float $value): float
    {
        if (!is_finite($value)) {
            return 0.0;
        }

        return max(0.0, min(100.0, $value));
    }

    public function __construct(
        private readonly CriteriaAggregator $aggregator,
        private readonly CriteriaNormalizer $normalizer,
        private readonly CriteriaRegistry $registry,
    ) {
    }

    /**
     * Hitung skor WSM + detail normalisasi untuk sekelompok user dalam unit+periode.
     *
     * Ketentuan:
        * - Normalisasi WSM menggunakan TOTAL_UNIT: (nilai_individu / total_peer) * 100.
        * - COST menurunkan nilai melalui NILAI RELATIF (bukan normalisasi khusus).
     * - Kriteria non-aktif (performance_criterias.is_active = false) tetap tampil di detail,
     *   tetapi tidak ikut dihitung ke total WSM.
        * - Bobot diambil dari unit_criteria_weights; hanya status=active yang dihitung.
     *
     * @param array<int> $userIds
     * @return array{
     *   users: array<int, array{criteria: array<int, array{criteria_id:int,criteria_name:string,type:string,normalization_basis:string,weight:float,is_active:bool,included_in_wsm:bool,raw:float,nilai_normalisasi:float,nilai_relativ_unit:float}>, total_wsm:?float, sum_weight:float}>,
     *   criteria_ids: array<int>,
     *   weights: array<int,float>,
    *   max_by_criteria: array<int,float>,
    *   min_by_criteria: array<int,float>,
    *   sum_raw_by_criteria: array<int,float>
     * }
     */
    public function calculate(int $unitId, AssessmentPeriod $period, array $userIds, ?int $professionId = null): array
    {
        $unitId = (int) $unitId;
        $userIds = array_values(array_unique(array_map('intval', $userIds)));

        // Frozen period: prefer snapshot so results don't change even if criteria config changes.
        $snapshot = $this->tryLoadSnapshotCalculation($period, $userIds);
        if ($snapshot !== null) {
            $snapshot['calculation_source'] = 'snapshot';
            return $snapshot;
        }

        if ($unitId <= 0 || empty($userIds)) {
            return ['users' => [], 'criteria_ids' => [], 'weights' => [], 'max_by_criteria' => [], 'min_by_criteria' => [], 'calculation_source' => 'live'];
        }

        $weightsCfg = $this->resolveUnitWeightsForPeriod($unitId, $period);
        $weightsAllByCriteriaId = $weightsCfg['weights_all'];
        $weightsActiveByCriteriaId = $weightsCfg['weights_active'];
        $statusByCriteriaId = $weightsCfg['status_by_criteria'];

        // IMPORTANT:
        // - We still want to display criteria even if weight status is not active (e.g., draft).
        // - Total WSM uses only ACTIVE weights for the period.
        $criteriaIds = array_values(array_map('intval', array_keys($weightsAllByCriteriaId)));

        if (empty($criteriaIds)) {
            $usersOut = [];
            foreach ($userIds as $uid) {
                $usersOut[(int) $uid] = ['criteria' => [], 'total_wsm' => null, 'sum_weight' => 0.0];
            }
            return ['users' => $usersOut, 'criteria_ids' => [], 'weights' => [], 'max_by_criteria' => [], 'min_by_criteria' => [], 'calculation_source' => 'live'];
        }

        $criteriaRows = PerformanceCriteria::query()
            ->whereIn('id', $criteriaIds)
            ->get([
                'id',
                'name',
                'type',
                'source',
                'input_method',
                'is_360',
                'is_active',
                'normalization_basis',
                'custom_target_value',
            ])
            ->keyBy('id');

        $agg = $this->aggregator->aggregate($period, $unitId, $userIds, $professionId);
        $criteriaAgg = (array) ($agg['criteria'] ?? []);

        // Precompute normalized values per criteria_id.
        $normalizedByCriteriaId = [];
        $rawByCriteriaId = [];
        $sumRawByCriteriaId = [];
        $basisByCriteriaId = [];
        $basisValueByCriteriaId = [];
        $customTargetByCriteriaId = [];
        $readinessByCriteriaId = [];
        foreach ($criteriaIds as $criteriaId) {
            $criteriaId = (int) $criteriaId;
            /** @var PerformanceCriteria|null $c */
            $c = $criteriaRows->get($criteriaId);
            if (!$c) {
                // Unknown criteria row: keep zeros.
                $normalizedByCriteriaId[$criteriaId] = array_fill_keys($userIds, 0.0);
                $rawByCriteriaId[$criteriaId] = array_fill_keys($userIds, 0.0);
                $sumRawByCriteriaId[$criteriaId] = 0.0;
                $readinessByCriteriaId[$criteriaId] = ['status' => 'missing_data', 'message' => 'Kriteria tidak ditemukan.'];
                continue;
            }

            $key = $this->registry->keyForCriteria($c);
            if (!$key) {
                $normalizedByCriteriaId[$criteriaId] = array_fill_keys($userIds, 0.0);
                $rawByCriteriaId[$criteriaId] = array_fill_keys($userIds, 0.0);
                $sumRawByCriteriaId[$criteriaId] = 0.0;
                $readinessByCriteriaId[$criteriaId] = ['status' => 'missing_data', 'message' => 'Collector untuk kriteria ini tidak tersedia.'];
                continue;
            }

            $readiness = (array) (($criteriaAgg[$key]['readiness'] ?? null) ?: []);
            $status = (string) ($readiness['status'] ?? 'missing_data');
            $message = array_key_exists('message', $readiness) ? $readiness['message'] : null;

            // IMPORTANT:
            // Readiness must align with the scoring scope (unit + profession + period).
            // Some collectors' readiness checks are unit-wide; for metric_import we must ensure
            // there is data for THIS GROUP of userIds, otherwise the criterion would dilute weights
            // (e.g., contribute 9.09 when ΣBobotAktif=110 but 1 criterion has no data for the profession).
            if (str_starts_with($key, 'metric:')) {
                $qCount = DB::table('imported_criteria_values')
                    ->where('assessment_period_id', (int) $period->id)
                    ->where('performance_criteria_id', (int) $criteriaId)
                    ->whereIn('user_id', $userIds)
                    ;

                if (Schema::hasColumn('imported_criteria_values', 'is_active')) {
                    $qCount->where('is_active', 1);
                }

                $groupCount = (int) $qCount->count();

                if ($groupCount <= 0) {
                    $status = 'missing_data';
                    $message = 'Data metric (import) untuk profesi/grup ini belum ada pada periode ini.';
                } else {
                    $status = 'ready';
                    $message = null;
                }
            }

            $readinessByCriteriaId[$criteriaId] = [
                'status' => $status !== '' ? $status : 'missing_data',
                'message' => $message,
            ];

            $rawByUser = (array) (($criteriaAgg[$key]['raw'] ?? []) ?: []);
            foreach ($userIds as $uid) {
                if (!array_key_exists((int) $uid, $rawByUser)) {
                    $rawByUser[(int) $uid] = 0.0;
                }
            }
            $rawByCriteriaId[$criteriaId] = $rawByUser;
            $sumRawByCriteriaId[$criteriaId] = (float) array_sum(array_map('floatval', $rawByUser));

            $criteriaType = (string) ($c?->type?->value ?? (string) ($c?->type ?? 'benefit'));
            $criteriaType = $criteriaType === 'cost' ? 'cost' : 'benefit';

            $basis = (string) (($c->normalization_basis ?? null) ?: 'total_unit');
            if (!in_array($basis, ['total_unit', 'max_unit', 'average_unit', 'custom_target'], true)) {
                $basis = 'total_unit';
            }

            $target = null;
            if ($basis === 'custom_target') {
                $targetVal = $c->custom_target_value !== null ? (float) $c->custom_target_value : null;
                $target = ($targetVal !== null && $targetVal > 0.0) ? $targetVal : null;
            }

            $basisByCriteriaId[$criteriaId] = $basis;
            $customTargetByCriteriaId[$criteriaId] = $target;

            // IMPORTANT:
            // Normalisasi (nilai_normalisasi) disajikan sebagai basis-percentage (raw/basis*100)
            // untuk BENEFIT dan COST. Penalti COST diterapkan pada nilai_relativ_unit.
            $norm = $this->normalizer->normalizeWithBasis(
                'benefit',
                $basis,
                $rawByUser,
                $userIds,
                $target
            );

            $basisValueByCriteriaId[$criteriaId] = (float) ($norm['basis_value'] ?? 0.0);

            $normalizedByCriteriaId[$criteriaId] = (array) ($norm['normalized'] ?? []);
        }

        // Max/min normalized per criteria for relative-unit score (0-100).
        $maxNormByCriteriaId = [];
        $minNormByCriteriaId = [];
        foreach ($criteriaIds as $criteriaId) {
            $criteriaId = (int) $criteriaId;
            $max = 0.0;
            $min = null;
            foreach ($userIds as $uid) {
                $val = (float) ($normalizedByCriteriaId[$criteriaId][(int) $uid] ?? 0.0);
                $max = max($max, $val);
                $min = $min === null ? $val : min($min, $val);
            }
            $maxNormByCriteriaId[$criteriaId] = $max;
            $minNormByCriteriaId[$criteriaId] = $min !== null ? $min : 0.0;
        }

        // Build per-user output + total WSM.
        $usersOut = [];
        foreach ($userIds as $uid) {
            $uid = (int) $uid;

            $rows = [];
            $sumWeightIncluded = 0.0;
            $sumWeightedRelative = 0.0;
            $sumWeightedValue = 0.0;

            foreach ($criteriaIds as $criteriaId) {
                $criteriaId = (int) $criteriaId;
                /** @var PerformanceCriteria|null $c */
                $c = $criteriaRows->get($criteriaId);

                $type = (string) ($c?->type?->value ?? (string) ($c?->type ?? 'benefit'));
                $type = $type === 'cost' ? 'cost' : 'benefit';

                $weight = (float) ($weightsAllByCriteriaId[$criteriaId] ?? 0.0);
                $weightStatus = (string) ($statusByCriteriaId[$criteriaId] ?? 'unknown');
                $activeWeight = (float) ($weightsActiveByCriteriaId[$criteriaId] ?? 0.0);
                $isActive = $c ? (bool) $c->is_active : false;
                $readinessStatus = (string) (($readinessByCriteriaId[$criteriaId]['status'] ?? 'missing_data') ?: 'missing_data');
                $included = $isActive && $activeWeight > 0.0 && $readinessStatus === 'ready';

                $normalized = (float) ($normalizedByCriteriaId[$criteriaId][$uid] ?? 0.0);
                $maxNorm = (float) ($maxNormByCriteriaId[$criteriaId] ?? 0.0);

                // Nilai Relatif (0–100):
                // - BENEFIT: R = IF(max(N)>0, (N/max(N))*100, 0)
                // - COST:    R =
                //      - if min(N)=0: top performer (N=0) => 100, others => 0
                //      - else: (min(N)/N)*100
                if ($readinessStatus !== 'ready') {
                    $relative = 0.0;
                } elseif ($type === 'cost') {
                    $minNorm = (float) ($minNormByCriteriaId[$criteriaId] ?? 0.0);
                    if ($minNorm <= 0.0) {
                        $relative = $normalized <= 0.0 ? 100.0 : 0.0;
                    } else {
                        $relative = $normalized > 0.0 ? (($minNorm / $normalized) * 100.0) : 0.0;
                    }
                } else {
                    $relative = $maxNorm > 0.0 ? (($normalized / $maxNorm) * 100.0) : 0.0;
                }

                $relative = $this->clampPct((float) $relative);

                if ($included) {
                    $sumWeightIncluded += $activeWeight;
                    // Excel/UI expectation: total WSM is based on relative score (0–100),
                    // while normalized score remains visible in details.
                    $sumWeightedRelative += ($activeWeight * $relative);
                    // Attached-mode payout% expectation: based on normalized score (0–100).
                    $sumWeightedValue += ($activeWeight * $normalized);
                }

                $rows[] = [
                    'criteria_id' => $criteriaId,
                    'criteria_name' => $c ? (string) $c->name : ('Kriteria #' . $criteriaId),
                    'type' => $type,
                    'normalization_basis' => (string) ($basisByCriteriaId[$criteriaId] ?? 'total_unit'),
                    'basis_value' => (float) ($basisValueByCriteriaId[$criteriaId] ?? 0.0),
                    'custom_target_value' => $customTargetByCriteriaId[$criteriaId] ?? null,
                    'max_normalized_in_scope' => (float) ($maxNormByCriteriaId[$criteriaId] ?? 0.0),
                    // Display the weight that is actually used in WSM when included.
                    'weight' => $included ? $activeWeight : $weight,
                    'weight_status' => $included ? 'active' : $weightStatus,
                    'is_active' => $isActive,
                    'included_in_wsm' => $included,
                    'readiness_status' => $readinessStatus,
                    'readiness_message' => $readinessByCriteriaId[$criteriaId]['message'] ?? null,
                    'raw' => (float) ($rawByCriteriaId[$criteriaId][$uid] ?? 0.0),
                    'nilai_normalisasi' => round($normalized, 6),
                    'nilai_relativ_unit' => round($relative, 6),
                ];
            }

            $totalWsmRelative = $sumWeightIncluded > 0.0 ? ($sumWeightedRelative / $sumWeightIncluded) : null;
            $totalWsmValue = $sumWeightIncluded > 0.0 ? ($sumWeightedValue / $sumWeightIncluded) : null;

            $usersOut[$uid] = [
                'criteria' => $rows,
                // Backward compatible field (legacy callers expect total_wsm).
                'total_wsm' => $totalWsmRelative !== null ? round($totalWsmRelative, 6) : null,
                'total_wsm_relative' => $totalWsmRelative !== null ? round($totalWsmRelative, 6) : null,
                'total_wsm_value' => $totalWsmValue !== null ? round($totalWsmValue, 6) : null,
                'sum_weight' => round($sumWeightIncluded, 6),
            ];
        }

        return [
            'users' => $usersOut,
            'criteria_ids' => $criteriaIds,
            'weights' => $weightsAllByCriteriaId,
            'max_by_criteria' => $maxNormByCriteriaId,
            'min_by_criteria' => $minNormByCriteriaId,
            'sum_raw_by_criteria' => $sumRawByCriteriaId,
            'basis_by_criteria' => $basisByCriteriaId,
            'basis_value_by_criteria' => $basisValueByCriteriaId,
            'custom_target_by_criteria' => $customTargetByCriteriaId,
            'calculation_source' => 'live',
        ];
    }

    /**
     * Hitung skor untuk SEMUA kriteria yang ada di database (real-time),
     * tanpa menunggu bobot aktif. Total WSM tetap hanya menghitung bobot active.
     *
     * Dipakai untuk modul "Kinerja Saya" pada periode berjalan.
     *
     * @param array<int> $userIds
     */
    public function calculateAllCriteria(int $unitId, AssessmentPeriod $period, array $userIds, ?int $professionId = null): array
    {
        $unitId = (int) $unitId;
        $userIds = array_values(array_unique(array_map('intval', $userIds)));

        // Frozen period: prefer snapshot so results don't change even if criteria config changes.
        $snapshot = $this->tryLoadSnapshotCalculation($period, $userIds);
        if ($snapshot !== null) {
            $snapshot['calculation_source'] = 'snapshot';
            return $snapshot;
        }

        if ($unitId <= 0 || empty($userIds)) {
            return ['users' => [], 'criteria_ids' => [], 'weights' => [], 'max_by_criteria' => [], 'min_by_criteria' => [], 'calculation_source' => 'live'];
        }

        $criteriaIds = PerformanceCriteria::query()
            ->orderBy('id')
            ->pluck('id')
            ->map(fn ($v) => (int) $v)
            ->all();

        if (empty($criteriaIds)) {
            $usersOut = [];
            foreach ($userIds as $uid) {
                $usersOut[(int) $uid] = ['criteria' => [], 'total_wsm' => null, 'sum_weight' => 0.0];
            }
            return ['users' => $usersOut, 'criteria_ids' => [], 'weights' => [], 'max_by_criteria' => [], 'min_by_criteria' => [], 'calculation_source' => 'live'];
        }

        $weightsCfg = $this->resolveUnitWeightsForPeriod($unitId, $period);
        $weightsAllRaw = (array) ($weightsCfg['weights_all'] ?? []);
        $weightsActiveRaw = (array) ($weightsCfg['weights_active'] ?? []);
        $statusRaw = (array) ($weightsCfg['status_by_criteria'] ?? []);

        // Ensure every criteria has an explicit weight/status for display.
        $weightsAllByCriteriaId = [];
        $weightsActiveByCriteriaId = [];
        $statusByCriteriaId = [];
        foreach ($criteriaIds as $cid) {
            $cid = (int) $cid;
            $weightsAllByCriteriaId[$cid] = (float) ($weightsAllRaw[$cid] ?? 0.0);
            $weightsActiveByCriteriaId[$cid] = (float) ($weightsActiveRaw[$cid] ?? 0.0);
            $statusByCriteriaId[$cid] = (string) ($statusRaw[$cid] ?? 'missing');
        }

        $criteriaRows = PerformanceCriteria::query()
            ->whereIn('id', $criteriaIds)
            ->get([
                'id',
                'name',
                'type',
                'source',
                'input_method',
                'is_360',
                'is_active',
                'normalization_basis',
                'custom_target_value',
            ])
            ->keyBy('id');

        $agg = $this->aggregator->aggregateForCriteriaIds($period, $unitId, $userIds, $criteriaIds, $professionId);
        $criteriaAgg = (array) ($agg['criteria'] ?? []);

        // Precompute normalized values per criteria_id.
        $normalizedByCriteriaId = [];
        $rawByCriteriaId = [];
        $sumRawByCriteriaId = [];
        $basisByCriteriaId = [];
        $basisValueByCriteriaId = [];
        $customTargetByCriteriaId = [];
        $readinessByCriteriaId = [];
        foreach ($criteriaIds as $criteriaId) {
            $criteriaId = (int) $criteriaId;
            /** @var PerformanceCriteria|null $c */
            $c = $criteriaRows->get($criteriaId);
            if (!$c) {
                $normalizedByCriteriaId[$criteriaId] = array_fill_keys($userIds, 0.0);
                $rawByCriteriaId[$criteriaId] = array_fill_keys($userIds, 0.0);
                $sumRawByCriteriaId[$criteriaId] = 0.0;
                $readinessByCriteriaId[$criteriaId] = ['status' => 'missing_data', 'message' => 'Kriteria tidak ditemukan.'];
                continue;
            }

            $key = $this->registry->keyForCriteria($c);
            if (!$key) {
                $normalizedByCriteriaId[$criteriaId] = array_fill_keys($userIds, 0.0);
                $rawByCriteriaId[$criteriaId] = array_fill_keys($userIds, 0.0);
                $sumRawByCriteriaId[$criteriaId] = 0.0;
                $readinessByCriteriaId[$criteriaId] = ['status' => 'missing_data', 'message' => 'Collector untuk kriteria ini tidak tersedia.'];
                continue;
            }

            $readiness = (array) (($criteriaAgg[$key]['readiness'] ?? null) ?: []);
            $status = (string) ($readiness['status'] ?? 'missing_data');
            $message = array_key_exists('message', $readiness) ? $readiness['message'] : null;

            // Keep readiness aligned with the scoring scope.
            if (str_starts_with($key, 'metric:')) {
                $groupCountQuery = DB::table('imported_criteria_values')
                    ->where('assessment_period_id', (int) $period->id)
                    ->where('performance_criteria_id', (int) $criteriaId)
                    ->whereIn('user_id', $userIds);

                if (Schema::hasColumn('imported_criteria_values', 'is_active')) {
                    $groupCountQuery->where('is_active', true);
                }

                $groupCount = (int) $groupCountQuery->count();

                if ($groupCount <= 0) {
                    $status = 'missing_data';
                    $message = 'Data metric (import) untuk profesi/grup ini belum ada pada periode ini.';
                } else {
                    $status = 'ready';
                    $message = null;
                }
            }

            $readinessByCriteriaId[$criteriaId] = [
                'status' => $status !== '' ? $status : 'missing_data',
                'message' => $message,
            ];

            $rawByUser = (array) (($criteriaAgg[$key]['raw'] ?? []) ?: []);
            foreach ($userIds as $uid) {
                if (!array_key_exists((int) $uid, $rawByUser)) {
                    $rawByUser[(int) $uid] = 0.0;
                }
            }
            $rawByCriteriaId[$criteriaId] = $rawByUser;
            $sumRawByCriteriaId[$criteriaId] = (float) array_sum(array_map('floatval', $rawByUser));

            $criteriaType = (string) ($c?->type?->value ?? (string) ($c?->type ?? 'benefit'));
            $criteriaType = $criteriaType === 'cost' ? 'cost' : 'benefit';

            $basis = (string) (($c->normalization_basis ?? null) ?: 'total_unit');
            if (!in_array($basis, ['total_unit', 'max_unit', 'average_unit', 'custom_target'], true)) {
                $basis = 'total_unit';
            }

            $target = null;
            if ($basis === 'custom_target') {
                $targetVal = $c->custom_target_value !== null ? (float) $c->custom_target_value : null;
                $target = ($targetVal !== null && $targetVal > 0.0) ? $targetVal : null;
            }

            $basisByCriteriaId[$criteriaId] = $basis;
            $customTargetByCriteriaId[$criteriaId] = $target;

            $norm = $this->normalizer->normalizeWithBasis(
                'benefit',
                $basis,
                $rawByUser,
                $userIds,
                $target
            );

            $basisValueByCriteriaId[$criteriaId] = (float) ($norm['basis_value'] ?? 0.0);
            $normalizedByCriteriaId[$criteriaId] = (array) ($norm['normalized'] ?? []);
        }

        // Max/min normalized per criteria for relative-unit score (0-100).
        $maxNormByCriteriaId = [];
        $minNormByCriteriaId = [];
        foreach ($criteriaIds as $criteriaId) {
            $criteriaId = (int) $criteriaId;
            $max = 0.0;
            $min = null;
            foreach ($userIds as $uid) {
                $val = (float) ($normalizedByCriteriaId[$criteriaId][(int) $uid] ?? 0.0);
                $max = max($max, $val);
                $min = $min === null ? $val : min($min, $val);
            }
            $maxNormByCriteriaId[$criteriaId] = $max;
            $minNormByCriteriaId[$criteriaId] = $min !== null ? $min : 0.0;
        }

        // Build per-user output + total WSM.
        $usersOut = [];
        foreach ($userIds as $uid) {
            $uid = (int) $uid;

            $rows = [];
            $sumWeightIncluded = 0.0;
            $sumWeightedRelative = 0.0;
            $sumWeightedValue = 0.0;

            foreach ($criteriaIds as $criteriaId) {
                $criteriaId = (int) $criteriaId;
                /** @var PerformanceCriteria|null $c */
                $c = $criteriaRows->get($criteriaId);

                $type = (string) ($c?->type?->value ?? (string) ($c?->type ?? 'benefit'));
                $type = $type === 'cost' ? 'cost' : 'benefit';

                $weight = (float) ($weightsAllByCriteriaId[$criteriaId] ?? 0.0);
                $weightStatus = (string) ($statusByCriteriaId[$criteriaId] ?? 'unknown');
                $activeWeight = (float) ($weightsActiveByCriteriaId[$criteriaId] ?? 0.0);
                $isActive = $c ? (bool) $c->is_active : false;
                $readinessStatus = (string) (($readinessByCriteriaId[$criteriaId]['status'] ?? 'missing_data') ?: 'missing_data');
                $included = $isActive && $activeWeight > 0.0 && $readinessStatus === 'ready';

                $normalized = (float) ($normalizedByCriteriaId[$criteriaId][$uid] ?? 0.0);
                $maxNorm = (float) ($maxNormByCriteriaId[$criteriaId] ?? 0.0);

                if ($readinessStatus !== 'ready') {
                    $relative = 0.0;
                } elseif ($type === 'cost') {
                    $minNorm = (float) ($minNormByCriteriaId[$criteriaId] ?? 0.0);
                    if ($minNorm <= 0.0) {
                        $relative = $normalized <= 0.0 ? 100.0 : 0.0;
                    } else {
                        $relative = $normalized > 0.0 ? (($minNorm / $normalized) * 100.0) : 0.0;
                    }
                } else {
                    $relative = $maxNorm > 0.0 ? (($normalized / $maxNorm) * 100.0) : 0.0;
                }

                $relative = $this->clampPct((float) $relative);

                if ($included) {
                    $sumWeightIncluded += $activeWeight;
                    $sumWeightedRelative += ($activeWeight * $relative);
                    $sumWeightedValue += ($activeWeight * $normalized);
                }

                $rows[] = [
                    'criteria_id' => $criteriaId,
                    'criteria_name' => $c ? (string) $c->name : ('Kriteria #' . $criteriaId),
                    'type' => $type,
                    'normalization_basis' => (string) ($basisByCriteriaId[$criteriaId] ?? 'total_unit'),
                    'basis_value' => (float) ($basisValueByCriteriaId[$criteriaId] ?? 0.0),
                    'custom_target_value' => $customTargetByCriteriaId[$criteriaId] ?? null,
                    'max_normalized_in_scope' => (float) ($maxNormByCriteriaId[$criteriaId] ?? 0.0),
                    'weight' => $included ? $activeWeight : $weight,
                    'weight_status' => $included ? 'active' : $weightStatus,
                    'is_active' => $isActive,
                    'included_in_wsm' => $included,
                    'readiness_status' => $readinessStatus,
                    'readiness_message' => $readinessByCriteriaId[$criteriaId]['message'] ?? null,
                    'raw' => (float) ($rawByCriteriaId[$criteriaId][$uid] ?? 0.0),
                    'nilai_normalisasi' => round($normalized, 6),
                    'nilai_relativ_unit' => round($relative, 6),
                ];
            }

            $totalWsmRelative = $sumWeightIncluded > 0.0 ? ($sumWeightedRelative / $sumWeightIncluded) : null;
            $totalWsmValue = $sumWeightIncluded > 0.0 ? ($sumWeightedValue / $sumWeightIncluded) : null;

            $usersOut[$uid] = [
                'criteria' => $rows,
                'total_wsm' => $totalWsmRelative !== null ? round($totalWsmRelative, 6) : null,
                'total_wsm_relative' => $totalWsmRelative !== null ? round($totalWsmRelative, 6) : null,
                'total_wsm_value' => $totalWsmValue !== null ? round($totalWsmValue, 6) : null,
                'sum_weight' => round($sumWeightIncluded, 6),
            ];
        }

        return [
            'users' => $usersOut,
            'criteria_ids' => $criteriaIds,
            'weights' => $weightsAllByCriteriaId,
            'max_by_criteria' => $maxNormByCriteriaId,
            'min_by_criteria' => $minNormByCriteriaId,
            'sum_raw_by_criteria' => $sumRawByCriteriaId,
            'basis_by_criteria' => $basisByCriteriaId,
            'basis_value_by_criteria' => $basisValueByCriteriaId,
            'custom_target_by_criteria' => $customTargetByCriteriaId,
            'calculation_source' => 'live',
        ];
    }

    /**
     * @param array<int> $userIds
     * @return array<string,mixed>|null
     */
    private function tryLoadSnapshotCalculation(AssessmentPeriod $period, array $userIds): ?array
    {
        if (!$period->isFrozen()) {
            return null;
        }

        if (!Schema::hasTable('performance_assessment_snapshots')) {
            return null;
        }

        $periodId = (int) $period->id;
        if ($periodId <= 0 || empty($userIds)) {
            return null;
        }

        $rows = DB::table('performance_assessment_snapshots')
            ->where('assessment_period_id', $periodId)
            ->whereIn('user_id', $userIds)
            ->get(['user_id', 'payload', 'snapshotted_at']);

        if ($rows->isEmpty()) {
            return null;
        }

        $users = [];
        $criteriaIds = [];
        $weights = [];
        $maxByCriteria = [];
        $minByCriteria = [];
        $sumRawByCriteria = [];
        $basisByCriteria = [];
        $basisValueByCriteria = [];
        $customTargetByCriteria = [];
        $snapshottedAt = null;

        foreach ($rows as $r) {
            $uid = (int) $r->user_id;
            $payload = is_string($r->payload) ? json_decode($r->payload, true) : (array) $r->payload;
            if (!is_array($payload)) {
                continue;
            }

            $calc = (array) ($payload['calc'] ?? []);
            $userRow = $calc['user'] ?? null;
            if (!is_array($userRow)) {
                continue;
            }

            $users[$uid] = $userRow;

            // Use first valid snapshot's group-level meta as the response meta.
            if (empty($criteriaIds) && isset($calc['criteria_ids'])) {
                $criteriaIds = array_values(array_map('intval', (array) $calc['criteria_ids']));
                $weights = (array) ($calc['weights'] ?? []);
                $maxByCriteria = (array) ($calc['max_by_criteria'] ?? []);
                $minByCriteria = (array) ($calc['min_by_criteria'] ?? []);
                $sumRawByCriteria = (array) ($calc['sum_raw_by_criteria'] ?? []);
                $basisByCriteria = (array) ($calc['basis_by_criteria'] ?? []);
                $basisValueByCriteria = (array) ($calc['basis_value_by_criteria'] ?? []);
                $customTargetByCriteria = (array) ($calc['custom_target_by_criteria'] ?? []);
                $snapshottedAt = $r->snapshotted_at ?? null;
            }
        }

        // Require at least the requested users.
        foreach ($userIds as $uid) {
            if (!array_key_exists((int) $uid, $users)) {
                return null;
            }
        }

        return [
            'users' => $users,
            'criteria_ids' => $criteriaIds,
            'weights' => $weights,
            'max_by_criteria' => $maxByCriteria,
            'min_by_criteria' => $minByCriteria,
            'sum_raw_by_criteria' => $sumRawByCriteria,
            'basis_by_criteria' => $basisByCriteria,
            'basis_value_by_criteria' => $basisValueByCriteria,
            'custom_target_by_criteria' => $customTargetByCriteria,
            'snapshotted_at' => $snapshottedAt,
        ];
    }

    /**
     * @return array{weights_all: array<int,float>, weights_active: array<int,float>, status_by_criteria: array<int,string>}
     */
    private function resolveUnitWeightsForPeriod(int $unitId, AssessmentPeriod $period): array
    {
        $periodId = (int) $period->id;
        $hasWasActiveBefore = Schema::hasColumn('unit_criteria_weights', 'was_active_before');
        $select = ['performance_criteria_id', 'weight', 'status'];
        if ($hasWasActiveBefore) {
            $select[] = 'was_active_before';
        }

        // Fetch ALL statuses so criteria can still be displayed even if not active.
        $rows = DB::table('unit_criteria_weights')
            ->where('unit_id', $unitId)
            ->where('assessment_period_id', $periodId)
            ->get($select);

        if ($rows->isEmpty()) {
            return ['weights_all' => [], 'weights_active' => [], 'status_by_criteria' => []];
        }

        // Build all weights for display with deterministic priority.
        // Priority: active > pending > draft > rejected > archived
        $priority = [
            'active' => 5,
            'pending' => 4,
            'draft' => 3,
            'rejected' => 2,
            // archived hanya valid bila was_active_before=1
            'archived' => 1,
        ];

        $weightsAll = [];
        $statusByCriteriaId = [];
        $pickedPriority = [];
        foreach ($rows as $r) {
            $cid = (int) $r->performance_criteria_id;
            $st = (string) ($r->status ?? 'unknown');
            if ($st === 'archived' && property_exists($r, 'was_active_before') && !((bool) ($r->was_active_before ?? false))) {
                // Abaikan arsip yang bukan bobot aktif periode tersebut.
                continue;
            }

            $p = (int) ($priority[$st] ?? 0);

            if (!array_key_exists($cid, $pickedPriority) || $p > (int) $pickedPriority[$cid]) {
                $pickedPriority[$cid] = $p;
                $weightsAll[$cid] = (float) $r->weight;
                $statusByCriteriaId[$cid] = $st;
            }
        }

        // Build weights effective for perhitungan:
        // - Jika ada status=active pada periode tsb, pakai active.
        // - Jika tidak ada (periode sudah ditutup/diarsip), pakai archived yang was_active_before=1.
        $hasAnyActive = $rows->contains(fn($r) => (string) ($r->status ?? '') === 'active');
        $weightsActive = [];
        foreach ($rows as $r) {
            $st = (string) ($r->status ?? '');
            if ($hasAnyActive) {
                if ($st === 'active') {
                    $weightsActive[(int) $r->performance_criteria_id] = (float) $r->weight;
                }
                continue;
            }

            if ($st === 'archived') {
                if (!property_exists($r, 'was_active_before') || (bool) ($r->was_active_before ?? false)) {
                    $weightsActive[(int) $r->performance_criteria_id] = (float) $r->weight;
                }
            }
        }

        return ['weights_all' => $weightsAll, 'weights_active' => $weightsActive, 'status_by_criteria' => $statusByCriteriaId];
    }

    private function periodTotalDays(AssessmentPeriod $period): float
    {
        if (!$period->start_date || !$period->end_date) {
            return 0.0;
        }

        try {
            $s = \Illuminate\Support\Carbon::parse((string) $period->start_date)->startOfDay();
            $e = \Illuminate\Support\Carbon::parse((string) $period->end_date)->startOfDay();
            return (float) ($s->diffInDays($e) + 1);
        } catch (\Throwable) {
            return 0.0;
        }
    }
}
