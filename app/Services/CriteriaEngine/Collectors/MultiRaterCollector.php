<?php

namespace App\Services\CriteriaEngine\Collectors;

use App\Models\AssessmentPeriod;
use App\Models\PerformanceCriteria;
use App\Services\MultiRater\RaterWeightResolver;
use App\Services\CriteriaEngine\Contracts\CriteriaCollector;
use Illuminate\Support\Facades\DB;

class MultiRaterCollector implements CriteriaCollector
{
    public function __construct(private readonly PerformanceCriteria $criteria)
    {
    }

    public function key(): string
    {
        return '360:' . (int) $this->criteria->id;
    }

    public function label(): string
    {
        return (string) ($this->criteria->name ?? ('360 #' . (int) $this->criteria->id));
    }

    public function type(): string
    {
        $type = $this->criteria->type?->value ?? (string) ($this->criteria->type ?? 'benefit');
        return $type === 'cost' ? 'cost' : 'benefit';
    }

    public function source(): string
    {
        return 'assessment_360';
    }

    public function collect(AssessmentPeriod $period, int $unitId, array $userIds): array
    {
        if (empty($userIds)) {
            return [];
        }

        $userIds = array_values(array_unique(array_map('intval', $userIds)));
        $criteriaId = (int) $this->criteria->id;
        $periodId = (int) $period->id;
        $unitId = (int) $unitId;

        // AVG per assessee + assessor_type
        $avgRows = DB::table('multi_rater_assessment_details as d')
            ->join('multi_rater_assessments as mra', 'mra.id', '=', 'd.multi_rater_assessment_id')
            ->selectRaw('mra.assessee_id as user_id, mra.assessor_type as assessor_type, mra.assessor_level as assessor_level, AVG(d.score) as avg_score, COUNT(*) as n')
            ->where('mra.assessment_period_id', $periodId)
            ->where('mra.status', 'submitted')
            ->whereIn('mra.assessee_id', $userIds)
            ->where('d.performance_criteria_id', $criteriaId)
            ->groupBy('mra.assessee_id', 'mra.assessor_type', 'mra.assessor_level')
            ->get();

        $avgMap = [];
        $supervisorSumByUser = [];
        $supervisorNByUser = [];
        foreach ($avgRows as $row) {
            $uid = (int) ($row->user_id ?? 0);
            $type = (string) ($row->assessor_type ?? '');
            if ($uid <= 0 || $type === '') {
                continue;
            }

            $avg = (float) ($row->avg_score ?? 0.0);
            $n = (int) ($row->n ?? 0);
            $lvl = $row->assessor_level === null ? null : (int) $row->assessor_level;

            if ($type === 'supervisor' && $lvl && $lvl > 0) {
                $avgMap[$uid]['supervisor:' . $lvl] = $avg;
            } else {
                $avgMap[$uid][$type] = $avg;
            }

            if ($type === 'supervisor' && $n > 0) {
                $supervisorSumByUser[$uid] = (float) (($supervisorSumByUser[$uid] ?? 0.0) + ($avg * $n));
                $supervisorNByUser[$uid] = (int) (($supervisorNByUser[$uid] ?? 0) + $n);
            }
        }

        // Overall supervisor average (used when weights are not level-specific).
        foreach ($userIds as $uid) {
            $uid = (int) $uid;
            $n = (int) ($supervisorNByUser[$uid] ?? 0);
            if ($n > 0) {
                $avgMap[$uid]['supervisor'] = (float) (($supervisorSumByUser[$uid] ?? 0.0) / $n);
            }
        }

        // Resolve assessee profession_id so weights can be applied.
        $professionByUserId = DB::table('users')
            ->whereIn('id', $userIds)
            ->pluck('profession_id', 'id')
            ->map(fn($v) => $v === null ? null : (int) $v)
            ->all();

        $professionIds = [];
        foreach ($professionByUserId as $pid) {
            if (is_int($pid) && $pid > 0) {
                $professionIds[$pid] = true;
            }
        }
        $professionIds = array_keys($professionIds);

        $resolvedWeightsByProfession = RaterWeightResolver::resolveForCriteria($periodId, $unitId, $criteriaId, $professionIds);
        $zeroWeights = array_fill_keys(array_keys(RaterWeightResolver::defaults()), 0.0);

        $out = [];
        foreach ($userIds as $uid) {
            $uid = (int) $uid;
            $avgs = $avgMap[$uid] ?? [];
            if (empty($avgs)) {
                $out[$uid] = 0.0;
                continue;
            }

            $professionId = $professionByUserId[$uid] ?? null;
            // IMPORTANT: do NOT fall back to default weights.
            // If explicit rater weights are not configured/active for this profession, treat all weights as 0.
            $weights = $zeroWeights;
            if (is_int($professionId) && $professionId > 0 && isset($resolvedWeightsByProfession[$professionId])) {
                // When explicit weights exist, treat missing keys as 0.
                $weights = array_merge($zeroWeights, (array) $resolvedWeightsByProfession[$professionId]);
            }

            // IMPORTANT:
            // Missing assessor types must contribute 0 (do NOT renormalize by available weights).
            // Final score = Σ(avg_key * weight_key/100), where key can be supervisor:<level>.
            $weightedSum = 0.0;
            foreach ($weights as $key => $w) {
                $w = (float) $w;
                if ($w <= 0.0) {
                    continue;
                }
                $avg = (float) ($avgs[(string) $key] ?? 0.0);
                $weightedSum += $avg * ($w / 100.0);
            }

            // Defensive clamp (data should normally keep weights sum to 100).
            $out[$uid] = max(0.0, min(100.0, $weightedSum));
        }

        return $out;
    }

    public function readiness(AssessmentPeriod $period, int $unitId): array
    {
        $count = DB::table('multi_rater_assessment_details as d')
            ->join('multi_rater_assessments as mra', 'mra.id', '=', 'd.multi_rater_assessment_id')
            ->join('users as u', 'u.id', '=', 'mra.assessee_id')
            ->where('mra.assessment_period_id', (int) $period->id)
            ->where('mra.status', 'submitted')
            ->where('d.performance_criteria_id', (int) $this->criteria->id)
            ->where('u.unit_id', $unitId)
            ->count();

        return $count > 0
            ? ['status' => 'ready', 'message' => null]
            : ['status' => 'missing_data', 'message' => 'Belum ada data penilaian 360 (submitted) untuk kriteria ini pada periode ini.'];
    }
}
