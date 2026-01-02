<?php

namespace App\Http\Controllers\Web\MedicalStaff;

use App\Http\Controllers\Controller;
use App\Models\PerformanceAssessment;
use App\Models\User;
use App\Services\PerformanceScore\PerformanceScoreService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use App\Models\AssessmentPeriod;
use Illuminate\View\View;

class PerformanceAssessmentController extends Controller
{
    public function __construct(
        private readonly PerformanceScoreService $performanceScoreService,
    ) {
    }

    /**
     * List assessments owned by the logged-in medical staff.
     */
    public function index(Request $request)
    {
        // Acknowledge approval banner (one-time, no DB change)
        if ($request->boolean('ack_approval') && $request->filled('assessment_id')) {
            session(['approval_seen_' . (int)$request->input('assessment_id') => true]);
            return redirect()->route('pegawai_medis.assessments.index');
        }

        $assessments = PerformanceAssessment::with('assessmentPeriod')
            ->where('user_id', Auth::id())
            ->orderByDesc('id')
            ->paginate(10);

        $activePeriodId = (int) (DB::table('assessment_periods')->where('status', AssessmentPeriod::STATUS_ACTIVE)->value('id') ?? 0);
        $unitId = (int) (Auth::user()?->unit_id ?? 0);

        $kinerjaTotalsByAssessmentId = [];
        $activePeriodHasWeights = null;

        if ($unitId > 0) {
            $user = Auth::user();
            $professionId = $user?->profession_id;
            $groupUserIds = $this->resolveGroupUserIds($unitId, $professionId);
            $uid = (int) (Auth::id() ?? 0);

            $periodIds = $assessments->getCollection()
                ->pluck('assessment_period_id')
                ->map(fn($v) => (int) $v)
                ->unique()
                ->values()
                ->all();

            $periodsById = AssessmentPeriod::query()
                ->whereIn('id', $periodIds)
                ->get(['id', 'status'])
                ->keyBy('id');

            foreach ($periodsById as $pid => $period) {
                $calc = $this->performanceScoreService->calculate($unitId, $period, $groupUserIds, $professionId);
                $userRow = $uid > 0 ? ($calc['users'][$uid] ?? null) : null;
                $score = $userRow['total_wsm'] ?? null;

                foreach ($assessments->getCollection()->where('assessment_period_id', (int) $pid) as $a) {
                    $kinerjaTotalsByAssessmentId[(int) $a->id] = $score;
                }

                if ((int) $pid === $activePeriodId) {
                    $activePeriodHasWeights = $userRow ? (((float) ($userRow['sum_weight'] ?? 0.0)) > 0.0) : false;
                }
            }
        }

        return view('pegawai_medis.assessments.index', compact('assessments', 'kinerjaTotalsByAssessmentId', 'activePeriodHasWeights'));
    }

    /**
     * Show a single assessment (read-only with details).
     */
    public function show(PerformanceAssessment $assessment): View
    {
        $this->authorizeSelf($assessment);
        $assessment->load(['assessmentPeriod', 'details.performanceCriteria', 'user.unit', 'user.profession']);

        $kinerja = $this->computeKinerjaViaService($assessment);
        $rawMetrics = $this->buildRawMetrics($assessment, $kinerja);
        $activeCriteriaIdSet = $kinerja['activeCriteriaIdSet'] ?? [];

        $activeCriteria = $kinerja['activeCriteria'] ?? [];
        $inactiveCriteria = $kinerja['inactiveCriteria'] ?? [];
        $inactiveCriteriaRows = $kinerja['inactiveCriteriaRows'] ?? [];

        $visibleDetails = $assessment->details;

        return view('pegawai_medis.assessments.show', compact(
            'assessment',
            'rawMetrics',
            'kinerja',
            'visibleDetails',
            'activeCriteria',
            'inactiveCriteria',
            'inactiveCriteriaRows',
            'activeCriteriaIdSet'
        ));
    }

