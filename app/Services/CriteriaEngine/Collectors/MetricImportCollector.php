<?php

namespace App\Services\CriteriaEngine\Collectors;

use App\Models\AssessmentPeriod;
use App\Models\PerformanceCriteria;
use App\Services\CriteriaEngine\Contracts\CriteriaCollector;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class MetricImportCollector implements CriteriaCollector
{
    public function __construct(private readonly PerformanceCriteria $criteria)
    {
    }

    public function key(): string
    {
        return 'metric:' . (int) $this->criteria->id;
    }

    public function label(): string
    {
        return (string) ($this->criteria->name ?? ('Metric #' . (int) $this->criteria->id));
    }

    public function type(): string
    {
        $type = $this->criteria->type?->value ?? (string) ($this->criteria->type ?? 'benefit');
        return $type === 'cost' ? 'cost' : 'benefit';
    }

    public function source(): string
    {
        return 'metric_import';
    }

    public function collect(AssessmentPeriod $period, int $unitId, array $userIds): array
    {
        if (empty($userIds)) {
            return [];
        }

        $q = DB::table('imported_criteria_values')
            ->selectRaw('user_id, COALESCE(SUM(value_numeric),0) as total_value')
            ->where('assessment_period_id', (int) $period->id)
            ->where('performance_criteria_id', (int) $this->criteria->id)
            ->whereIn('user_id', $userIds)
            ->groupBy('user_id');

        if (Schema::hasColumn('imported_criteria_values', 'is_active')) {
            $q->where('is_active', 1);
        }

        return $q->pluck('total_value', 'user_id')
            ->map(fn($v) => (float) $v)
            ->all();
    }

    public function readiness(AssessmentPeriod $period, int $unitId): array
    {
        $q = DB::table('imported_criteria_values as cm')
            ->join('users as u', 'u.id', '=', 'cm.user_id')
            ->where('cm.assessment_period_id', (int) $period->id)
            ->where('cm.performance_criteria_id', (int) $this->criteria->id)
            ->where('u.unit_id', $unitId)
            ;

        if (Schema::hasColumn('imported_criteria_values', 'is_active')) {
            $q->where('cm.is_active', 1);
        }

        $count = $q->count();

        return $count > 0
            ? ['status' => 'ready', 'message' => null]
            : ['status' => 'missing_data', 'message' => 'Data metric (import) untuk kriteria ini belum ada pada periode ini.'];
    }
}
