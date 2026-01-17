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
            $user = User::query()->whereKey($userId)->first(['id', 'unit_id', 'profession_id']);
            $unitId = (int) ($user?->unit_id ?? 0);
            $professionId = (int) ($user?->profession_id ?? 0);

            $weightRows = collect();
            if ($unitId > 0 && $professionId > 0) {
                $weightRows = RaterWeight::query()
                    ->where('assessment_period_id', (int) $selectedPeriod->id)
                    ->where('unit_id', $unitId)
                    ->where('assessee_profession_id', $professionId)
                    ->where(function ($q) {
                        $q->where('status', 'active')
                            ->orWhere(function ($q) {
                                $q->where('status', 'archived')
                                    ->where('was_active_before', true);
                            });
                    })
                    ->get(['performance_criteria_id', 'assessor_type', 'weight']);
            }

            $weightMap = [];
            foreach ($weightRows as $rw) {
                $cid = (int) ($rw->performance_criteria_id ?? 0);
                $type = (string) ($rw->assessor_type ?? '');
                $lvl = $rw->assessor_level === null ? null : (int) $rw->assessor_level;
                if ($cid > 0 && $type !== '') {
                    $key = ($type === 'supervisor' && $lvl && $lvl > 0) ? ('supervisor:' . $lvl) : $type;
                    $weightMap[$cid][$key] = (float) (($weightMap[$cid][$key] ?? 0.0) + (float) ($rw->weight ?? 0));
                }
            }

            // If any supervisor level weights exist for a criteria, ignore legacy supervisor weight key.
            foreach ($weightMap as $cid => $map) {
                $hasSupervisorLevels = false;
                foreach (array_keys($map) as $k) {
                    if (is_string($k) && str_starts_with($k, 'supervisor:')) {
                        $hasSupervisorLevels = true;
                        break;
                    }
                }
                if ($hasSupervisorLevels) {
                    unset($weightMap[$cid]['supervisor']);
                }
            }

            $avgByCriteriaAndType = DB::table('multi_rater_assessment_details as d')
                ->join('multi_rater_assessments as mra', 'mra.id', '=', 'd.multi_rater_assessment_id')
                ->selectRaw('d.performance_criteria_id as criteria_id, mra.assessor_type as assessor_type, mra.assessor_level as assessor_level, AVG(d.score) as avg_score, COUNT(*) as n')
                ->where('mra.assessee_id', $userId)
                ->where('mra.assessment_period_id', $selectedPeriod->id)
                ->where('mra.status', 'submitted')
                ->groupBy('d.performance_criteria_id', 'mra.assessor_type', 'mra.assessor_level')
                ->get();

            $avgMap = [];
            $supervisorSumByCriteria = [];
            $supervisorNByCriteria = [];
            foreach ($avgByCriteriaAndType as $row) {
                $cid = (int) ($row->criteria_id ?? 0);
                $type = (string) ($row->assessor_type ?? '');

                $avg = (float) ($row->avg_score ?? 0);
                $n = (int) ($row->n ?? 0);
                $lvl = $row->assessor_level === null ? null : (int) $row->assessor_level;

                if ($type === 'supervisor' && $lvl && $lvl > 0) {
                    $avgMap[$cid]['supervisor:' . $lvl] = $avg;
                } else {
                    $avgMap[$cid][$type] = $avg;
                }

                if ($type === 'supervisor' && $n > 0) {
                    $supervisorSumByCriteria[$cid] = (float) (($supervisorSumByCriteria[$cid] ?? 0.0) + ($avg * $n));
                    $supervisorNByCriteria[$cid] = (int) (($supervisorNByCriteria[$cid] ?? 0) + $n);
                }
            }

            foreach ($supervisorNByCriteria as $cid => $n) {
                $n = (int) $n;
                if ($n > 0) {
                    $avgMap[(int) $cid]['supervisor'] = (float) (($supervisorSumByCriteria[(int) $cid] ?? 0.0) / $n);
                }
            }

            $rows = PerformanceCriteria::query()
                ->where('is_360', true)
                ->where('is_active', true)
                ->orderBy('name')
                ->get()
                ->map(function ($criteria) use ($avgMap, $weightMap) {
                    $type = $criteria->type?->value ?? 'benefit';
                    $avgs = $avgMap[(int) $criteria->id] ?? [];

                    $defaults = [
                        'supervisor' => 40.0,
                        'peer' => 30.0,
                        'subordinate' => 20.0,
                        'self' => 10.0,
                    ];
                    $zeroWeights = array_fill_keys(array_keys($defaults), 0.0);
                    $criteriaWeightCfg = (array) ($weightMap[(int) $criteria->id] ?? []);
                    $weights = !empty($criteriaWeightCfg)
                        ? array_merge($zeroWeights, $criteriaWeightCfg)
                        : $defaults;

                    if (empty($avgs)) {
                        return [
                            'id' => $criteria->id,
                            'name' => $criteria->name,
                            'type' => $type,
                            'type_label' => $type === 'cost' ? 'Cost' : 'Benefit',
                            'avg_score' => null,
                        ];
                    }

                    // IMPORTANT:
                    // Missing assessor types must contribute 0 (do NOT renormalize by available weights).
                    // Final score = Σ(avg_type * weight_type/100).
                    $weightedSum = 0.0;
                    foreach (['self', 'supervisor', 'peer', 'subordinate'] as $assessorType) {
                        $avg = (float) ($avgs[$assessorType] ?? 0.0);
                        $w = (float) ($weights[$assessorType] ?? 0.0);
                        if ($w <= 0.0) {
                            continue;
                        }
                        $weightedSum += $avg * ($w / 100.0);
                    }

                    $final = max(0.0, min(100.0, $weightedSum));
                    return [
                        'id' => $criteria->id,
                        'name' => $criteria->name,
                        'type' => $type,
                        'type_label' => $type === 'cost' ? 'Cost' : 'Benefit',
                        'avg_score' => is_null($final) ? null : round($final, 2),
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
    // resolveActiveWeights removed (weights are per-criteria now)

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
