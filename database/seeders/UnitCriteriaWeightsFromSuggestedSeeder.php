<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class UnitCriteriaWeightsFromSuggestedSeeder extends Seeder
{
    public function run(): void
    {
        if (!Schema::hasTable('performance_criterias') || !Schema::hasTable('units') || !Schema::hasTable('assessment_periods') || !Schema::hasTable('unit_criteria_weights')) {
            $this->command?->warn('Skipping UnitCriteriaWeightsFromSuggestedSeeder: required tables not found.');
            return;
        }

        $criteria = DB::table('performance_criterias')
            ->where('is_active', 1)
            ->whereNotNull('suggested_weight')
            ->get(['id', 'suggested_weight']);

        if ($criteria->isEmpty()) {
            $this->command?->warn('No active performance_criterias with suggested_weight found.');
            return;
        }

        $criteriaWeightsById = $criteria->pluck('suggested_weight', 'id');
        $criteriaIds = $criteriaWeightsById->keys()->all();

        $unitIds = DB::table('units')->pluck('id')->all();

        // Only seed for periods that are not finished.
        // For finished periods (locked/approval/closed/archived), weights must not be created/left as active.
        $periods = DB::table('assessment_periods')
            ->whereIn('status', ['draft', 'active'])
            ->get(['id', 'status']);

        $now = now();
        $inserted = 0;

        $hasWasActiveBefore = Schema::hasColumn('unit_criteria_weights', 'was_active_before');

        foreach ($periods as $period) {
            $periodId = (int) ($period->id ?? 0);
            if ($periodId <= 0) {
                continue;
            }

            $periodStatus = (string) ($period->status ?? 'draft');
            $targetStatus = $periodStatus === 'active' ? 'active' : 'draft';

            foreach ($unitIds as $unitId) {
                // If the unit already has any working weights for this period, do nothing.
                $hasAny = DB::table('unit_criteria_weights')
                    ->where('assessment_period_id', $periodId)
                    ->where('unit_id', $unitId)
                    ->where('status', '!=', 'archived')
                    ->exists();

                if ($hasAny) {
                    continue;
                }

                $rows = [];

                foreach ($criteriaIds as $criteriaId) {
                    $row = [
                        'unit_id' => $unitId,
                        'performance_criteria_id' => $criteriaId,
                        'assessment_period_id' => $periodId,
                        'status' => $targetStatus,
                        'weight' => (float) ($criteriaWeightsById[$criteriaId] ?? 0),
                        'created_at' => $now,
                        'updated_at' => $now,
                    ];

                    if ($hasWasActiveBefore) {
                        $row['was_active_before'] = false;
                    }

                    $rows[] = $row;
                }

                if ($rows) {
                    DB::table('unit_criteria_weights')->insert($rows);
                    $inserted += count($rows);
                }
            }
        }
        $this->command?->info("UnitCriteriaWeightsFromSuggestedSeeder inserted {$inserted} rows (status=active/draft).");
    }
}