    private function computeKinerjaViaService(PerformanceAssessment $assessment): array
    {
        $period = $assessment->assessmentPeriod;
        $user = $assessment->user;

        $unitId = (int) ($user?->unit_id ?? 0);
        $professionId = $user?->profession_id;
        $uid = (int) ($assessment->user_id ?? 0);

        $applicable = (bool) ($period && $unitId > 0 && $uid > 0);
        if (!$applicable) {
            return [
                'applicable' => false,
                'hasWeights' => false,
                'total' => null,
                'sumWeight' => 0.0,
                'rows' => [],
                'weights' => [],
                'relativeByCriteria' => [],
                'normalizedByCriteria' => [],
                'activeCriteriaIdSet' => [],
                'activeCriteria' => [],
                'inactiveCriteria' => [],
                'inactiveCriteriaRows' => [],
            ];
        }

        $groupUserIds = $this->resolveGroupUserIds($unitId, $professionId);

        $calc = $this->performanceScoreService->calculate($unitId, $period, $groupUserIds, $professionId);

        $calculationSource = (string) (($calc['calculation_source'] ?? null) ?: 'live');
        $snapshottedAt = $calc['snapshotted_at'] ?? null;

        $userRow = $calc['users'][$uid] ?? null;
        $sumWeight = $userRow ? (float) ($userRow['sum_weight'] ?? 0.0) : 0.0;
        $hasWeights = $sumWeight > 0.0;
        $total = $userRow['total_wsm'] ?? null;

        $weights = (array) ($calc['weights'] ?? []);
        $maxByCriteria = (array) ($calc['max_by_criteria'] ?? []);
        $sumRawByCriteria = (array) ($calc['sum_raw_by_criteria'] ?? []);
        $basisByCriteria = (array) ($calc['basis_by_criteria'] ?? []);
        $basisValueByCriteria = (array) ($calc['basis_value_by_criteria'] ?? []);
        $customTargetByCriteria = (array) ($calc['custom_target_by_criteria'] ?? []);

        $relativeByCriteria = [];
        $normalizedByCriteria = [];
        $rawByCriteria = [];
        $normalizationBasisByCriteria = [];
        $basisValueByCriteriaId = [];
        $maxNormalizedByCriteria = [];
        $weightByCriteria = [];
        $includedByCriteria = [];
        $rows = [];
        $activeCriteria = [];
        $inactiveCriteria = [];
        $inactiveCriteriaRows = [];
        $activeCriteriaIdSet = [];

        // Porsi remunerasi (share) = user_total_wsm / total_wsm_group
        $groupTotalWsm = 0.0;
        foreach ($groupUserIds as $gid) {
            $groupTotalWsm += (float) (($calc['users'][(int) $gid]['total_wsm'] ?? 0.0) ?: 0.0);
        }
        $sharePct = ($groupTotalWsm > 0.0 && $total !== null) ? (((float) $total) / $groupTotalWsm) : null;

        $criteriaRows = (array) ($userRow['criteria'] ?? []);
        foreach ($criteriaRows as $r) {
            $criteriaId = (int) ($r['criteria_id'] ?? 0);
            if ($criteriaId <= 0) {
                continue;
            }

            $included = (bool) ($r['included_in_wsm'] ?? false);
            $criteriaName = (string) ($r['criteria_name'] ?? ('Kriteria #' . $criteriaId));
            $weight = (float) ($r['weight'] ?? ($weights[$criteriaId] ?? 0.0));
            $norm = (float) ($r['nilai_normalisasi'] ?? 0.0);
            $rel = (float) ($r['nilai_relativ_unit'] ?? 0.0);
            $raw = array_key_exists('raw', $r) ? (float) ($r['raw'] ?? 0.0) : null;
            $basis = (string) (($r['normalization_basis'] ?? null) ?: ((string) ($basisByCriteria[$criteriaId] ?? 'total_unit')));
            $basisVal = (float) (($r['basis_value'] ?? null) ?? (float) ($basisValueByCriteria[$criteriaId] ?? 0.0));
            $maxNorm = (float) (($r['max_normalized_in_scope'] ?? null) ?? (float) ($maxByCriteria[$criteriaId] ?? 0.0));
            $includedInWsm = (bool) ($r['included_in_wsm'] ?? false);

            $normalizedByCriteria[$criteriaId] = $norm;
            $relativeByCriteria[$criteriaId] = $rel;
            $normalizationBasisByCriteria[$criteriaId] = $basis;
            $basisValueByCriteriaId[$criteriaId] = $basisVal;
            $maxNormalizedByCriteria[$criteriaId] = $maxNorm;
            $weightByCriteria[$criteriaId] = $weight;
            $includedByCriteria[$criteriaId] = $includedInWsm;
            if ($raw !== null) {
                $rawByCriteria[$criteriaId] = $raw;
            }

            if ($included) {
                $activeCriteriaIdSet[$criteriaId] = true;
                $activeCriteria[] = $criteriaName;
                // Total WSM uses relative score (0–100).
                $contribution = $sumWeight > 0 ? (($weight / $sumWeight) * $rel) : 0.0;
                $rows[] = [
                    'criteria_id' => $criteriaId,
                    'criteria_name' => $criteriaName,
                    'weight' => $weight,
                    'score_wsm' => $rel,
                    'score_normalisasi' => $norm,
                    'contribution' => $contribution,
                ];
            } else {
                $inactiveCriteria[] = $criteriaName;
                $inactiveCriteriaRows[] = [
                    'criteria_id' => $criteriaId,
                    'criteria_name' => $criteriaName,
                    'score_wsm' => $rel,
                    'score_normalisasi' => $norm,
                ];
            }
        }

        $unitName = (string) ($user?->unit?->name ?? '-');
        $professionName = (string) ($user?->profession?->name ?? '-');
        $periodName = (string) ($period?->name ?? '-');

        return [
            'applicable' => true,
            'hasWeights' => $hasWeights,
            'total' => $total,
            'sumWeight' => $sumWeight,
            'calculationSource' => $calculationSource,
            'snapshottedAt' => $snapshottedAt,
            'activeCriteriaCount' => count($rows),
            'weightSource' => 'unit_criteria_weights (status=active)',
            'scope' => [
                'period' => $periodName,
                'unit' => $unitName,
                'profession' => $professionName,
            ],
            'rows' => $rows,
            'weights' => $weights,
            'relativeByCriteria' => $relativeByCriteria,
            'normalizedByCriteria' => $normalizedByCriteria,
            'rawByCriteria' => $rawByCriteria,
            'sumRawByCriteria' => $sumRawByCriteria,
            'maxByCriteria' => $maxByCriteria,
            'normalizationBasisByCriteria' => $normalizationBasisByCriteria,
            'basisValueByCriteria' => $basisValueByCriteriaId,
            'maxNormalizedByCriteria' => $maxNormalizedByCriteria,
            'customTargetByCriteria' => $customTargetByCriteria,
            'weightByCriteria' => $weightByCriteria,
            'includedByCriteria' => $includedByCriteria,
            'activeCriteriaIdSet' => $activeCriteriaIdSet,
            'activeCriteria' => $activeCriteria,
            'inactiveCriteria' => $inactiveCriteria,
            'inactiveCriteriaRows' => $inactiveCriteriaRows,
            'groupTotalWsm' => $groupTotalWsm,
            'sharePct' => $sharePct,
        ];
    }

