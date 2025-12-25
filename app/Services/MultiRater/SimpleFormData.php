<?php

namespace App\Services\MultiRater;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Collection;

class SimpleFormData
{
    /**
     * @param  Collection  $targets  Collection of stdClass/array with id, name, etc.
     * @param  callable  $criteriaResolver  function ($target): iterable criteria rows
     */
    public static function build(int $periodId, int $raterId, Collection $targets, callable $criteriaResolver): array
    {
        $completedByTarget = DB::table('multi_rater_assessments as mra')
            ->join('multi_rater_assessment_details as d', 'd.multi_rater_assessment_id', '=', 'mra.id')
            ->where('mra.assessment_period_id', $periodId)
            ->where('mra.assessor_id', $raterId)
            ->select(['mra.assessee_id as target_user_id', 'd.performance_criteria_id'])
            ->get()
            ->groupBy('target_user_id')
            ->map(function ($rows) {
                return collect($rows)
                    ->pluck('performance_criteria_id')
                    ->filter()
                    ->map(fn($val) => (int) $val)
                    ->unique()
                    ->values();
            });

        $criteriaCatalog = collect();
        $preparedTargets = collect();
        $totalAssignments = 0;
        $completedAssignments = 0;

        foreach ($targets as $target) {
            $criteriaData = $criteriaResolver($target);
            $criteria = $criteriaData instanceof Collection ? $criteriaData : collect($criteriaData);
            $criteria = $criteria->filter(fn($c) => !empty($c['id'] ?? null));
            if ($criteria->isEmpty()) {
                continue;
            }

            $criteriaCatalog = $criteriaCatalog->merge($criteria->keyBy('id'));

            $criteriaIds = $criteria->pluck('id')->map(fn($val) => (int) $val)->values();
            $totalAssignments += $criteriaIds->count();

            $completed = collect($completedByTarget->get($target->id, collect()))
                ->map(fn($val) => (int) $val)
                ->all();

            $pending = array_values(array_diff($criteriaIds->all(), $completed));
            $completedAssignments += count($criteriaIds) - count($pending);

            if (empty($pending)) {
                continue;
            }

            $searchSource = trim(($target->name ?? '') . ' ' . ($target->employee_number ?? ''));
            $searchable = function_exists('mb_strtolower') ? mb_strtolower($searchSource) : strtolower($searchSource);

            $preparedTargets->push([
                'id' => $target->id,
                'name' => $target->name,
                'label' => $target->label ?? $target->name,
                'unit_name' => $target->unit_name ?? null,
                'employee_number' => $target->employee_number ?? null,
                'searchable' => $searchable,
                'criteria_ids' => $criteriaIds->all(),
                'pending_criteria' => $pending,
            ]);
        }

        $remainingAssignments = max(0, $totalAssignments - $completedAssignments);

        return [
            'targets' => $preparedTargets->values(),
            'criteria_catalog' => $criteriaCatalog->values()->unique('id')->values(),
            'remaining_assignments' => $remainingAssignments,
            'completed_assignments' => $completedAssignments,
            'total_assignments' => $totalAssignments,
        ];
    }
}
