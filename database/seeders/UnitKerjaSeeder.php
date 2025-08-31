<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\DB;

class UnitKerjaSeeder extends Seeder
{
    public function run(): void
    {
        $now = now();

        $units = [
            ['nama_unit' => 'Manajemen Rumah Sakit', 'kode' => 'MNG', 'type' => 'manajemen',   'lokasi' => 'Gedung A Lt.2', 'telepon' => '0389-0001', 'email' => 'manajemen@rsud.local'],
            ['nama_unit' => 'Keuangan',               'kode' => 'KEU', 'type' => 'administrasi','lokasi' => 'Gedung A Lt.1', 'telepon' => '0389-0002', 'email' => 'keu@rsud.local', 'parent' => 'Manajemen Rumah Sakit'],
            ['nama_unit' => 'SDM',                    'kode' => 'SDM', 'type' => 'administrasi','lokasi' => 'Gedung A Lt.1', 'telepon' => '0389-0003', 'email' => 'sdm@rsud.local', 'parent' => 'Manajemen Rumah Sakit'],
            ['nama_unit' => 'IGD',                    'kode' => 'IGD', 'type' => 'igd',         'lokasi' => 'Gedung C Lt.1', 'telepon' => '0389-0100', 'email' => 'igd@rsud.local'],
            ['nama_unit' => 'Rawat Inap',             'kode' => 'RWI', 'type' => 'rawat_inap',  'lokasi' => 'Gedung D',      'telepon' => '0389-0200', 'email' => 'rwinap@rsud.local'],
            ['nama_unit' => 'Poliklinik Umum',        'kode' => 'POL-UM', 'type' => 'poliklinik','lokasi' => 'Gedung B Lt.1','telepon' => '0389-0301', 'email' => 'poli.umum@rsud.local'],
            ['nama_unit' => 'Poliklinik Bedah',       'kode' => 'POL-BD', 'type' => 'poliklinik','lokasi' => 'Gedung B Lt.1','telepon' => '0389-0302', 'email' => 'poli.bedah@rsud.local'],
            ['nama_unit' => 'Laboratorium',           'kode' => 'LAB',    'type' => 'penunjang', 'lokasi' => 'Gedung B Lt.2','telepon' => '0389-0400', 'email' => 'lab@rsud.local'],
            ['nama_unit' => 'Farmasi',                'kode' => 'FAR',    'type' => 'penunjang', 'lokasi' => 'Gedung B Lt.2','telepon' => '0389-0500', 'email' => 'farmasi@rsud.local'],
        ];

        // insert parents first
        $idByName = [];
        foreach ($units as $u) {
            if (!isset($u['parent'])) {
                $id = DB::table('unit_kerjas')->insertGetId([
                    'nama_unit' => $u['nama_unit'],
                    'slug' => Str::slug($u['nama_unit']),
                    'kode' => $u['kode'] ?? null,
                    'type' => $u['type'] ?? 'poliklinik',
                    'parent_id' => null,
                    'lokasi' => $u['lokasi'] ?? null,
                    'telepon' => $u['telepon'] ?? null,
                    'email' => $u['email'] ?? null,
                    'proporsi_remunerasi_unit' => 0.00,
                    'is_active' => true,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
                $idByName[$u['nama_unit']] = $id;
            }
        }

        // insert children with parent_id
        foreach ($units as $u) {
            if (isset($u['parent'])) {
                DB::table('unit_kerjas')->insert([
                    'nama_unit' => $u['nama_unit'],
                    'slug' => Str::slug($u['nama_unit']),
                    'kode' => $u['kode'] ?? null,
                    'type' => $u['type'] ?? 'poliklinik',
                    'parent_id' => $idByName[$u['parent']] ?? null,
                    'lokasi' => $u['lokasi'] ?? null,
                    'telepon' => $u['telepon'] ?? null,
                    'email' => $u['email'] ?? null,
                    'proporsi_remunerasi_unit' => 0.00,
                    'is_active' => true,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }
        }
    }
}
