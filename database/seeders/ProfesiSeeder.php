<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class ProfesiSeeder extends Seeder
{
    public function run(): void
    {
        $now = now();

        $rows = [
            ['nama' => 'Dokter Spesialis', 'kode' => 'DOK-SP', 'deskripsi' => 'Dokter spesialis klinis di poliklinik'],
            ['nama' => 'Dokter Umum',      'kode' => 'DOK-UM', 'deskripsi' => 'Dokter layanan primer'],
            ['nama' => 'Perawat',          'kode' => 'PRW',    'deskripsi' => 'Tenaga keperawatan'],
            ['nama' => 'Bidan',            'kode' => 'BDN',    'deskripsi' => 'Tenaga kebidanan'],
            ['nama' => 'Analis Lab',       'kode' => 'LAB-AN', 'deskripsi' => 'Tenaga laboratorium'],
            ['nama' => 'Apoteker',         'kode' => 'APT',    'deskripsi' => 'Tenaga farmasi'],
            ['nama' => 'Tenaga Administrasi', 'kode' => 'ADM', 'deskripsi' => 'Staf administrasi poliklinik'],
        ];

        foreach ($rows as $r) {
            DB::table('profesis')->updateOrInsert(
                ['kode' => $r['kode']],
                $r + ['created_at' => $now, 'updated_at' => $now]
            );
        }
    }
}
