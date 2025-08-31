<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    public function run(): void
    {
        $now = now();

        // ambil id unit & profesi agar relasi valid
        $unit = fn(string $kode) => DB::table('unit_kerjas')->where('kode', $kode)->value('id');
        $prof = fn(string $kode) => DB::table('profesis')->where('kode', $kode)->value('id');

        $users = [
            // SUPER ADMIN (admin aplikasi)
            [
                'nip' => '000000000000000001',
                'nama' => 'Super Admin',
                'tanggal_mulai_kerja' => '2020-01-01',
                'jenis_kelamin' => 'Laki-laki',
                'kewarganegaraan' => 'Indonesia',
                'nomor_identitas' => 'ID-0001',
                'alamat' => 'Jl. Admin No.1',
                'nomor_telepon' => '081111111111',
                'email' => 'superadmin@rsud.local',
                'pendidikan_terakhir' => 'S1',
                'jabatan' => 'Administrator',
                'unit_kerja_id' => $unit('MNG'),
                'profesi_id' => null,
                'password' => Hash::make('password'),
                'role' => 'super_admin',
                'email_verified_at' => $now,
            ],

            // KEPALA UNIT IGD
            [
                'nip' => '000000000000000002',
                'nama' => 'dr. Kepala IGD',
                'tanggal_mulai_kerja' => '2017-03-12',
                'jenis_kelamin' => 'Perempuan',
                'kewarganegaraan' => 'Indonesia',
                'nomor_identitas' => 'ID-0002',
                'alamat' => 'Jl. RS No.2',
                'nomor_telepon' => '081222222222',
                'email' => 'kepala.igd@rsud.local',
                'pendidikan_terakhir' => 'Sp.',
                'jabatan' => 'Kepala Unit IGD',
                'unit_kerja_id' => $unit('IGD'),
                'profesi_id' => $prof('DOK-SP'),
                'password' => Hash::make('password'),
                'role' => 'kepala_unit',
                'email_verified_at' => $now,
            ],

            // STAF ADMINISTRASI POLIKLINIK
            [
                'nip' => '000000000000000003',
                'nama' => 'Staf Administrasi Poli',
                'tanggal_mulai_kerja' => '2019-07-01',
                'jenis_kelamin' => 'Laki-laki',
                'kewarganegaraan' => 'Indonesia',
                'nomor_identitas' => 'ID-0003',
                'alamat' => 'Jl. RS No.3',
                'nomor_telepon' => '081333333333',
                'email' => 'admin.poli@rsud.local',
                'pendidikan_terakhir' => 'D3',
                'jabatan' => 'Staf Administrasi',
                'unit_kerja_id' => $unit('POL-UM'),
                'profesi_id' => $prof('ADM'),
                'password' => Hash::make('password'),
                'role' => 'administrasi',
                'email_verified_at' => $now,
            ],

            // PEGAWAI MEDIS: DOKTER UMUM
            [
                'nip' => '000000000000000004',
                'nama' => 'dr. Umum Poliklinik',
                'tanggal_mulai_kerja' => '2021-02-10',
                'jenis_kelamin' => 'Laki-laki',
                'kewarganegaraan' => 'Indonesia',
                'nomor_identitas' => 'ID-0004',
                'alamat' => 'Jl. RS No.4',
                'nomor_telepon' => '081444444444',
                'email' => 'dokter.umum@rsud.local',
                'pendidikan_terakhir' => 'S.Ked',
                'jabatan' => 'Dokter Umum',
                'unit_kerja_id' => $unit('POL-UM'),
                'profesi_id' => $prof('DOK-UM'),
                'password' => Hash::make('password'),
                'role' => 'pegawai_medis',
                'email_verified_at' => $now,
            ],

            // PEGAWAI MEDIS: PERAWAT
            [
                'nip' => '000000000000000005',
                'nama' => 'Perawat Poliklinik',
                'tanggal_mulai_kerja' => '2018-11-05',
                'jenis_kelamin' => 'Perempuan',
                'kewarganegaraan' => 'Indonesia',
                'nomor_identitas' => 'ID-0005',
                'alamat' => 'Jl. RS No.5',
                'nomor_telepon' => '081555555555',
                'email' => 'perawat@rsud.local',
                'pendidikan_terakhir' => 'D3 Keperawatan',
                'jabatan' => 'Perawat',
                'unit_kerja_id' => $unit('POL-UM'),
                'profesi_id' => $prof('PRW'),
                'password' => Hash::make('password'),
                'role' => 'pegawai_medis',
                'email_verified_at' => $now,
            ],
        ];

        foreach ($users as $u) {
            DB::table('users')->updateOrInsert(
                ['email' => $u['email']],
                $u + ['created_at' => $now, 'updated_at' => $now]
            );
        }
    }
}
