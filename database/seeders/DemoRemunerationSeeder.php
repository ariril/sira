<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class DemoRemunerationSeeder extends Seeder
{
    public function run(): void
    {
        if (!Schema::hasTable('assessment_periods') || !Schema::hasTable('users')) {
            return;
        }

        if (!Schema::hasTable('unit_profession_remuneration_allocations') || !Schema::hasTable('remunerations')) {
            return;
        }

        $period = DB::table('assessment_periods')->where('name', 'November 2025')->first();
        if (!$period) {
            return;
        }

        $emails = [
            'kepala.umum@rsud.local',
            'dokter.umum1@rsud.local',
            'dokter.umum2@rsud.local',
            'perawat1@rsud.local',
            'perawat2@rsud.local',
            'kepala.gigi@rsud.local',
            'dokter.spes1@rsud.local',
            'dokter.spes2@rsud.local',
        ];

        $users = DB::table('users')
            ->whereIn('email', $emails)
            ->get(['id', 'email', 'unit_id', 'profession_id']);

        if ($users->count() !== count($emails)) {
            $missing = array_values(array_diff($emails, $users->pluck('email')->all()));
            throw new \RuntimeException('DemoRemunerationSeeder: user tidak ditemukan: ' . implode(', ', $missing));
        }

        $now = now();
        $periodId = (int) $period->id;

        // Group by (unit_id, profession_id) for this period.
        $groups = $users
            ->groupBy(fn ($u) => (int) $u->unit_id . ':' . (int) $u->profession_id);

        // Simple demo policy:
        // - allocation amount is (count * perUserAmount)
        // - remuneration per user is allocation / count (dibagi rata)
        $perUserAmount = 1000000.00;

        foreach ($groups as $key => $groupUsers) {
            $first = $groupUsers->first();
            $unitId = (int) $first->unit_id;
            $professionId = (int) $first->profession_id;
            $count = max(1, (int) $groupUsers->count());

            if ($unitId <= 0 || $professionId <= 0) {
                continue;
            }

            $allocationAmount = round($count * $perUserAmount, 2);
            $perUser = round($allocationAmount / $count, 2);

            DB::table('unit_profession_remuneration_allocations')->updateOrInsert(
                [
                    'assessment_period_id' => $periodId,
                    'unit_id' => $unitId,
                    'profession_id' => $professionId,
                ],
                [
                    'amount' => $allocationAmount,
                    'note' => 'Seeder demo: alokasi untuk ' . $count . ' pegawai (dibagi rata).',
                    'published_at' => $now,
                    'revised_by' => null,
                    'updated_at' => $now,
                    'created_at' => $now,
                ]
            );

            foreach ($groupUsers as $u) {
                DB::table('remunerations')->updateOrInsert(
                    [
                        'user_id' => (int) $u->id,
                        'assessment_period_id' => $periodId,
                    ],
                    [
                        'amount' => $perUser,
                        'payment_date' => null,
                        'payment_status' => 'Belum Dibayar',
                        'calculation_details' => json_encode([
                            'policy' => 'demo_equal_split',
                            'allocation_amount' => $allocationAmount,
                            'split_count' => $count,
                            'per_user_amount' => $perUser,
                            'unit_id' => $unitId,
                            'profession_id' => $professionId,
                        ], JSON_UNESCAPED_UNICODE),
                        'published_at' => $now,
                        'calculated_at' => $now,
                        'revised_by' => null,
                        'updated_at' => $now,
                        'created_at' => $now,
                    ]
                );
            }
        }
    }
}
