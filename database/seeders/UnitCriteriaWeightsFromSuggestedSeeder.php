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
        $periodIds = DB::table('assessment_periods')->pluck('id')->all();

        $now = now();
        $inserted = 0;

        foreach ($periodIds as $periodId) {
            foreach ($unitIds as $unitId) {
                $existing = DB::table('unit_criteria_weights')
                    ->where('assessment_period_id', $periodId)
                    ->where('unit_id', $unitId)
                    ->where('status', 'active')
                    ->pluck('performance_criteria_id')
                    ->all();

                $existingMap = array_fill_keys($existing, true);
                $rows = [];

                foreach ($criteriaIds as $criteriaId) {
                    if (isset($existingMap[$criteriaId])) {
                        continue;
                    }

                    $rows[] = [
                        'unit_id' => $unitId,
                        'performance_criteria_id' => $criteriaId,
                        'assessment_period_id' => $periodId,
                        'status' => 'active',
                        'weight' => (float) ($criteriaWeightsById[$criteriaId] ?? 0),
                        'created_at' => $now,
                        'updated_at' => $now,
                    ];
                }

                if ($rows) {
                    DB::table('unit_criteria_weights')->insert($rows);
                    $inserted += count($rows);
                }
            }
        }
        $this->command?->info("UnitCriteriaWeightsFromSuggestedSeeder inserted {$inserted} rows (status=active).");
    }
}
