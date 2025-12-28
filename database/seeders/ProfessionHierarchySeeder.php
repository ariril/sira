<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class ProfessionHierarchySeeder extends Seeder
{
    public function run(): void
    {
        if (!Schema::hasTable('professions')) {
            return;
        }

        $now = now();

        // Ensure professions exist (idempotent)
        $ensureProfession = function (string $code, string $name, ?string $description = null) use ($now): int {
            DB::table('professions')->updateOrInsert(
                ['code' => $code],
                [
                    'name' => $name,
                    'description' => $description,
                    'is_active' => 1,
                    'updated_at' => $now,
                    'created_at' => $now,
                ]
            );

            return (int) (DB::table('professions')->where('code', $code)->value('id') ?? 0);
        };

        $dokterUmumId = (int) (DB::table('professions')->where('code', 'DOK-UM')->value('id') ?? 0);
        $perawatId = (int) (DB::table('professions')->where('code', 'PRW')->value('id') ?? 0);

        $kepalaUnitDokId = $ensureProfession(
            code: 'KPL-UNIT-DOK',
            name: 'Kepala Unit (Dokter)',
            description: 'Profesi struktural: kepala unit (dokter)'
        );

        $kepalaPoliDokId = $ensureProfession(
            code: 'KPL-POLI-DOK',
            name: 'Kepala Poliklinik (Dokter)',
            description: 'Profesi struktural: kepala poliklinik (dokter)'
        );

        // Attach professions to seeded users (best-effort, idempotent)
        if (Schema::hasTable('users')) {
            DB::table('users')->where('email', 'kepala.unit.medis@rsud.local')->update(['profession_id' => $kepalaUnitDokId ?: null]);
            DB::table('users')->where('email', 'kepala.gigi@rsud.local')->update(['profession_id' => $kepalaUnitDokId ?: null]);
            DB::table('users')->where('email', 'kepala.poliklinik@rsud.local')->update(['profession_id' => $kepalaPoliDokId ?: null]);
        }

        if (!Schema::hasTable('profession_reporting_lines')) {
            return;
        }

        if ($perawatId <= 0) {
            return;
        }

        // Perawat supervisors: L1 Dokter Umum, L2 Kepala Unit, L3 Kepala Poli
        $lines = [
            ['assessee_profession_id' => $perawatId, 'assessor_profession_id' => $dokterUmumId, 'relation_type' => 'supervisor', 'level' => 1, 'is_required' => 1, 'is_active' => 1],
            ['assessee_profession_id' => $perawatId, 'assessor_profession_id' => $kepalaUnitDokId, 'relation_type' => 'supervisor', 'level' => 2, 'is_required' => 1, 'is_active' => 1],
            ['assessee_profession_id' => $perawatId, 'assessor_profession_id' => $kepalaPoliDokId, 'relation_type' => 'supervisor', 'level' => 3, 'is_required' => 1, 'is_active' => 1],
            ['assessee_profession_id' => $perawatId, 'assessor_profession_id' => $perawatId, 'relation_type' => 'peer', 'level' => null, 'is_required' => 1, 'is_active' => 1],
        ];

        foreach ($lines as $row) {
            if ((int) ($row['assessor_profession_id'] ?? 0) <= 0) {
                continue;
            }

            DB::table('profession_reporting_lines')->updateOrInsert(
                [
                    'assessee_profession_id' => (int) $row['assessee_profession_id'],
                    'assessor_profession_id' => (int) $row['assessor_profession_id'],
                    'relation_type' => (string) $row['relation_type'],
                    'level' => $row['level'],
                ],
                [
                    'is_required' => (int) $row['is_required'],
                    'is_active' => (int) $row['is_active'],
                    'updated_at' => $now,
                    'created_at' => $now,
                ]
            );
        }
    }
}