    /**
     * Data mentah untuk modal per-kriteria.
     *
     * Angka harus sinkron dengan engine (PerformanceScoreService):
     * - TOTAL_UNIT pembanding = jumlah raw seluruh pegawai pada unit+profesi+periode (untuk kriteria tsb)
     * - Nilai Normalisasi = (raw_individu / total_pembanding) × 100
     *
     * @return array<int, array{title:string,lines:array<int,array{label:string,value:string,hint?:string}>,formula:?array{raw:float,denominator:float,result:float}}>
     */
    private function buildRawMetrics(PerformanceAssessment $assessment, array $kinerja): array
    {
        $rawByCriteria = (array) ($kinerja['rawByCriteria'] ?? []);
        $sumRawByCriteria = (array) ($kinerja['sumRawByCriteria'] ?? []);
        $normalizedByCriteria = (array) ($kinerja['normalizedByCriteria'] ?? []);
        $basisByCriteria = (array) ($kinerja['normalizationBasisByCriteria'] ?? []);
        $basisValueByCriteria = (array) ($kinerja['basisValueByCriteria'] ?? []);
        $maxNormByCriteria = (array) ($kinerja['maxNormalizedByCriteria'] ?? ($kinerja['maxByCriteria'] ?? []));
        $relativeByCriteria = (array) ($kinerja['relativeByCriteria'] ?? []);
        $weightByCriteria = (array) ($kinerja['weightByCriteria'] ?? []);
        $sumWeight = (float) ($kinerja['sumWeight'] ?? 0.0);
        $includedByCriteria = (array) ($kinerja['includedByCriteria'] ?? []);

        $metrics = [];
        foreach ($assessment->details as $detail) {
            $criteria = $detail->performanceCriteria;
            $criteriaId = (int) ($criteria?->id ?? $detail->performance_criteria_id ?? 0);
            if ($criteriaId <= 0) {
                continue;
            }

            $rawValue = array_key_exists($criteriaId, $rawByCriteria) ? (float) $rawByCriteria[$criteriaId] : null;
            $normalized = array_key_exists($criteriaId, $normalizedByCriteria) ? (float) $normalizedByCriteria[$criteriaId] : null;
            $basis = (string) (($basisByCriteria[$criteriaId] ?? null) ?: ((string) ($criteria?->normalization_basis ?? 'total_unit')));
            $basisValue = array_key_exists($criteriaId, $basisValueByCriteria) ? (float) $basisValueByCriteria[$criteriaId] : null;
            $maxNorm = array_key_exists($criteriaId, $maxNormByCriteria) ? (float) $maxNormByCriteria[$criteriaId] : null;
            $relative = array_key_exists($criteriaId, $relativeByCriteria) ? (float) $relativeByCriteria[$criteriaId] : null;
            $weight = array_key_exists($criteriaId, $weightByCriteria) ? (float) $weightByCriteria[$criteriaId] : null;
            $included = (bool) ($includedByCriteria[$criteriaId] ?? false);

            $basisLabel = match ($basis) {
                'max_unit' => 'max_unit (max raw grup)',
                'average_unit' => 'average_unit (avg raw grup)',
                'custom_target' => 'custom_target (target khusus)',
                default => 'total_unit (Σ raw grup)',
            };

            $type = optional($criteria?->type)->value ?? 'benefit';
            $type = $type === 'cost' ? 'cost' : 'benefit';

            $denomHint = match ($basis) {
                'max_unit' => 'Pembanding = nilai raw maksimum dalam grup (unit+profesi+periode) untuk kriteria ini.',
                'average_unit' => 'Pembanding = rata-rata raw dalam grup (unit+profesi+periode) untuk kriteria ini.',
                'custom_target' => 'Pembanding = target khusus yang diisi pada kriteria ini.',
                default => 'Pembanding = total raw seluruh pegawai dalam grup (unit+profesi+periode) untuk kriteria ini.',
            };

            $formulaText = match ($basis) {
                'total_unit' => $type === 'cost'
                    ? 'N = (1 - (raw / Σraw_grup)) × 100 (jika Σraw_grup=0 ⇒ N=100)'
                    : 'N = (raw / Σraw_grup) × 100 (jika Σraw_grup=0 ⇒ N=0)',
                'max_unit' => $type === 'cost'
                    ? 'N = (1 - (raw / max_raw_grup)) × 100 (jika max_raw_grup=0 ⇒ N=100)'
                    : 'N = (raw / max_raw_grup) × 100 (jika max_raw_grup=0 ⇒ N=0)',
                'average_unit' => $type === 'cost'
                    ? 'N = MIN(100, (1 - (raw / avg_raw_grup)) × 100) (jika avg_raw_grup=0 ⇒ N=100)'
                    : 'N = MIN(100, (raw / avg_raw_grup) × 100) (jika avg_raw_grup=0 ⇒ N=0)',
                'custom_target' => $type === 'cost'
                    ? 'N = MIN(100, (1 - (raw / target)) × 100) (jika target=0 ⇒ N=100)'
                    : 'N = MIN(100, (raw / target) × 100) (jika target=0 ⇒ N=0)',
                default => 'N dihitung dari basis kriteria.',
            };

            $relativeText = 'R = IF(max(N)>0, (N / max(N)) × 100, 0)';

            $contributionWeightedAvg = null;
            if ($included && $sumWeight > 0.0 && $relative !== null && $weight !== null) {
                $contributionWeightedAvg = ($weight / $sumWeight) * $relative;
            }
            $contributionSimple = null;
            if ($relative !== null && $weight !== null) {
                $contributionSimple = ($relative * ($weight / 100.0));
            }

            $lines = [
                ['label' => 'Raw individu', 'value' => $rawValue !== null ? number_format($rawValue, 2) : '-'],
                ['label' => 'Normalization basis', 'value' => $basisLabel],
                [
                    'label' => 'Pembanding (basis value)',
                    'value' => $basisValue !== null ? number_format($basisValue, 2) : '-',
                    'hint' => $denomHint,
                ],
                ['label' => 'Nilai Normalisasi (N)', 'value' => $normalized !== null ? number_format($normalized, 2) : '-'],
                ['label' => 'Max N dalam scope', 'value' => $maxNorm !== null ? number_format($maxNorm, 2) : '-'],
                ['label' => 'Nilai Relatif (R)', 'value' => $relative !== null ? number_format($relative, 2) : '-'],
                ['label' => 'Bobot', 'value' => $weight !== null ? number_format($weight, 2) : '-'],
                [
                    'label' => 'Kontribusi (versi WSM)',
                    'value' => $contributionWeightedAvg !== null ? number_format($contributionWeightedAvg, 2) : '-',
                    'hint' => 'Kontribusi = (bobot/ΣBobotAktif) × R. Total WSM = Σ(bobot×R)/Σ(bobot).',
                ],
                [
                    'label' => 'Kontribusi (R×bobot/100)',
                    'value' => $contributionSimple !== null ? number_format($contributionSimple, 2) : '-',
                    'hint' => 'Ini versi alternatif yang sering dipakai untuk interpretasi sederhana.',
                ],
            ];

            $formula = [
                'normalization_text' => $formulaText,
                'relative_text' => $relativeText,
            ];

            $metrics[$criteriaId] = [
                'title' => 'Detail Perhitungan',
                'lines' => $lines,
                'formula' => $formula,
            ];
        }

        return $metrics;
    }

    /**
     * @return array<int>
     */
    private function resolveGroupUserIds(int $unitId, ?int $professionId): array
    {
        if ($unitId <= 0) {
            return [];
        }

        return User::query()
            ->role(User::ROLE_PEGAWAI_MEDIS)
            ->where('unit_id', $unitId)
            ->when($professionId === null, fn($q) => $q->whereNull('profession_id'))
            ->when($professionId !== null, fn($q) => $q->where('profession_id', (int) $professionId))
            ->pluck('id')
            ->map(fn($v) => (int) $v)
            ->all();
    }

    /**
     * Ensure the record belongs to the logged-in user.
     */
    private function authorizeSelf(PerformanceAssessment $assessment): void
    {
        abort_unless($assessment->user_id === Auth::id(), 403);
    }
}
