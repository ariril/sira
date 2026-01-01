<?php

namespace App\Services\CriteriaEngine;

use App\Models\AssessmentPeriod;
use App\Models\PerformanceCriteria;
use App\Services\CriteriaEngine\Collectors\AttendanceCollector;
use App\Services\CriteriaEngine\Collectors\ContributionCollector;
use App\Services\CriteriaEngine\Collectors\LateMinutesCollector;
use App\Services\CriteriaEngine\Collectors\MetricImportCollector;
use App\Services\CriteriaEngine\Collectors\MultiRaterCollector;
use App\Services\CriteriaEngine\Collectors\OvertimeCollector;
use App\Services\CriteriaEngine\Collectors\RatingCollector;
use App\Services\CriteriaEngine\Collectors\WorkHoursCollector;
use App\Services\CriteriaEngine\Contracts\CriteriaCollector;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

class CriteriaRegistry
{
    /**
     * IMPORTANT: these names must match seeded system criteria names.
     * This is what makes system criteria "locked" and stable.
     */
    private const SYSTEM_CRITERIA_NAME_TO_KEY = [
        'Kehadiran (Absensi)' => 'attendance',
        'Jam Kerja (Absensi)' => 'work_hours',
        'Lembur (Absensi)' => 'overtime',
        'Keterlambatan (Absensi)' => 'late_minutes',
        'Kontribusi Tambahan' => 'contribution',
        'Rating' => 'rating',
    ];

    /** @var array<string, CriteriaCollector> */
    private array $systemCollectors;

    public function __construct()
    {
        $this->systemCollectors = [
            'attendance' => new AttendanceCollector(),
            'work_hours' => new WorkHoursCollector(),
            'overtime' => new OvertimeCollector(),
            'late_minutes' => new LateMinutesCollector(),
            'contribution' => new ContributionCollector(),
            'rating' => new RatingCollector(),
        ];
    }

    public function systemKeyByName(string $name): ?string
    {
        return self::SYSTEM_CRITERIA_NAME_TO_KEY[$name] ?? null;
    }

    public function systemNameByKey(string $key): ?string
    {
        foreach (self::SYSTEM_CRITERIA_NAME_TO_KEY as $name => $k) {
            if ($k === $key) {
                return $name;
            }
        }
        return null;
    }

    public function keyForCriteria(PerformanceCriteria $criteria): ?string
    {
        $name = (string) $criteria->name;
        $source = (string) ($criteria->source ?? '');
        $inputMethod = (string) ($criteria->input_method ?? '');
        $is360 = (bool) $criteria->is_360;

        if (($source === 'system') || ($inputMethod === 'system') || isset(self::SYSTEM_CRITERIA_NAME_TO_KEY[$name])) {
            return self::SYSTEM_CRITERIA_NAME_TO_KEY[$name] ?? null;
        }

        if ($source === 'assessment_360' || $inputMethod === '360' || $is360) {
            return '360:' . (int) $criteria->id;
        }

        return 'metric:' . (int) $criteria->id;
    }

    /**
     * @return array<int, string>
     */
    public function systemKeys(): array
    {
        return array_values(self::SYSTEM_CRITERIA_NAME_TO_KEY);
    }

    public function byKey(string $key): ?CriteriaCollector
    {
        if (isset($this->systemCollectors[$key])) {
            return $this->systemCollectors[$key];
        }

        if (str_starts_with($key, 'metric:')) {
            $id = (int) substr($key, strlen('metric:'));
            $criteria = PerformanceCriteria::query()->find($id);
            return $criteria ? new MetricImportCollector($criteria) : null;
        }

        if (str_starts_with($key, '360:')) {
            $id = (int) substr($key, strlen('360:'));
            $criteria = PerformanceCriteria::query()->find($id);
            return $criteria ? new MultiRaterCollector($criteria) : null;
        }

        return null;
    }

    /**
     * Resolve collectors for ACTIVE criteria based on DB configuration (unit_criteria_weights)
     * for a specific period+unit (+profession is currently ignored because weights table
     * is per unit).
     *
     * @return array<int, CriteriaCollector>
     */
    public function getCollectorsForPeriod(AssessmentPeriod $period, int $unitId, ?int $professionId = null): array
    {
        $activeCriteriaIds = $this->resolveActiveCriteriaIds($period, $unitId);
        if (empty($activeCriteriaIds)) {
            return [];
        }

        $rows = PerformanceCriteria::query()
            ->whereIn('id', $activeCriteriaIds)
            ->get($this->criteriaSelectColumns());

        $collectorsByOrder = [];
        foreach ($rows as $criteria) {
            $collector = $this->collectorForCriteriaRow($criteria);
            if (!$collector) {
                Log::warning('CriteriaRegistry: no collector for criteria', [
                    'criteria_id' => (int) $criteria->id,
                    'name' => (string) $criteria->name,
                    'input_method' => (string) ($criteria->input_method ?? ''),
                    'is_360' => (bool) $criteria->is_360,
                    'source' => (string) ($criteria->source ?? ''),
                ]);
                continue;
            }
            $collectorsByOrder[(int) $criteria->id] = $collector;
        }

        ksort($collectorsByOrder);
        return array_values($collectorsByOrder);
    }

    /** @return array<int, string> */
    private function criteriaSelectColumns(): array
    {
        $cols = ['id', 'name', 'input_method', 'is_360', 'type', 'is_active'];
        if (Schema::hasColumn('performance_criterias', 'source')) {
            $cols[] = 'source';
        }
        return $cols;
    }

    /**
     * Map a DB PerformanceCriteria row into a collector.
     */
    private function collectorForCriteriaRow(PerformanceCriteria $criteria): ?CriteriaCollector
    {
        $key = $this->keyForCriteria($criteria);
        if (!$key) {
            return null;
        }

        if (isset($this->systemCollectors[$key])) {
            return $this->systemCollectors[$key];
        }

        if (str_starts_with($key, '360:')) {
            return new MultiRaterCollector($criteria);
        }

        return new MetricImportCollector($criteria);
    }

    /**
     * Determine active criteria IDs from DB configuration.
     *
     * Uses existing business rule:
     * - Active period: status=active only
     * - Non-active: prefer status=active else status=archived
     *
     * @return array<int, int>
     */
    private function resolveActiveCriteriaIds(AssessmentPeriod $period, int $unitId): array
    {
        $periodId = (int) $period->id;
        if ($periodId <= 0 || $unitId <= 0) {
            return [];
        }

        $isActive = (string) ($period->status ?? '') === AssessmentPeriod::STATUS_ACTIVE;
        $statuses = $isActive ? ['active'] : ['active', 'archived'];

        $rows = DB::table('unit_criteria_weights')
            ->where('unit_id', $unitId)
            ->where('assessment_period_id', $periodId)
            ->whereIn('status', $statuses)
            ->get(['performance_criteria_id', 'status']);

        if ($rows->isEmpty()) {
            return [];
        }

        if (!$isActive && $rows->contains(fn($r) => (string) $r->status === 'active')) {
            $rows = $rows->filter(fn($r) => (string) $r->status === 'active');
        } elseif (!$isActive) {
            $rows = $rows->filter(fn($r) => (string) $r->status === 'archived');
        }

        return $rows
            ->pluck('performance_criteria_id')
            ->map(fn($v) => (int) $v)
            ->unique()
            ->values()
            ->all();
    }
}
