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
            ->selectRaw('mra.assessee_id as user_id, mra.assessor_type as assessor_type, AVG(d.score) as avg_score')
            ->where('mra.assessment_period_id', $periodId)
            ->where('mra.status', 'submitted')
            ->whereIn('mra.assessee_id', $userIds)
            ->where('d.performance_criteria_id', $criteriaId)
            ->groupBy('mra.assessee_id', 'mra.assessor_type')
            ->get();

        $avgMap = [];
        foreach ($avgRows as $row) {
            $uid = (int) ($row->user_id ?? 0);
            $type = (string) ($row->assessor_type ?? '');
            if ($uid <= 0 || $type === '') {
                continue;
            }
            $avgMap[$uid][$type] = (float) ($row->avg_score ?? 0.0);
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
        $defaults = RaterWeightResolver::defaults();

        $out = [];
        foreach ($userIds as $uid) {
            $uid = (int) $uid;
            $avgs = $avgMap[$uid] ?? [];
            if (empty($avgs)) {
                $out[$uid] = 0.0;
                continue;
            }

            $professionId = $professionByUserId[$uid] ?? null;
            $weights = $defaults;
            if (is_int($professionId) && $professionId > 0 && isset($resolvedWeightsByProfession[$professionId])) {
                $weights = array_merge($weights, (array) $resolvedWeightsByProfession[$professionId]);
            }

            $weightedSum = 0.0;
            $weightSum = 0.0;
            foreach (['self', 'supervisor', 'peer', 'subordinate'] as $assessorType) {
                if (!array_key_exists($assessorType, $avgs)) {
                    continue;
                }
                $avg = (float) ($avgs[$assessorType] ?? 0.0);
                $w = (float) ($weights[$assessorType] ?? 0.0);
                if ($w <= 0.0) {
                    continue;
                }
                $weightedSum += $avg * ($w / 100.0);
                $weightSum += $w;
            }

            $out[$uid] = $weightSum > 0.0 ? (float) ($weightedSum / ($weightSum / 100.0)) : 0.0;
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
