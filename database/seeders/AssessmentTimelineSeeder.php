<?php

namespace Database\Seeders;

use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class AssessmentTimelineSeeder extends Seeder
{
    public function run(): void
    {
        DB::transaction(function () {
            $now = Carbon::now();

            $periodConfigs = [
                'Desember 2025' => [
                    'start' => '2025-12-01',
                    'end' => '2025-12-31',
                    'status' => 'active',
                ],
            ];

            $periodIds = [];
            foreach ($periodConfigs as $name => $config) {
                $payload = [
                    'start_date' => $config['start'],
                    'end_date' => $config['end'],
                    'status' => $config['status'],
                    'locked_at' => null,
                    'locked_by_id' => null,
                    'closed_at' => $config['status'] === 'closed' ? $now : null,
                    'closed_by_id' => null,
                    'notes' => $config['status'] === 'active'
                        ? 'Periode aktif siap dipakai (diset oleh seeder).'
                        : 'Periode otomatis ditutup oleh seeder.',
                    'updated_at' => $now,
                ];

                $existingId = DB::table('assessment_periods')->where('name', $name)->value('id');
                if ($existingId) {
                    DB::table('assessment_periods')->where('id', $existingId)->update($payload);
                    $periodIds[$name] = $existingId;
                } else {
                    $payload['name'] = $name;
                    $payload['created_at'] = $now;
                    $periodIds[$name] = DB::table('assessment_periods')->insertGetId($payload);
                }
            }

            $poliklinikUmumId = DB::table('units')->where('slug', 'poliklinik-umum')->value('id');
            $decPeriodId = $periodIds['Desember 2025'] ?? null;
            $unitHeadId = DB::table('users')->where('email', 'kepala.unit.medis@rsud.local')->value('id');
            $polyclinicHeadId = DB::table('users')->where('email', 'kepala.poliklinik@rsud.local')->value('id');

            if (!$poliklinikUmumId || !$decPeriodId) {
                throw new RuntimeException('Prasyarat seeder belum tersedia. Pastikan unit dan periode utama sudah ada.');
            }

            $criteriaWeights = [
                'Kedisiplinan' => 40.00,
                'Pelayanan Pasien' => 40.00,
                'Kepatuhan Prosedur' => 20.00,
            ];

            foreach ($criteriaWeights as $criteriaName => $weight) {
                $criteriaId = DB::table('performance_criterias')->where('name', $criteriaName)->value('id');
                if (!$criteriaId) {
                    continue;
                }

                DB::table('unit_criteria_weights')->updateOrInsert(
                    [
                        'unit_id' => $poliklinikUmumId,
                        'performance_criteria_id' => $criteriaId,
                        'assessment_period_id' => $decPeriodId,
                        'status' => 'active',
                    ],
                    [
                        'weight' => $weight,
                        'policy_doc_path' => null,
                        'policy_note' => 'Aktif otomatis untuk periode Desember 2025.',
                        'unit_head_id' => $unitHeadId,
                        'unit_head_note' => null,
                        'polyclinic_head_id' => $polyclinicHeadId,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ]
                );
            }

            $adminRsId = DB::table('users')->where('email', 'admin.rs@rsud.local')->value('id');

            DB::table('assessment_360_windows')
                ->where('assessment_period_id', $decPeriodId)
                ->update([
                    'is_active' => false,
                    'updated_at' => $now,
                ]);

            DB::table('assessment_360_windows')->updateOrInsert(
                [
                    'assessment_period_id' => $decPeriodId,
                    'start_date' => '2025-12-07',
                    'end_date' => '2025-12-08',
                ],
                [
                    'is_active' => true,
                    'opened_by' => $adminRsId,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]
            );
        });
    }
}
