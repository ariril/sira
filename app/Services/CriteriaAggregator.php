<?php

namespace App\Services;

use App\Models\CriteriaMetric;
use App\Models\PerformanceAssessment;
use App\Models\PerformanceAssessmentDetail;
use App\Models\PerformanceCriteria;
use App\Models\RaterWeight;
use App\Models\MultiRaterAssessment;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class CriteriaAggregator
{
    // Aggregate all criterias for a user & period into 0-100 and store in details
    public function aggregateUserPeriod(int $userId, int $periodId): void
    {
        DB::transaction(function () use ($userId, $periodId) {
            $assessment = PerformanceAssessment::firstOrCreate(
                ['user_id' => $userId, 'assessment_period_id' => $periodId],
                ['assessment_date' => now(), 'total_wsm_score' => 0.0, 'validation_status' => 'draft']
            );

            $criterias = PerformanceCriteria::where('is_active', true)->get();

            foreach ($criterias as $criteria) {
                $score = null;
                $metric = null;

                if ((bool) $criteria->is_360) {
                    $score = $this->aggregate360($criteria->id, $userId, $periodId);
                } else {
                    // For non-360, assume metric is precomputed/imported; normalize as-is (0-100 expected)
                    $metric = CriteriaMetric::where('user_id', $userId)
                        ->where('assessment_period_id', $periodId)
                        ->where('performance_criteria_id', $criteria->id)
                        ->first();
                    $score = $metric?->value_numeric;
                }

                if ($score !== null) {
                    PerformanceAssessmentDetail::updateOrCreate(
                        [
                            'performance_assessment_id' => $assessment->id,
                            'performance_criteria_id' => $criteria->id,
                        ],
                        [
                            'criteria_metric_id' => $metric?->id,
                            'score' => round((float)$score, 2),
                        ]
                    );
                }
            }
        });
    }

    // Weighted aggregation for 360 based on rater_weights (active per period + assessee profession). Returns 0-100 or null.
    private function aggregate360(int $criteriaId, int $userId, int $periodId): ?float
    {
        $user = User::query()->whereKey($userId)->first(['id', 'unit_id', 'profession_id']);
        $unitId = (int) ($user?->unit_id ?? 0);
        $professionId = (int) ($user?->profession_id ?? 0);
        $weights = $this->resolveActiveWeights($periodId, $unitId, $criteriaId, $professionId);

        $avgByType = DB::table('multi_rater_assessment_details as d')
            ->join('multi_rater_assessments as mra', 'mra.id', '=', 'd.multi_rater_assessment_id')
            ->selectRaw('mra.assessor_type as assessor_type, AVG(d.score) as avg_score')
            ->where('mra.assessee_id', $userId)
            ->where('mra.assessment_period_id', $periodId)
            ->where('mra.status', 'submitted')
            ->where('d.performance_criteria_id', $criteriaId)
            ->groupBy('mra.assessor_type')
            ->pluck('avg_score', 'assessor_type')
            ->map(fn($v) => (float) $v)
            ->all();

        if (empty($avgByType)) {
            return null;
        }

        $final = 0.0;
        $hasAny = false;
        foreach (['self', 'supervisor', 'peer', 'subordinate'] as $type) {
            if (array_key_exists($type, $avgByType)) {
                $hasAny = true;
            }
            $avg = (float) ($avgByType[$type] ?? 0.0);
            $w = (float) ($weights[$type] ?? 0.0);
            $final += $avg * ($w / 100.0);
        }

        return $hasAny ? $final : null;
    }

    /**
     * @return array<string,float> assessor_type => weight
     */
    private function resolveActiveWeights(int $periodId, int $unitId, int $criteriaId, int $professionId): array
    {
        $defaults = [
            'supervisor' => 40.0,
            'peer' => 30.0,
            'subordinate' => 20.0,
            'self' => 10.0,
        ];

        if ($periodId <= 0 || $unitId <= 0 || $criteriaId <= 0 || $professionId <= 0) {
            return $defaults;
        }

        $rows = RaterWeight::query()
            ->where('assessment_period_id', $periodId)
            ->where('unit_id', $unitId)
            ->where('performance_criteria_id', $criteriaId)
            ->where('assessee_profession_id', $professionId)
            ->where('status', 'active')
            ->selectRaw('assessor_type, SUM(weight) as weight')
            ->groupBy('assessor_type')
            ->get();

        if ($rows->isEmpty()) {
            return $defaults;
        }

        $out = $defaults;
        foreach ($rows as $row) {
            $out[(string) $row->assessor_type] = (float) $row->weight;
        }
        return $out;
    }
}
