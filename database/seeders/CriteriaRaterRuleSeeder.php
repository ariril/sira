<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class CriteriaRaterRuleSeeder extends Seeder
{
    public function run(): void
    {
        if (!Schema::hasTable('criteria_rater_rules') || !Schema::hasTable('performance_criterias')) {
            return;
        }

        $now = now();

        $criteriaId = fn (string $name) => (int) (DB::table('performance_criterias')->where('name', $name)->value('id') ?? 0);

        $kedisId = $criteriaId('Kedisiplinan (360)');
        $kerjaId = $criteriaId('Kerjasama (360)');

        $matrix = [];

        // Matrix per requirement:
        // - Kedisiplinan (360): supervisor (utama), self (kecil) => allowed types: supervisor, self
        // - Kerjasama (360): peer (utama), supervisor/subordinate/self (kecil) => allowed types: supervisor, peer, subordinate, self
        if ($kedisId > 0) {
            $matrix[$kedisId] = ['supervisor', 'self'];
        }
        if ($kerjaId > 0) {
            $matrix[$kerjaId] = ['supervisor', 'peer', 'subordinate', 'self'];
        }

        if (empty($matrix)) {
            return;
        }

        DB::transaction(function () use ($matrix, $now) {
            foreach ($matrix as $pcId => $allowedTypes) {
                $allowedTypes = array_values(array_unique(array_values(array_filter($allowedTypes))));

                if (empty($allowedTypes)) {
                    continue;
                }

                DB::table('criteria_rater_rules')
                    ->where('performance_criteria_id', (int) $pcId)
                    ->whereNotIn('assessor_type', $allowedTypes)
                    ->delete();

                $rows = [];
                foreach ($allowedTypes as $type) {
                    $rows[] = [
                        'performance_criteria_id' => (int) $pcId,
                        'assessor_type' => (string) $type,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ];
                }

                DB::table('criteria_rater_rules')->upsert(
                    $rows,
                    ['performance_criteria_id', 'assessor_type'],
                    ['updated_at']
                );
            }
        });
    }
}
