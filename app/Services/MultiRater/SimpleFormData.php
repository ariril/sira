<?php

namespace App\Services\MultiRater;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Collection;

class SimpleFormData
{
    /**
     * @param  Collection  $targets  Collection of stdClass/array with id, name, etc.
     * @param  callable  $contextResolver  function ($target): array{assessor_type:string, criteria:iterable}
     */
    public static function build(int $periodId, int $raterId, ?int $assessorProfessionId, Collection $targets, callable $contextResolver): array
    {
        $completedByTarget = DB::table('multi_rater_assessments as mra')
            ->join('multi_rater_assessment_details as d', 'd.multi_rater_assessment_id', '=', 'mra.id')
            ->where('mra.assessment_period_id', $periodId)
            ->where('mra.assessor_id', $raterId)
            ->when($assessorProfessionId, fn($q) => $q->where('mra.assessor_profession_id', $assessorProfessionId))
            ->select(['mra.assessee_id as target_user_id', 'mra.assessor_type', 'd.performance_criteria_id'])
            ->get()
            ->groupBy(function ($row) {
                $type = $row->assessor_type ?? 'peer';
                return ((int) $row->target_user_id) . ':' . $type;
            })
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
            $context = $contextResolver($target);
            $assessorType = (string) ($context['assessor_type'] ?? 'peer');
            $criteriaData = $context['criteria'] ?? [];
            $criteria = $criteriaData instanceof Collection ? $criteriaData : collect($criteriaData);
            $criteria = $criteria->filter(fn($c) => !empty($c['id'] ?? null));
            if ($criteria->isEmpty()) {
                continue;
            }

            $criteriaCatalog = $criteriaCatalog->merge($criteria->keyBy('id'));

            $criteriaIds = $criteria->pluck('id')->map(fn($val) => (int) $val)->values();
            $totalAssignments += $criteriaIds->count();

            $completedKey = ((int) $target->id) . ':' . $assessorType;
            $completed = collect($completedByTarget->get($completedKey, collect()))
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
                'assessor_type' => $assessorType,
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
