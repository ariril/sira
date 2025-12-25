<?php

namespace App\Services;

use App\Models\PerformanceAssessment;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class AssessmentApprovalDetailService
{
    public function __construct(private readonly PerformanceScoreService $scoreSvc)
    {
    }

    /**
     * Compute breakdown for the assessment's own period using active weights for that (unit, period).
     * This is intentionally independent of the period "is_active" flag, because approvals happen when
     * periods are locked/approval.
     *
     * @return array{hasWeights: bool, total: ?float, sumWeight: float, rows: array<int, array{criteria_id:int,criteria_name:string,weight:float,score_wsm:float,score_relative_unit:float,contribution:float}>}
     */
    public function getBreakdown(PerformanceAssessment $pa): array
    {
        $unitId = (int) ($pa->user?->unit_id ?? 0);
        $periodId = (int) ($pa->assessment_period_id ?? 0);
        if ($unitId <= 0 || $periodId <= 0) {
            return ['hasWeights' => false, 'total' => null, 'sumWeight' => 0.0, 'rows' => []];
        }

        $cfg = $this->scoreSvc->getActiveUnitWeights($unitId, $periodId);
        $weights = $cfg['weights'] ?? [];
        $sumWeight = (float) ($cfg['sumWeight'] ?? 0.0);
        if (empty($weights) || $sumWeight <= 0) {
            return ['hasWeights' => false, 'total' => null, 'sumWeight' => 0.0, 'rows' => []];
        }

        $scoreByCriteria = [];
        foreach ($pa->details as $d) {
            $scoreByCriteria[(int) $d->performance_criteria_id] = $d->score !== null ? (float) $d->score : 0.0;
        }

        $criteriaIds = array_values(array_map('intval', array_keys($weights)));

        $maxByCriteria = DB::table('performance_assessments as pa')
            ->join('users as u', 'u.id', '=', 'pa.user_id')
            ->join('performance_assessment_details as d', 'd.performance_assessment_id', '=', 'pa.id')
            ->where('pa.assessment_period_id', $periodId)
            ->where('u.unit_id', $unitId)
            ->whereIn('d.performance_criteria_id', $criteriaIds)
            ->groupBy('d.performance_criteria_id')
            ->selectRaw('d.performance_criteria_id as criteria_id, MAX(d.score) as max_score')
            ->pluck('max_score', 'criteria_id')
            ->map(fn($v) => (float) $v)
            ->all();

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
            $maxScore = (float) ($maxByCriteria[$criteriaId] ?? 0.0);

            $scoreRelative = $maxScore > 0 ? (($scoreWsm / $maxScore) * 100.0) : 0.0;
            $scoreRelative = max(0.0, min(100.0, $scoreRelative));

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

        return [
            'hasWeights' => true,
            'total' => $sum / $sumWeight,
            'sumWeight' => $sumWeight,
            'rows' => $rows,
        ];
    }

    public function getRawImportedValues(PerformanceAssessment $pa): Collection
    {
        $userId = (int) ($pa->user_id ?? 0);
        $periodId = (int) ($pa->assessment_period_id ?? 0);
        if ($userId <= 0 || $periodId <= 0) {
            return collect();
        }

        return DB::table('imported_criteria_values as v')
            ->leftJoin('performance_criterias as c', 'c.id', '=', 'v.performance_criteria_id')
            ->leftJoin('metric_import_batches as b', 'b.id', '=', 'v.import_batch_id')
            ->where('v.user_id', $userId)
            ->where('v.assessment_period_id', $periodId)
            ->orderBy('v.performance_criteria_id')
            ->select([
                'v.performance_criteria_id',
                'c.name as criteria_name',
                'v.value_numeric',
                'v.value_datetime',
                'v.value_text',
                'b.file_name as batch_file',
                'v.import_batch_id',
            ])
            ->get();
    }
}
