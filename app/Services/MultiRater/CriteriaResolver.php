<?php

namespace App\Services\MultiRater;

use App\Enums\PerformanceCriteriaType;
use App\Enums\UnitCriteriaWeightStatus;
use App\Models\PerformanceCriteria;
use App\Models\UnitCriteriaWeight;
use Illuminate\Support\Collection;

class CriteriaResolver
{
    public static function forUnit(?int $unitId, int $periodId): Collection
    {
        $weights = UnitCriteriaWeight::query()
            ->with('performanceCriteria')
            ->where('assessment_period_id', $periodId)
            ->when($unitId, fn($q) => $q->where('unit_id', $unitId))
            ->where('status', UnitCriteriaWeightStatus::ACTIVE)
            ->orderBy('id')
            ->get()
            ->unique('performance_criteria_id');

        if ($weights->isNotEmpty()) {
            return $weights->map(fn($weight) => self::mapCriteria($weight->performanceCriteria));
        }

        return PerformanceCriteria::query()
            ->where('is_360_based', true)
            ->where('is_active', true)
            ->orderBy('name')
            ->get()
            ->map(fn($criteria) => self::mapCriteria($criteria));
    }

    protected static function mapCriteria(?PerformanceCriteria $criteria): array
    {
        if (!$criteria) {
            return [];
        }

        $type = $criteria->type?->value ?? PerformanceCriteriaType::BENEFIT->value;
        return [
            'id' => $criteria->id,
            'name' => $criteria->name,
            'type' => $type,
            'type_label' => $type === PerformanceCriteriaType::COST->value ? 'Cost' : 'Benefit',
        ];
    }
}
