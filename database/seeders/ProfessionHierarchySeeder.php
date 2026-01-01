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

        // Base professions (idempotent)
        $dokterUmumId = $ensureProfession('DOK-UM', 'Dokter Umum', 'Dokter layanan primer');
        $dokterSpesialisId = $ensureProfession('DOK-SP', 'Dokter Spesialis', 'Dokter spesialis');
        // Needed for user import (profession_slug: dokter-spesialis-anak)
        $dokterSpesialisAnakId = $ensureProfession('DOK-SPA', 'Dokter Spesialis Anak', 'Dokter spesialis anak');
        $perawatId = $ensureProfession('PRW', 'Perawat', 'Perawat');

        $kepalaUnitDokId = $ensureProfession(
            code: 'KPL-UNIT',
            name: 'Kepala Unit',
            description: 'Profesi struktural: kepala unit '
        );

        $kepalaPoliDokId = $ensureProfession(
            code: 'KPL-POLI',
            name: 'Kepala Poliklinik',
            description: 'Profesi struktural: kepala poliklinik'
        );

        // Attach professions to seeded users (best-effort, idempotent)
        if (Schema::hasTable('users')) {
            // Only fill if empty; do not override explicit demo professions from DatabaseSeeder.
            DB::table('users')->where('email', 'kepala.umum@rsud.local')->whereNull('profession_id')->update(['profession_id' => $kepalaUnitDokId ?: null]);
            DB::table('users')->where('email', 'kepala.gigi@rsud.local')->whereNull('profession_id')->update(['profession_id' => $kepalaUnitDokId ?: null]);
            DB::table('users')->where('email', 'kepala.poliklinik@rsud.local')->whereNull('profession_id')->update(['profession_id' => $kepalaPoliDokId ?: null]);
        }

        if (!Schema::hasTable('profession_reporting_lines')) {
            return;
        }

        if ($perawatId <= 0) {
            return;
        }

        $lines = [];

        // ============================
        // PERAWAT (assessee)
        // - Supervisors: level 1 = dokter (umum/spesialis/spa), level 2 = kepala unit, level 3 = kepala poli
        // - Peers: perawat
        // ============================
        foreach ([$dokterUmumId, $dokterSpesialisId, $dokterSpesialisAnakId] as $dokterId) {
            if ((int) $dokterId > 0) {
                $lines[] = ['assessee_profession_id' => $perawatId, 'assessor_profession_id' => $dokterId, 'relation_type' => 'supervisor', 'level' => 1, 'is_required' => 1, 'is_active' => 1];
            }
        }
        $lines[] = ['assessee_profession_id' => $perawatId, 'assessor_profession_id' => $kepalaUnitDokId, 'relation_type' => 'supervisor', 'level' => 2, 'is_required' => 1, 'is_active' => 1];
        $lines[] = ['assessee_profession_id' => $perawatId, 'assessor_profession_id' => $kepalaPoliDokId, 'relation_type' => 'supervisor', 'level' => 3, 'is_required' => 1, 'is_active' => 1];
        $lines[] = ['assessee_profession_id' => $perawatId, 'assessor_profession_id' => $perawatId, 'relation_type' => 'peer', 'level' => null, 'is_required' => 1, 'is_active' => 1];

        // ============================
        // DOKTER (assessee): umum, spesialis, spesialis anak
        // - Peers: dokter umum/spesialis/spa
        // - Subordinates: perawat
        // - Supervisors: kepala unit (L1), kepala poli (L2)
        // ============================
        $dokterAssesseeIds = array_values(array_filter([
            $dokterUmumId,
            $dokterSpesialisId,
            $dokterSpesialisAnakId,
        ], fn($id) => (int) $id > 0));

        foreach ($dokterAssesseeIds as $assesseeDokterId) {
            // Supervisors
            if ((int) $kepalaUnitDokId > 0) {
                $lines[] = ['assessee_profession_id' => $assesseeDokterId, 'assessor_profession_id' => $kepalaUnitDokId, 'relation_type' => 'supervisor', 'level' => 1, 'is_required' => 1, 'is_active' => 1];
            }
            if ((int) $kepalaPoliDokId > 0) {
                $lines[] = ['assessee_profession_id' => $assesseeDokterId, 'assessor_profession_id' => $kepalaPoliDokId, 'relation_type' => 'supervisor', 'level' => 2, 'is_required' => 1, 'is_active' => 1];
            }

            // Peers (antar dokter)
            foreach ($dokterAssesseeIds as $assessorDokterId) {
                $lines[] = ['assessee_profession_id' => $assesseeDokterId, 'assessor_profession_id' => $assessorDokterId, 'relation_type' => 'peer', 'level' => null, 'is_required' => 1, 'is_active' => 1];
            }

            // Subordinates (perawat menilai dokter)
            $lines[] = ['assessee_profession_id' => $assesseeDokterId, 'assessor_profession_id' => $perawatId, 'relation_type' => 'subordinate', 'level' => null, 'is_required' => 1, 'is_active' => 1];
        }

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
