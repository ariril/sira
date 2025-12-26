<?php

namespace App\Services\MultiRater;

use App\Models\AssessmentPeriod;
use App\Models\MultiRaterAssessmentDetail;
use App\Models\PerformanceCriteria;
use App\Models\RaterWeight;
use App\Models\User;
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
            $professionId = (int) (User::query()->whereKey($userId)->value('profession_id') ?? 0);
            $weights = self::resolveActiveWeights((int) $selectedPeriod->id, $professionId);

            $avgByCriteriaAndType = DB::table('multi_rater_assessment_details as d')
                ->join('multi_rater_assessments as mra', 'mra.id', '=', 'd.multi_rater_assessment_id')
                ->selectRaw('d.performance_criteria_id as criteria_id, mra.assessor_type as assessor_type, AVG(d.score) as avg_score')
                ->where('mra.assessee_id', $userId)
                ->where('mra.assessment_period_id', $selectedPeriod->id)
                ->where('mra.status', 'submitted')
                ->groupBy('d.performance_criteria_id', 'mra.assessor_type')
                ->get();

            $avgMap = [];
            foreach ($avgByCriteriaAndType as $row) {
                $cid = (int) ($row->criteria_id ?? 0);
                $type = (string) ($row->assessor_type ?? '');
                $avgMap[$cid][$type] = (float) ($row->avg_score ?? 0);
            }

            $rows = PerformanceCriteria::query()
                ->where('is_360', true)
                ->where('is_active', true)
                ->orderBy('name')
                ->get()
                ->map(function ($criteria) use ($avgMap, $weights) {
                    $type = $criteria->type?->value ?? 'benefit';
                    $avgs = $avgMap[(int) $criteria->id] ?? [];
                    $final = 0.0;
                    $hasAny = false;
                    foreach (['self', 'supervisor', 'peer', 'subordinate'] as $assessorType) {
                        if (array_key_exists($assessorType, $avgs)) {
                            $hasAny = true;
                        }
                        $avg = (float) ($avgs[$assessorType] ?? 0.0);
                        $w = (float) ($weights[$assessorType] ?? 0.0);
                        $final += $avg * ($w / 100.0);
                    }
                    return [
                        'id' => $criteria->id,
                        'name' => $criteria->name,
                        'type' => $type,
                        'type_label' => $type === 'cost' ? 'Cost' : 'Benefit',
                        'avg_score' => $hasAny ? round($final, 2) : null,
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

    /**
     * @return array<string,float> assessor_type => weight
     */
    protected static function resolveActiveWeights(int $periodId, int $professionId): array
    {
        $defaults = [
            'supervisor' => 40.0,
            'peer' => 30.0,
            'subordinate' => 20.0,
            'self' => 10.0,
        ];

        if ($periodId <= 0 || $professionId <= 0) {
            return $defaults;
        }

        $rows = RaterWeight::query()
            ->where('assessment_period_id', $periodId)
            ->where('assessee_profession_id', $professionId)
            ->where('status', 'active')
            ->get(['assessor_type', 'weight']);

        if ($rows->isEmpty()) {
            return $defaults;
        }

        $out = $defaults;
        foreach ($rows as $row) {
            $out[(string) $row->assessor_type] = (float) $row->weight;
        }
        return $out;
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
