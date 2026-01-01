<?php

namespace App\Services\PerformanceScore;

use App\Models\AssessmentPeriod;
use App\Models\PerformanceCriteria;
use App\Services\CriteriaEngine\CriteriaAggregator;
use App\Services\CriteriaEngine\CriteriaNormalizer;
use App\Services\CriteriaEngine\CriteriaRegistry;
use Illuminate\Support\Facades\DB;

class PerformanceScoreService
{
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

        if ($unitId <= 0 || empty($userIds)) {
            return ['users' => [], 'criteria_ids' => [], 'weights' => [], 'max_by_criteria' => [], 'min_by_criteria' => []];
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
            return ['users' => $usersOut, 'criteria_ids' => [], 'weights' => [], 'max_by_criteria' => [], 'min_by_criteria' => []];
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
                $groupCount = (int) DB::table('imported_criteria_values')
                    ->where('assessment_period_id', (int) $period->id)
                    ->where('performance_criteria_id', (int) $criteriaId)
                    ->whereIn('user_id', $userIds)
                    ->count();

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

            // Per definisi: normalisasi yang dipakai adalah TOTAL_UNIT.
            $basis = 'total_unit';
            $target = null;

            // Normalisasi tidak membedakan cost/benefit; cost/benefit dipakai pada tahap nilai relatif.
            $norm = $this->normalizer->normalizeWithBasis(
                'benefit',
                $basis,
                $rawByUser,
                $userIds,
                $target
            );

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
            $sumWeighted = 0.0;

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

                $minNorm = (float) ($minNormByCriteriaId[$criteriaId] ?? 0.0);

                // Nilai Relatif (0–100):
                // - BENEFIT: (nilai_normalisasi / max_grup) * 100
                // - COST: (min_grup / nilai_normalisasi) * 100
                if ($type === 'benefit') {
                    $relative = $maxNorm > 0.0 ? (($normalized / $maxNorm) * 100.0) : 0.0;
                } else {
                    // If all normalized are 0 (maxNorm==0), treat as not computable -> 0.
                    if ($maxNorm <= 0.0) {
                        $relative = 0.0;
                    } elseif ($normalized <= 0.0) {
                        // If min is also 0 and there exists positive values (maxNorm>0), this user is the best (tied) -> 100.
                        $relative = ($minNorm <= 0.0) ? 100.0 : 0.0;
                    } else {
                        $relative = ($minNorm / $normalized) * 100.0;
                    }
                }

                // Hard cap for safety: nilai relatif must be within 0..100.
                // This avoids any drift from floating point / edge-case math.
                $relative = max(0.0, min(100.0, (float) $relative));

                if ($included) {
                    $sumWeightIncluded += $activeWeight;
                    // Excel/UI expectation: total WSM is based on relative score (0–100),
                    // while normalized score remains visible in details.
                    $sumWeighted += ($activeWeight * $relative);
                }

                $rows[] = [
                    'criteria_id' => $criteriaId,
                    'criteria_name' => $c ? (string) $c->name : ('Kriteria #' . $criteriaId),
                    'type' => $type,
                    'normalization_basis' => 'total_unit',
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

            $totalWsm = $sumWeightIncluded > 0.0 ? ($sumWeighted / $sumWeightIncluded) : null;

            $usersOut[$uid] = [
                'criteria' => $rows,
                'total_wsm' => $totalWsm !== null ? round($totalWsm, 6) : null,
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
        ];
    }

    /**
     * @return array{weights_all: array<int,float>, weights_active: array<int,float>, status_by_criteria: array<int,string>}
     */
    private function resolveUnitWeightsForPeriod(int $unitId, AssessmentPeriod $period): array
    {
        $periodId = (int) $period->id;
        if ($unitId <= 0 || $periodId <= 0) {
            return ['weights_all' => [], 'weights_active' => [], 'status_by_criteria' => []];
        }

        // Fetch ALL statuses so criteria can still be displayed even if not active.
        $rows = DB::table('unit_criteria_weights')
            ->where('unit_id', $unitId)
            ->where('assessment_period_id', $periodId)
            ->get(['performance_criteria_id', 'weight', 'status']);

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
            'archived' => 1,
        ];

        $weightsAll = [];
        $statusByCriteriaId = [];
        $pickedPriority = [];
        foreach ($rows as $r) {
            $cid = (int) $r->performance_criteria_id;
            $st = (string) ($r->status ?? 'unknown');
            $p = (int) ($priority[$st] ?? 0);

            if (!array_key_exists($cid, $pickedPriority) || $p > (int) $pickedPriority[$cid]) {
                $pickedPriority[$cid] = $p;
                $weightsAll[$cid] = (float) $r->weight;
                $statusByCriteriaId[$cid] = $st;
            }
        }

        // Build active weights for WSM (hanya status=active).
        $weightsActive = [];
        foreach ($rows as $r) {
            if ((string) $r->status === 'active') {
                $weightsActive[(int) $r->performance_criteria_id] = (float) $r->weight;
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
