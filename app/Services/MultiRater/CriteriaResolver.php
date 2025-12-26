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
            ->unique('performance_criteria_id')
            ->filter(fn($w) => $w->performanceCriteria && (bool) $w->performanceCriteria->is_360 && (bool) $w->performanceCriteria->is_active);

        if ($weights->isNotEmpty()) {
            $weighted = $weights->map(fn($weight) => self::mapCriteria($weight->performanceCriteria));
            $weightedIds = $weighted->pluck('id')->filter()->all();

            $unweighted = PerformanceCriteria::query()
                ->where('is_360', true)
                ->where('is_active', true)
                ->when(!empty($weightedIds), fn($q) => $q->whereNotIn('id', $weightedIds))
                ->orderBy('name')
                ->get()
                ->map(fn(PerformanceCriteria $criteria) => self::mapCriteria($criteria));

            return $weighted->concat($unweighted)->filter(fn($r) => !empty($r['id']))->values();
        }

        return PerformanceCriteria::query()
            ->where('is_360', true)
            ->where('is_active', true)
            ->orderBy('name')
            ->get()
            ->map(fn(PerformanceCriteria $criteria) => self::mapCriteria($criteria));
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
