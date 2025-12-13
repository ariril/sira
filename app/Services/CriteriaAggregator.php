<?php

namespace App\Services;

use App\Models\CriteriaMetric;
use App\Models\PerformanceAssessment;
use App\Models\PerformanceAssessmentDetail;
use App\Models\PerformanceCriteria;
use App\Models\RaterTypeWeight;
use App\Models\MultiRaterAssessment;
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
                $metric = CriteriaMetric::where('user_id', $userId)
                    ->where('assessment_period_id', $periodId)
                    ->where('performance_criteria_id', $criteria->id)
                    ->first();

                if ($criteria->input_method === '360') {
                    $score = $this->aggregate360($criteria->id, $userId, $periodId);
                    // Persist metric for trace if not present
                    if (!$metric && $score !== null) {
                        $metric = CriteriaMetric::create([
                            'user_id' => $userId,
                            'assessment_period_id' => $periodId,
                            'performance_criteria_id' => $criteria->id,
                            'value_numeric' => $score,
                            'source_type' => 'system',
                            'source_table' => 'multi_rater_assessment_details',
                            'source_id' => null,
                        ]);
                    } elseif ($metric && $score !== null) {
                        $metric->update(['value_numeric' => $score, 'source_table' => 'multi_rater_assessment_details']);
                    }
                } else {
                    // For non-360, assume metric is precomputed/imported; normalize as-is (0-100 expected)
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

    // Weighted aggregation for 360 based on rater_type_weights. Returns 0-100 or null.
    private function aggregate360(int $criteriaId, int $userId, int $periodId): ?float
    {
        $weights = RaterTypeWeight::where('performance_criteria_id', $criteriaId)->get()->keyBy('assessor_type');
        if ($weights->isEmpty()) {
            // default: supervisor 40, peer 30, subordinate 20, self 10
            $defaults = [
                'supervisor' => 40.0,
                'peer' => 30.0,
                'subordinate' => 20.0,
                'self' => 10.0,
            ];
        } else {
            $defaults = $weights->mapWithKeys(fn($w) => [$w->assessor_type => (float)$w->weight])->all();
        }

        // Average per assessor_type for the criteria
        $rows = MultiRaterAssessment::query()
            ->where('assessee_id', $userId)
            ->where('assessment_period_id', $periodId)
            ->whereHas('details', function ($q) use ($criteriaId) {
                $q->where('performance_criteria_id', $criteriaId);
            })
            ->with(['details' => function ($q) use ($criteriaId) {
                $q->where('performance_criteria_id', $criteriaId);
            }])
            ->get();

        if ($rows->isEmpty()) return null;

        $grouped = [];
        foreach ($rows as $r) {
            $type = $r->assessor_type;
            foreach ($r->details as $d) {
                $grouped[$type][] = (float)$d->score;
            }
        }

        $weightedSum = 0.0; $weightTotal = 0.0;
        foreach ($grouped as $type => $scores) {
            $avg = array_sum($scores) / max(count($scores),1);
            $w = $defaults[$type] ?? 0.0;
            if ($w > 0) { $weightedSum += $avg * $w; $weightTotal += $w; }
        }

        if ($weightTotal <= 0.0) return null;
        return $weightedSum / $weightTotal; // 0-100
    }
}
