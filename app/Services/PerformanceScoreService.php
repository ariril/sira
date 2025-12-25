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
            ->where('status', 'active')
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
     *   relativeByCriteria: array<int,float>,
     *   rows: array<int, array{criteria_id:int,criteria_name:string,weight:float,score_wsm:float,score_relative_unit:float,contribution:float}>
     * }
     */
    public function computeBreakdownForAssessment(PerformanceAssessment $assessment): array
    {
        $period = $assessment->assessmentPeriod;
        $unitId = (int) ($assessment->user?->unit_id ?? 0);

        $applicable = (bool) ($period && $period->is_active && $unitId > 0);
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

        $cfg = $this->getActiveUnitWeights($unitId, (int) $period->id);
        $weights = $cfg['weights'];
        $sumWeight = (float) $cfg['sumWeight'];
        if (empty($weights) || $sumWeight <= 0) {
            return [
                'applicable' => true,
                'hasWeights' => false,
                'total' => null,
                'sumWeight' => 0.0,
                'weights' => [],
                'maxByCriteria' => [],
                'relativeByCriteria' => [],
                'rows' => [],
            ];
        }

        // Scores for this assessment (WSM-normalized values stored in details.score)
        $scoreByCriteria = [];
        foreach ($assessment->details as $d) {
            $scoreByCriteria[(int) $d->performance_criteria_id] = $d->score !== null ? (float) $d->score : 0.0;
        }

        $criteriaIds = array_values(array_map('intval', array_keys($weights)));

        // Max normalized score per criteria in this unit+period (for relative-unit display)
        $maxByCriteria = DB::table('performance_assessments as pa')
            ->join('users as u', 'u.id', '=', 'pa.user_id')
            ->join('performance_assessment_details as d', 'd.performance_assessment_id', '=', 'pa.id')
            ->where('pa.assessment_period_id', (int) $period->id)
            ->where('u.unit_id', $unitId)
            ->whereIn('d.performance_criteria_id', $criteriaIds)
            ->groupBy('d.performance_criteria_id')
            ->selectRaw('d.performance_criteria_id as criteria_id, MAX(d.score) as max_score')
            ->pluck('max_score', 'criteria_id')
            ->map(fn($v) => (float) $v)
            ->all();

        // Names for criteria
        $namesByCriteria = DB::table('performance_criterias')
            ->whereIn('id', $criteriaIds)
            ->pluck('name', 'id')
            ->map(fn($v) => (string) $v)
            ->all();

        $rows = [];
        $relativeByCriteria = [];
        $sum = 0.0;
        foreach ($weights as $criteriaId => $weight) {
            $criteriaId = (int) $criteriaId;
            $weight = (float) $weight;
            $scoreWsm = (float) ($scoreByCriteria[$criteriaId] ?? 0.0);
            $maxScore = (float) ($maxByCriteria[$criteriaId] ?? 0.0);

            $scoreRelative = $maxScore > 0 ? (($scoreWsm / $maxScore) * 100.0) : 0.0;
            $scoreRelative = max(0.0, min(100.0, $scoreRelative));
            $relativeByCriteria[$criteriaId] = $scoreRelative;

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
