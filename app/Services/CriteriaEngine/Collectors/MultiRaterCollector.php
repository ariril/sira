<?php

namespace App\Services\CriteriaEngine\Collectors;

use App\Models\AssessmentPeriod;
use App\Models\PerformanceCriteria;
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

        return DB::table('multi_rater_assessment_details as mrad')
            ->join('multi_rater_assessments as mra', 'mra.id', '=', 'mrad.multi_rater_assessment_id')
            ->selectRaw('mra.assessee_id as user_id, AVG(mrad.score) as avg_score')
            ->where('mra.assessment_period_id', (int) $period->id)
            ->where('mra.status', 'submitted')
            ->whereIn('mra.assessee_id', $userIds)
            ->where('mrad.performance_criteria_id', (int) $this->criteria->id)
            ->groupBy('mra.assessee_id')
            ->pluck('avg_score', 'user_id')
            ->map(fn($v) => (float) $v)
            ->all();
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
