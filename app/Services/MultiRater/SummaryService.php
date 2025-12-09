<?php

namespace App\Services\MultiRater;

use App\Models\AssessmentPeriod;
use App\Models\MultiRaterAssessmentDetail;
use App\Models\PerformanceCriteria;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class SummaryService
{
    public static function build(int $userId, ?int $requestedPeriodId = null): array
    {
        $periods = AssessmentPeriod::orderByDesc('end_date')->get();
        $selectedPeriod = self::resolveSelectedPeriod($periods, $requestedPeriodId);
        $rows = collect();

        if ($selectedPeriod) {
            $averages = MultiRaterAssessmentDetail::query()
                ->select('performance_criteria_id', DB::raw('AVG(score) as avg_score'))
                ->whereHas('header', function ($q) use ($userId, $selectedPeriod) {
                    $q->where('assessee_id', $userId)
                        ->where('assessment_period_id', $selectedPeriod->id)
                        ->where('status', 'submitted');
                })
                ->groupBy('performance_criteria_id')
                ->pluck('avg_score', 'performance_criteria_id');

            $rows = PerformanceCriteria::query()
                ->where('is_360_based', true)
                ->orderBy('name')
                ->get()
                ->map(function ($criteria) use ($averages) {
                    $avg = $averages[$criteria->id] ?? null;
                    $type = $criteria->type?->value ?? 'benefit';
                    return [
                        'id' => $criteria->id,
                        'name' => $criteria->name,
                        'type' => $type,
                        'type_label' => $type === 'cost' ? 'Cost' : 'Benefit',
                        'avg_score' => is_null($avg) ? null : round((float) $avg, 2),
                    ];
                })
                ->filter(fn ($row) => !is_null($row['avg_score']))
                ->values();
        }

        return [
            'periods' => $periods,
            'selected_period' => $selectedPeriod,
            'rows' => $rows,
        ];
    }

    protected static function resolveSelectedPeriod(Collection $periods, ?int $requested): ?AssessmentPeriod
    {
        if ($periods->isEmpty()) {
            return null;
        }

        if ($requested) {
            $match = $periods->firstWhere('id', (int) $requested);
            if ($match) {
                return $match;
            }
        }

        $today = now();
        $current = $periods->first(function ($period) use ($today) {
            if (!$period->start_date || !$period->end_date) {
                return false;
            }
            return $today->between($period->start_date, $period->end_date);
        });

        return $current ?? $periods->first();
    }
}
