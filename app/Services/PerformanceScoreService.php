<?php

namespace App\Services;

use App\Models\PerformanceAssessment;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class PerformanceScoreService
{
    /**
     * @return array{weights: array<int,float>, sumWeight: float}
     */
    public function getActiveUnitWeights(int $unitId, int $periodId): array
    {
        $weights = DB::table('unit_criteria_weights')
            ->where('unit_id', $unitId)
            ->where('assessment_period_id', $periodId)
                ->where('status', AssessmentPeriod::STATUS_ACTIVE)
            ->pluck('weight', 'performance_criteria_id')
            ->map(fn($v) => (float) $v)
            ->all();

        $sumWeight = 0.0;
        foreach ($weights as $w) {
            $sumWeight += (float) $w;
        }

        return [
            'weights' => $weights,
            'sumWeight' => $sumWeight,
        ];
    }

    /**
     * Resolve weight mapping for a unit and period.
     *
     * Rules (match PeriodPerformanceAssessmentService):
     * - Active period: only status=active.
     * - Non-active period: prefer status=active, fallback to status=archived.
     *
     * @return array{weights: array<int,float>, sumWeight: float}
     */
    private function resolveUnitWeightsForPeriod(int $unitId, int $periodId, string $periodStatus): array
    {
        $isActive = $periodStatus === 'active';
        $statuses = $isActive ? ['active'] : ['active', 'archived'];

        $rows = DB::table('unit_criteria_weights')
            ->where('unit_id', $unitId)
            ->where('assessment_period_id', $periodId)
            ->whereIn('status', $statuses)
            ->get(['performance_criteria_id', 'weight', 'status']);

        if ($rows->isEmpty()) {
            return ['weights' => [], 'sumWeight' => 0.0];
        }

        if (!$isActive && $rows->contains(fn($r) => (string) $r->status === 'active')) {
            $rows = $rows->filter(fn($r) => (string) $r->status === 'active');
        } elseif (!$isActive) {
            $rows = $rows->filter(fn($r) => (string) $r->status === 'archived');
        }

        $weights = [];
        $sumWeight = 0.0;
        foreach ($rows as $r) {
            $cid = (int) $r->performance_criteria_id;
            $w = (float) $r->weight;
            $weights[$cid] = $w;
            $sumWeight += $w;
        }

        return ['weights' => $weights, 'sumWeight' => $sumWeight];
    }

    /**
     * Compute weighted totals for a list of assessments belonging to the same unit+period.
     *
     * @param Collection<int, PerformanceAssessment> $assessments
     * @return array{hasWeights: bool, totals: array<int,float>, sumWeight: float}
     */
    public function computeWeightedTotalsForAssessments(Collection $assessments, int $unitId, int $periodId): array
    {
        $cfg = $this->getActiveUnitWeights($unitId, $periodId);
        $weights = $cfg['weights'];
        $sumWeight = (float) $cfg['sumWeight'];
        if (empty($weights) || $sumWeight <= 0) {
            return ['hasWeights' => false, 'totals' => [], 'sumWeight' => 0.0];
        }

        $totals = [];
        foreach ($assessments as $assessment) {
            $scoreByCriteria = [];
            if ($assessment->relationLoaded('details')) {
                foreach ($assessment->details as $d) {
                    $cid = (int) $d->performance_criteria_id;
                    $scoreByCriteria[$cid] = $d->score !== null ? (float) $d->score : 0.0;
                }
            }

            $sum = 0.0;
            foreach ($weights as $criteriaId => $weight) {
                $sum += ((float) $weight) * (float) ($scoreByCriteria[(int) $criteriaId] ?? 0.0);
            }
            $totals[(int) $assessment->id] = $sum / $sumWeight;
        }

        return ['hasWeights' => true, 'totals' => $totals, 'sumWeight' => $sumWeight];
    }

    /**
     * @return array{
     *   applicable: bool,
     *   hasWeights: bool,
     *   total: ?float,
     *   sumWeight: float,
     *   weights: array<int,float>,
     *   maxByCriteria: array<int,float>,
     *   relativeByCriteria: array<int,?float>,
     *   rows: array<int, array{criteria_id:int,criteria_name:string,weight:float,score_wsm:float,score_relative_unit:?float,contribution:float}>
     * }
     */
    public function computeBreakdownForAssessment(PerformanceAssessment $assessment): array
    {
        $period = $assessment->assessmentPeriod;
        $unitId = (int) ($assessment->user?->unit_id ?? 0);
        $professionId = $assessment->user?->profession_id;

        $applicable = (bool) ($period && $unitId > 0);
        if (!$applicable) {
            return [
                'applicable' => false,
                'hasWeights' => false,
                'total' => null,
                'sumWeight' => 0.0,
                'weights' => [],
                'maxByCriteria' => [],
                'relativeByCriteria' => [],
                'rows' => [],
            ];
        }

        // Scores for this assessment (normalized values stored in details.score)
        $scoreByCriteria = [];
        foreach ($assessment->details as $d) {
            $scoreByCriteria[(int) $d->performance_criteria_id] = $d->score !== null ? (float) $d->score : 0.0;
        }

        // Criteria present in the details table (includes NON-AKTIF criteria)
        $detailCriteriaIds = $assessment->details
            ->pluck('performance_criteria_id')
            ->map(fn($v) => (int) $v)
            ->unique()
            ->values()
            ->all();

        // Max normalized score per criteria in the same unit+profession+period (for relative-unit display)
        $maxByCriteria = [];
        if (!empty($detailCriteriaIds)) {
            $maxByCriteriaQuery = DB::table('performance_assessments as pa')
                ->join('users as u', 'u.id', '=', 'pa.user_id')
                ->join('performance_assessment_details as d', 'd.performance_assessment_id', '=', 'pa.id')
                ->where('pa.assessment_period_id', (int) $period->id)
                ->where('u.unit_id', $unitId);

            if ($professionId === null) {
                $maxByCriteriaQuery->whereNull('u.profession_id');
            } else {
                $maxByCriteriaQuery->where('u.profession_id', (int) $professionId);
            }

            $maxByCriteria = $maxByCriteriaQuery
                ->whereIn('d.performance_criteria_id', $detailCriteriaIds)
                ->groupBy('d.performance_criteria_id')
                ->selectRaw('d.performance_criteria_id as criteria_id, MAX(d.score) as max_score')
                ->pluck('max_score', 'criteria_id')
                ->map(fn($v) => $v !== null ? (float) $v : 0.0)
                ->all();
        }

        // Relative score for ALL criteria shown in the detail table.
        // If max is 0 or missing, return null (UI shows '-')
        $relativeByCriteria = [];
        foreach ($detailCriteriaIds as $criteriaId) {
            $criteriaId = (int) $criteriaId;
            $score = (float) ($scoreByCriteria[$criteriaId] ?? 0.0);
            $maxScore = $maxByCriteria[$criteriaId] ?? null;
            $maxScore = $maxScore !== null ? (float) $maxScore : 0.0;
            $relativeByCriteria[$criteriaId] = $maxScore > 0 ? (($score / $maxScore) * 100.0) : null;
        }

        $cfg = $this->resolveUnitWeightsForPeriod($unitId, (int) $period->id, (string) ($period->status ?? ''));
        $weights = $cfg['weights'];
        $sumWeight = (float) $cfg['sumWeight'];
        if (empty($weights) || $sumWeight <= 0) {
            return [
                'applicable' => true,
                'hasWeights' => false,
                'total' => null,
                'sumWeight' => 0.0,
                'weights' => [],
                'maxByCriteria' => $maxByCriteria,
                'relativeByCriteria' => $relativeByCriteria,
                'rows' => [],
            ];
        }

        $criteriaIds = array_values(array_map('intval', array_keys($weights)));

        // Names for criteria
        $namesByCriteria = DB::table('performance_criterias')
            ->whereIn('id', $criteriaIds)
            ->pluck('name', 'id')
            ->map(fn($v) => (string) $v)
            ->all();

        $rows = [];
        $sum = 0.0;
        foreach ($weights as $criteriaId => $weight) {
            $criteriaId = (int) $criteriaId;
            $weight = (float) $weight;
            $scoreWsm = (float) ($scoreByCriteria[$criteriaId] ?? 0.0);
            $scoreRelative = $relativeByCriteria[$criteriaId] ?? null;

            $contribution = ($weight / $sumWeight) * $scoreWsm;

            $rows[] = [
                'criteria_id' => $criteriaId,
                'criteria_name' => $namesByCriteria[$criteriaId] ?? ('Kriteria #' . $criteriaId),
                'weight' => $weight,
                'score_wsm' => $scoreWsm,
                'score_relative_unit' => $scoreRelative,
                'contribution' => $contribution,
            ];

            $sum += $weight * $scoreWsm;
        }

        $total = $sum / $sumWeight;

        return [
            'applicable' => true,
            'hasWeights' => true,
            'total' => $total,
            'sumWeight' => $sumWeight,
            'weights' => $weights,
            'maxByCriteria' => $maxByCriteria,
            'relativeByCriteria' => $relativeByCriteria,
            'rows' => $rows,
        ];
    }
}
