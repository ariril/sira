<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Carbon\Carbon;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        DB::transaction(function () {
            $now = Carbon::now();

            // =========================================================
            // 1) UNIT KERJA (lengkap: manajemen, admin, poli, penunjang)
            // =========================================================

            // Insert Manajemen dulu agar parent_id bisa direferensikan
            $manajemenId = DB::table('unit_kerja')->insertGetId([
                'nama_unit' => 'Manajemen Rumah Sakit',
                'slug' => 'manajemen-rumah-sakit',
                'kode' => 'MNG',
                'type' => 'manajemen',
                'parent_id' => null,
                'lokasi' => 'Kantor Direksi',
                'telepon' => null,
                'email' => null,
                'proporsi_remunerasi_unit' => 0.00,
                'is_active' => 1,
                'created_at' => $now, 'updated_at' => $now,
            ]);

            $units = [
                // Administrasi
                ['nama_unit' => 'Sumber Daya Manusia', 'slug' => 'sdm', 'kode' => 'SDM', 'type' => 'administrasi', 'parent_id' => $manajemenId, 'lokasi' => 'Gedung Administrasi', 'telepon' => null, 'email' => null],
                ['nama_unit' => 'Keuangan', 'slug' => 'keuangan', 'kode' => 'KEU', 'type' => 'administrasi', 'parent_id' => $manajemenId, 'lokasi' => 'Gedung Administrasi', 'telepon' => null, 'email' => null],
                ['nama_unit' => 'Rekam Medis & Informasi', 'slug' => 'rekam-medis', 'kode' => 'RM', 'type' => 'administrasi', 'parent_id' => $manajemenId, 'lokasi' => 'Lantai Dasar', 'telepon' => null, 'email' => null],

                // Gawat darurat & rawat inap
                ['nama_unit' => 'Instalasi Gawat Darurat (IGD)', 'slug' => 'igd', 'kode' => 'IGD', 'type' => 'igd', 'parent_id' => null, 'lokasi' => 'Gedung IGD 24 Jam', 'telepon' => null, 'email' => null],
                ['nama_unit' => 'Rawat Inap', 'slug' => 'rawat-inap', 'kode' => 'RWI', 'type' => 'rawat_inap', 'parent_id' => null, 'lokasi' => 'Gedung Rawat Inap', 'telepon' => null, 'email' => null],

                // Poliklinik (contoh medik dasar & spesialis)
                ['nama_unit' => 'Poliklinik Umum', 'slug' => 'poliklinik-umum', 'kode' => 'POL-UM', 'type' => 'poliklinik', 'parent_id' => null, 'lokasi' => 'Gedung Poli', 'telepon' => null, 'email' => null],
                ['nama_unit' => 'Poliklinik Gigi & Mulut', 'slug' => 'poliklinik-gigi', 'kode' => 'POL-GG', 'type' => 'poliklinik', 'parent_id' => null, 'lokasi' => 'Gedung Poli', 'telepon' => null, 'email' => null],
                ['nama_unit' => 'Poliklinik KIA/KB', 'slug' => 'poliklinik-kia-kb', 'kode' => 'POL-KIA', 'type' => 'poliklinik', 'parent_id' => null, 'lokasi' => 'Gedung Poli', 'telepon' => null, 'email' => null],
                ['nama_unit' => 'Penyakit Dalam', 'slug' => 'penyakit-dalam', 'kode' => 'POL-PD', 'type' => 'poliklinik', 'parent_id' => null, 'lokasi' => 'Gedung Poli', 'telepon' => null, 'email' => null],
                ['nama_unit' => 'Kesehatan Anak', 'slug' => 'kesehatan-anak', 'kode' => 'POL-AN', 'type' => 'poliklinik', 'parent_id' => null, 'lokasi' => 'Gedung Poli', 'telepon' => null, 'email' => null],
                ['nama_unit' => 'Bedah', 'slug' => 'bedah', 'kode' => 'POL-BD', 'type' => 'poliklinik', 'parent_id' => null, 'lokasi' => 'Gedung Poli', 'telepon' => null, 'email' => null],
                ['nama_unit' => 'Obstetri & Ginekologi', 'slug' => 'obgyn', 'kode' => 'POL-OBG', 'type' => 'poliklinik', 'parent_id' => null, 'lokasi' => 'Gedung Poli', 'telepon' => null, 'email' => null],
                ['nama_unit' => 'Mata', 'slug' => 'mata', 'kode' => 'POL-MT', 'type' => 'poliklinik', 'parent_id' => null, 'lokasi' => 'Gedung Poli', 'telepon' => null, 'email' => null],
                ['nama_unit' => 'Paru', 'slug' => 'paru', 'kode' => 'POL-PR', 'type' => 'poliklinik', 'parent_id' => null, 'lokasi' => 'Gedung Poli', 'telepon' => null, 'email' => null],
                ['nama_unit' => 'Saraf', 'slug' => 'saraf', 'kode' => 'POL-SR', 'type' => 'poliklinik', 'parent_id' => null, 'lokasi' => 'Gedung Poli', 'telepon' => null, 'email' => null],
                ['nama_unit' => 'Jantung & Pembuluh Darah', 'slug' => 'jantung', 'kode' => 'POL-JTG', 'type' => 'poliklinik', 'parent_id' => null, 'lokasi' => 'Gedung Poli', 'telepon' => null, 'email' => null],

                // Penunjang
                ['nama_unit' => 'Laboratorium', 'slug' => 'laboratorium', 'kode' => 'LAB', 'type' => 'penunjang', 'parent_id' => null, 'lokasi' => 'Gedung Penunjang', 'telepon' => null, 'email' => null],
                ['nama_unit' => 'Radiologi', 'slug' => 'radiologi', 'kode' => 'RAD', 'type' => 'penunjang', 'parent_id' => null, 'lokasi' => 'Gedung Penunjang', 'telepon' => null, 'email' => null],
                ['nama_unit' => 'Farmasi', 'slug' => 'farmasi', 'kode' => 'FAR', 'type' => 'penunjang', 'parent_id' => null, 'lokasi' => 'Gedung Penunjang', 'telepon' => null, 'email' => null],
                ['nama_unit' => 'Rehabilitasi Medik', 'slug' => 'rehabilitasi-medik', 'kode' => 'RHM', 'type' => 'penunjang', 'parent_id' => null, 'lokasi' => 'Gedung Penunjang', 'telepon' => null, 'email' => null],
                ['nama_unit' => 'CSSD (Sterilisasi)', 'slug' => 'cssd', 'kode' => 'CSSD', 'type' => 'penunjang', 'parent_id' => null, 'lokasi' => 'Penunjang', 'telepon' => null, 'email' => null],
                ['nama_unit' => 'Bank Darah', 'slug' => 'bank-darah', 'kode' => 'BD', 'type' => 'penunjang', 'parent_id' => null, 'lokasi' => 'Penunjang', 'telepon' => null, 'email' => null],
                ['nama_unit' => 'Laundry', 'slug' => 'laundry', 'kode' => 'LDY', 'type' => 'penunjang', 'parent_id' => null, 'lokasi' => 'Penunjang', 'telepon' => null, 'email' => null],
            ];
            foreach ($units as &$u) {
                $u['proporsi_remunerasi_unit'] = 0.00;
                $u['is_active'] = 1;
                $u['created_at'] = $now;
                $u['updated_at'] = $now;
            }
            DB::table('unit_kerja')->insert($units);

            // Helper ambil id unit cepat
            $unitId = fn(string $slug) => DB::table('unit_kerja')->where('slug', $slug)->value('id');

            // =========================================================
            // 2) PROFESI
            // =========================================================
            $profesis = [
                ['nama' => 'Dokter Umum', 'kode' => 'DOK-UM', 'deskripsi' => 'Dokter layanan primer'],
                ['nama' => 'Dokter Spesialis', 'kode' => 'DOK-SP', 'deskripsi' => 'Dokter spesialis klinis'],
                ['nama' => 'Perawat', 'kode' => 'PRW', 'deskripsi' => 'Tenaga keperawatan'],
                ['nama' => 'Bidan', 'kode' => 'BDN', 'deskripsi' => 'Tenaga kebidanan'],
                ['nama' => 'Apoteker', 'kode' => 'APT', 'deskripsi' => 'Tenaga farmasi'],
                ['nama' => 'Analis Lab', 'kode' => 'LAB-AN', 'deskripsi' => 'Tenaga laboratorium'],
                ['nama' => 'Radiografer', 'kode' => 'RAD', 'deskripsi' => 'Tenaga radiologi'],
                ['nama' => 'Administrasi', 'kode' => 'ADM', 'deskripsi' => 'Staf administrasi'],
            ];
            foreach ($profesis as &$p) { $p['created_at']=$now; $p['updated_at']=$now; }
            DB::table('profesi')->insert($profesis);

            $profesiId = fn(string $kode) => DB::table('profesi')->where('kode', $kode)->value('id');

            // =========================================================
            // 3) USERS (akun awal agar modul bisa langsung dipakai)
            // =========================================================
            $users = [
                [
                    'nip' => '000000000000000001',
                    'nama' => 'Super Admin',
                    'tanggal_mulai_kerja' => '2020-01-01',
                    'jenis_kelamin' => 'Laki-laki',
                    'kewarganegaraan' => 'Indonesia',
                    'nomor_identitas' => 'ID-0001',
                    'alamat' => 'Jl. Dr. Sutomo No. 2, Atambua',
                    'nomor_telepon' => '0389-2513137',
                    'email' => 'superadmin@rsud.local',
                    'pendidikan_terakhir' => 'S1',
                    'jabatan' => 'Administrator',
                    'unit_kerja_id' => $unitId('manajemen-rumah-sakit'),
                    'profesi_id' => null,
                    'password' => Hash::make('password'),
                    'role' => 'super_admin',
                    'email_verified_at' => $now,
                    'remember_token' => Str::random(10),
                    'created_at' => $now, 'updated_at' => $now,
                ],
                [
                    'nip' => '000000000000000002',
                    'nama' => 'dr. Kepala IGD',
                    'tanggal_mulai_kerja' => '2017-03-12',
                    'jenis_kelamin' => 'Perempuan',
                    'kewarganegaraan' => 'Indonesia',
                    'nomor_identitas' => 'ID-0002',
                    'alamat' => 'Atambua',
                    'nomor_telepon' => '0812-2467-11027',
                    'email' => 'kepala.igd@rsud.local',
                    'pendidikan_terakhir' => 'Sp.',
                    'jabatan' => 'Kepala Unit IGD',
                    'unit_kerja_id' => $unitId('igd'),
                    'profesi_id' => $profesiId('DOK-UM'),
                    'password' => Hash::make('password'),
                    'role' => 'kepala_unit',
                    'email_verified_at' => $now,
                    'remember_token' => Str::random(10),
                    'created_at' => $now, 'updated_at' => $now,
                ],
                [
                    'nip' => '000000000000000003',
                    'nama' => 'Staf Rekam Medis',
                    'tanggal_mulai_kerja' => '2019-07-01',
                    'jenis_kelamin' => 'Laki-laki',
                    'kewarganegaraan' => 'Indonesia',
                    'nomor_identitas' => 'ID-0003',
                    'alamat' => 'Atambua',
                    'nomor_telepon' => '0813-3333-3333',
                    'email' => 'rekam.medis@rsud.local',
                    'pendidikan_terakhir' => 'D3',
                    'jabatan' => 'Staf Administrasi',
                    'unit_kerja_id' => $unitId('rekam-medis'),
                    'profesi_id' => $profesiId('ADM'),
                    'password' => Hash::make('password'),
                    'role' => 'administrasi',
                    'email_verified_at' => $now,
                    'remember_token' => Str::random(10),
                    'created_at' => $now, 'updated_at' => $now,
                ],
                [
                    'nip' => '000000000000000004',
                    'nama' => 'dr. Umum Poliklinik',
                    'tanggal_mulai_kerja' => '2021-02-10',
                    'jenis_kelamin' => 'Laki-laki',
                    'kewarganegaraan' => 'Indonesia',
                    'nomor_identitas' => 'ID-0004',
                    'alamat' => 'Atambua',
                    'nomor_telepon' => '0814-4444-4444',
                    'email' => 'dokter.umum@rsud.local',
                    'pendidikan_terakhir' => 'S.Ked',
                    'jabatan' => 'Dokter Umum',
                    'unit_kerja_id' => $unitId('poliklinik-umum'),
                    'profesi_id' => $profesiId('DOK-UM'),
                    'password' => Hash::make('password'),
                    'role' => 'pegawai_medis',
                    'email_verified_at' => $now,
                    'remember_token' => Str::random(10),
                    'created_at' => $now, 'updated_at' => $now,
                ],
                [
                    'nip' => '000000000000000005',
                    'nama' => 'Perawat Poli Umum',
                    'tanggal_mulai_kerja' => '2018-11-05',
                    'jenis_kelamin' => 'Perempuan',
                    'kewarganegaraan' => 'Indonesia',
                    'nomor_identitas' => 'ID-0005',
                    'alamat' => 'Atambua',
                    'nomor_telepon' => '0815-5555-5555',
                    'email' => 'perawat@rsud.local',
                    'pendidikan_terakhir' => 'D3 Keperawatan',
                    'jabatan' => 'Perawat',
                    'unit_kerja_id' => $unitId('poliklinik-umum'),
                    'profesi_id' => $profesiId('PRW'),
                    'password' => Hash::make('password'),
                    'role' => 'pegawai_medis',
                    'email_verified_at' => $now,
                    'remember_token' => Str::random(10),
                    'created_at' => $now, 'updated_at' => $now,
                ],
            ];
            DB::table('users')->insert($users);

            $userId = fn(string $email) => DB::table('users')->where('email', $email)->value('id');

            // =========================================================
            // 4) PENGATURAN SITUS
            // =========================================================
            DB::table('pengaturan_situs')->insert([
                'nama'          => 'RSUD Mgr. Gabriel Manek, SVD Atambua',
                'nama_singkat'  => 'RSUD GM Atambua',
                'alamat'        => "Jl. Dr Sutomo No. 2, Atambua, Belu, NTT",
                'telepon'       => '(0389)2513137',
                'email'         => 'rsudatambua66@gmail.com',
                'path_logo'     => null,
                'path_favicon'  => null,
                'url_facebook'  => 'https://www.facebook.com/people/RSUD-MGR-GABRIEL-MANEK-SVD/100054300359896/',
                'url_instagram' => null,
                'url_twitter'   => null,
                'url_youtube'   => null,
                'teks_footer'   => '© ' . date('Y') . ' RSUD Mgr. Gabriel Manek, SVD Atambua. Semua hak cipta.',
                'diperbarui_oleh'=> $userId('superadmin@rsud.local'),
                'created_at'    => $now,
                'updated_at'    => $now,
            ]);

            // =========================================================
            // 5) HALAMAN TENTANG
            // =========================================================
            $about = [
                [
                    'tipe' => 'profil_rs',
                    'judul' => 'Profil RSUD',
                    'konten' => "RSUD Mgr. Gabriel Manek, SVD Atambua berlokasi di Jl. Dr Sutomo No. 2, Atambua. Telepon (0389)2513137. Melayani IGD 24 jam, poliklinik, rawat inap, dan penunjang medis.",
                    'path_gambar' => null,
                    'lampiran_json' => json_encode([]),
                    'diterbitkan_pada' => $now,
                    'aktif' => 1,
                ],
                [
                    'tipe' => 'visi',
                    'judul' => 'Visi',
                    'konten' => "Mewujudkan pelayanan kesehatan yang prima, terjangkau, dan berpihak pada masyarakat—bersama yang tak mampu, kita berupaya maju.",
                    'path_gambar' => null,
                    'lampiran_json' => json_encode([]),
                    'diterbitkan_pada' => $now,
                    'aktif' => 1,
                ],
                [
                    'tipe' => 'misi',
                    'judul' => 'Misi',
                    'konten' => "Memberikan pelayanan kesehatan yang berkualitas tanpa membebani biaya pasien; mendukung akses layanan berdasarkan kebutuhan, bukan kemampuan ekonomi; dan terus meningkatkan kapasitas RSUD melalui pengembangan SDM dan fasilitas.",
                    'path_gambar' => null,
                    'lampiran_json' => json_encode([]),
                    'diterbitkan_pada' => $now,
                    'aktif' => 1,
                ],
                [
                    'tipe' => 'struktur',
                    'judul' => 'Struktur Organisasi',
                    'konten' => null,
                    'path_gambar' => null,
                    'lampiran_json' => json_encode([]),
                    'diterbitkan_pada' => null,
                    'aktif' => 1,
                ],
                [
                    'tipe' => 'tugas_fungsi',
                    'judul' => 'Tugas & Fungsi',
                    'konten' => null,
                    'path_gambar' => null,
                    'lampiran_json' => json_encode([]),
                    'diterbitkan_pada' => null,
                    'aktif' => 1,
                ],
            ];
            foreach ($about as &$a) { $a['created_at']=$now; $a['updated_at']=$now; }
            DB::table('halaman_tentang')->insert($about);

            // =========================================================
            // 6) PERIODE PENILAIAN
            // =========================================================
            DB::table('periode_penilaian')->insert([
                'nama_periode' => 'Triwulan III 2025',
                'tanggal_mulai' => '2025-07-01',
                'tanggal_akhir' => '2025-09-30',
                'siklus_penilaian' => 'triwulan',
                'status_periode' => 'berjalan',
                'is_active' => 1,
                'locked_at' => null,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
            $periodeId = DB::table('periode_penilaian')->where('nama_periode','Triwulan III 2025')->value('id');

            // =========================================================
            // 7) KRITERIA KINERJA
            // =========================================================
            $kriteria = [
                ['nama_kriteria'=>'Kedisiplinan','tipe_kriteria'=>'angka','deskripsi_kriteria'=>null,'aktif'=>1],
                ['nama_kriteria'=>'Pelayanan Pasien','tipe_kriteria'=>'angka','deskripsi_kriteria'=>null,'aktif'=>1],
                ['nama_kriteria'=>'Kepatuhan Prosedur','tipe_kriteria'=>'angka','deskripsi_kriteria'=>null,'aktif'=>1],
            ];
            foreach ($kriteria as &$k) { $k['created_at']=$now; $k['updated_at']=$now; }
            DB::table('kriteria_kinerja')->insert($kriteria);

            $krId = fn(string $nama) => DB::table('kriteria_kinerja')->where('nama_kriteria',$nama)->value('id');

            // =========================================================
            // 8) BOBOT KRITERIA PER UNIT (contoh untuk Poli Umum)
            // =========================================================
            DB::table('bobot_kriteria_unit')->insert([
                ['id_unit'=>$unitId('poliklinik-umum'),'id_kriteria'=>$krId('Kedisiplinan'),'bobot'=>40.00,'created_at'=>$now,'updated_at'=>$now],
                ['id_unit'=>$unitId('poliklinik-umum'),'id_kriteria'=>$krId('Pelayanan Pasien'),'bobot'=>40.00,'created_at'=>$now,'updated_at'=>$now],
                ['id_unit'=>$unitId('poliklinik-umum'),'id_kriteria'=>$krId('Kepatuhan Prosedur'),'bobot'=>20.00,'created_at'=>$now,'updated_at'=>$now],
            ]);

            // =========================================================
            // 9) PENGUMUMAN
            // =========================================================
            DB::table('pengumuman')->insert([
                'judul' => 'Selamat datang di Sistem Informasi RSUD GM Atambua',
                'slug' => 'selamat-datang',
                'ringkasan' => 'Portal internal untuk kinerja, remunerasi, dan layanan klinik.',
                'konten' => '<p>Silakan gunakan menu di atas untuk mengakses modul-modul yang tersedia.</p>',
                'kategori' => 'lainnya',
                'label' => 'info',
                'disorot' => 1,
                'dipublikasikan_pada' => $now,
                'kedaluwarsa_pada' => null,
                'lampiran_json' => json_encode([]),
                'penulis_id' => $userId('superadmin@rsud.local'),
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            // =========================================================
            // 10) FAQ (Pertanyaan Umum)
            // =========================================================
            DB::table('pertanyaan_umum')->insert([
                ['pertanyaan'=>'Jam operasional IGD?','jawaban'=>'IGD melayani 24 jam, 7 hari seminggu.','urutan'=>1,'aktif'=>1,'kategori'=>'layanan','created_at'=>$now,'updated_at'=>$now],
                ['pertanyaan'=>'Cara ambil nomor antrian poli?','jawaban'=>'Datang ke loket pendaftaran atau gunakan aplikasi internal bila tersedia.','urutan'=>2,'aktif'=>1,'kategori'=>'antrian','created_at'=>$now,'updated_at'=>$now],
                ['pertanyaan'=>'Kontak RS?','jawaban'=>'Telepon (0389)2513137, Email rsudatambua66@gmail.com.','urutan'=>3,'aktif'=>1,'kategori'=>'kontak','created_at'=>$now,'updated_at'=>$now],
            ]);

            // =========================================================
            // (Bonus untuk kebutuhan UI) KUNJUNGAN & ANTRIAN HARI INI
            // =========================================================
            $kunjunganId = DB::table('kunjungan')->insertGetId([
                'ticket_code' => 'KJ-'.date('Ymd').'-0001',
                'unit_kerja_id' => $unitId('poliklinik-umum'),
                'tanggal' => $now,
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            $antrianId = DB::table('antrian_pasien')->insertGetId([
                'tanggal_antri' => $now->toDateString(),
                'nomor_antrian' => 1,
                'status_antrian' => 'Menunggu',
                'waktu_masuk_antrian' => $now->format('H:i:s'),
                'waktu_mulai_dilayani' => null,
                'waktu_selesai_dilayani' => null,
                'dokter_bertugas_id' => $userId('dokter.umum@rsud.local'),
                'unit_kerja_id' => $unitId('poliklinik-umum'),
                'patient_ref' => 'RM-EX-0001',
                'kunjungan_id' => $kunjunganId,
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            DB::table('kunjungan_tenaga_medis')->insert([
                ['kunjungan_id'=>$kunjunganId,'tenaga_medis_id'=>$userId('dokter.umum@rsud.local'),'peran'=>'dokter','durasi_menit'=>15,'created_at'=>$now,'updated_at'=>$now],
                ['kunjungan_id'=>$kunjunganId,'tenaga_medis_id'=>$userId('perawat@rsud.local'),'peran'=>'perawat','durasi_menit'=>10,'created_at'=>$now,'updated_at'=>$now],
            ]);

            // =========================================================
            // (Helper ID yang dipakai di bawah)
            // =========================================================
            $dokterId     = $userId('dokter.umum@rsud.local');
            $perawatId    = $userId('perawat@rsud.local');
            $superAdminId = $userId('superadmin@rsud.local');
            $kepalaIgdId  = $userId('kepala.igd@rsud.local');
            $poliUmumId   = $unitId('poliklinik-umum');
            $periodeId    = DB::table('periode_penilaian')->orderBy('id', 'desc')->value('id');

            // Pastikan ada $kunjunganId untuk ulasan/transaksi, kalau belum ada buat satu.
            $kunjunganId = $kunjunganId ?? DB::table('kunjungan')->insertGetId([
                'ticket_code'   => 'KJ-'.date('Ymd').'-X001',
                'unit_kerja_id' => $poliUmumId,
                'tanggal'       => $now,
                'created_at'    => $now, 'updated_at' => $now,
            ]);

            // =========================================================
            // 11) JADWAL DOKTER (contoh slot 3 hari ke depan)
            // =========================================================
            $jadwal = [];
            for ($i=0; $i<3; $i++) {
                $tgl = Carbon::now()->addDays($i)->toDateString();
                $jadwal[] = [
                    'dokter_id'     => $dokterId,
                    'tanggal'       => $tgl,
                    'jam_mulai'     => '08:00:00',
                    'jam_selesai'   => '12:00:00',
                    'unit_kerja_id' => $poliUmumId,
                    'created_at'    => $now, 'updated_at' => $now,
                ];
            }
            DB::table('jadwal_dokter')->insert($jadwal);

            // =========================================================
            // 12) BATCH IMPORT KEHADIRAN (dummy 1 file) -> relasi kehadiran
            // =========================================================
            $batchId = DB::table('batch_import_kehadiran')->insertGetId([
                'nama_file'      => 'simrs_khanza_'.date('Y-m-d').'.xlsx',
                'diimpor_oleh'   => $superAdminId,
                'diimpor_pada'   => $now,
                'total_baris'    => 2,
                'baris_berhasil' => 2,
                'baris_gagal'    => 0,
                'created_at'     => $now, 'updated_at' => $now,
            ]);

            // =========================================================
            // 13) KEHADIRAN (dokter & perawat hari ini, sumber import)
            // =========================================================
            DB::table('kehadiran')->insert([
                [
                    'user_id'           => $dokterId,
                    'tanggal_hadir'     => $now->toDateString(),
                    'jam_masuk'         => '07:45:00',
                    'jam_keluar'        => '14:30:00',
                    'status_kehadiran'  => 'Hadir',
                    'catatan_lembur'    => null,
                    'source'            => 'import',
                    'import_batch_id'   => $batchId,
                    'created_at'        => $now, 'updated_at' => $now,
                ],
                [
                    'user_id'           => $perawatId,
                    'tanggal_hadir'     => $now->toDateString(),
                    'jam_masuk'         => '07:30:00',
                    'jam_keluar'        => '15:00:00',
                    'status_kehadiran'  => 'Hadir',
                    'catatan_lembur'    => null,
                    'source'            => 'import',
                    'import_batch_id'   => $batchId,
                    'created_at'        => $now, 'updated_at' => $now,
                ],
            ]);

            // =========================================================
            // 14) KONTRIBUSI TAMBAHAN (contoh dua usulan pegawai)
            // =========================================================
            DB::table('kontribusi_tambahan')->insert([
                [
                    'user_id'               => $perawatId,
                    'judul_kontribusi'      => 'Penyusunan SOP Triase Poli Umum',
                    'deskripsi_kontribusi'  => 'Draft SOP triase pasien poli umum untuk percepatan alur.',
                    'tanggal_pengajuan'     => $now->toDateString(),
                    'file_bukti'            => null,
                    'status_validasi'       => 'menunggu',
                    'komentar_supervisor'   => null,
                    'periode_penilaian_id'  => $periodeId,
                    'created_at'            => $now, 'updated_at' => $now,
                ],
                [
                    'user_id'               => $dokterId,
                    'judul_kontribusi'      => 'Edukasi DM Hipertensi Komunitas',
                    'deskripsi_kontribusi'  => 'Materi penyuluhan singkat untuk pasien rawat jalan.',
                    'tanggal_pengajuan'     => $now->toDateString(),
                    'file_bukti'            => null,
                    'status_validasi'       => 'disetujui',
                    'komentar_supervisor'   => 'Bagus, lanjutkan implementasi.',
                    'periode_penilaian_id'  => $periodeId,
                    'created_at'            => $now, 'updated_at' => $now,
                ],
            ]);

            // =========================================================
            // 15) PENILAIAN KINERJA (dokter poli umum pada periode aktif)
            // =========================================================
            $pkDokterId = DB::table('penilaian_kinerja')->insertGetId([
                'user_id'              => $dokterId,
                'periode_penilaian_id' => $periodeId,
                'tanggal_penilaian'    => $now->toDateString(),
                'skor_total_wsm'       => 86.50,
                'status_validasi'      => 'menunggu',
                'komentar_atasan'      => null,
                'created_at'           => $now, 'updated_at' => $now,
            ]);

            // =========================================================
            // 16) DETAIL PENILAIAN KRITERIA (sinkron dgn kriteria yg sudah ada)
            // =========================================================
            $kedisiplinanId     = $krId('Kedisiplinan');
            $pelayananPasienId  = $krId('Pelayanan Pasien');
            $kepatuhanId        = $krId('Kepatuhan Prosedur');

            DB::table('detail_penilaian_kriteria')->insert([
                [
                    'penilaian_kinerja_id' => $pkDokterId,
                    'kriteria_kinerja_id'  => $kedisiplinanId,
                    'nilai'                => 90.00,
                    'created_at'           => $now, 'updated_at' => $now,
                ],
                [
                    'penilaian_kinerja_id' => $pkDokterId,
                    'kriteria_kinerja_id'  => $pelayananPasienId,
                    'nilai'                => 85.00,
                    'created_at'           => $now, 'updated_at' => $now,
                ],
                [
                    'penilaian_kinerja_id' => $pkDokterId,
                    'kriteria_kinerja_id'  => $kepatuhanId,
                    'nilai'                => 80.00,
                    'created_at'           => $now, 'updated_at' => $now,
                ],
            ]);

            // =========================================================
            // 17) APPROVAL PENILAIAN (2 level: Kepala Unit -> Manajemen)
            // =========================================================
            DB::table('penilaian_approval')->insert([
                [
                    'penilaian_kinerja_id' => $pkDokterId,
                    'approver_id'          => $kepalaIgdId,
                    'level'                => 1,
                    'status'               => 'approved',
                    'catatan'              => 'Disetujui, lanjut ke manajemen.',
                    'acted_at'             => $now,
                    'created_at'           => $now, 'updated_at' => $now,
                ],
                [
                    'penilaian_kinerja_id' => $pkDokterId,
                    'approver_id'          => $superAdminId,
                    'level'                => 2,
                    'status'               => 'pending',
                    'catatan'              => null,
                    'acted_at'             => null,
                    'created_at'           => $now, 'updated_at' => $now,
                ],
            ]);

            // =========================================================
            // 18) REMUNERASI (berdasarkan periode & user yang sama)
            // =========================================================
            DB::table('remunerasi')->insert([
                'user_id'               => $dokterId,
                'periode_penilaian_id'  => $periodeId,
                'nilai_remunerasi'      => 3500000.00,
                'tanggal_pembayaran'    => null,
                'status_pembayaran'     => 'Belum Dibayar',
                'rincian_perhitungan'   => json_encode([
                    'dasar' => 3000000,
                    'insentif_kinerja' => 500000,
                    'catatan' => 'Mengacu skor WSM & kontribusi tambahan',
                ]),
                'published_at'          => null,
                'calculated_at'         => $now,
                'revised_by'            => null,
                'created_at'            => $now, 'updated_at' => $now,
            ]);

            // =========================================================
            // 19) ULASAN KUNJUNGAN (feedback pasien sederhana)
            // =========================================================
            $ulasanId = DB::table('ulasan')->insertGetId([
                'kunjungan_id'  => $kunjunganId,
                'overall_rating'=> 5,
                'komentar'      => 'Pelayanan cepat dan ramah.',
                'nama_pasien'   => 'Bapak A',
                'kontak'        => '08xxxxxxxxxx',
                'client_ip'     => request()->ip() ?? '127.0.0.1',
                'user_agent'    => request()->userAgent() ?? 'Seeder',
                'created_at'    => $now, 'updated_at' => $now,
            ]);

// =========================================================
// 20) DETAIL ULASAN (per nakes di kunjungan tersebut)
// =========================================================
            DB::table('ulasan_detail')->insert([
                [
                    'ulasan_id'       => $ulasanId,
                    'tenaga_medis_id' => $dokterId,
                    'peran'           => 'dokter',
                    'rating'          => 5,
                    'komentar'        => 'Dokter komunikatif.',
                    'created_at'      => $now, 'updated_at' => $now,
                ],
                [
                    'ulasan_id'       => $ulasanId,
                    'tenaga_medis_id' => $perawatId,
                    'peran'           => 'perawat',
                    'rating'          => 5,
                    'komentar'        => 'Perawat membantu dengan sigap.',
                    'created_at'      => $now, 'updated_at' => $now,
                ],
            ]);
// =========================================================
// SISA TABEL YANG BELUM DI-SEED:
// - (21) entri_logbook
// - (17) transaksi_pembayarans
// =========================================================

// Pastikan helper siap
            $dokterId      = $dokterId      ?? $userId('dokter.umum@rsud.local');
            $perawatId     = $perawatId     ?? $userId('perawat@rsud.local');
            $superAdminId  = $superAdminId  ?? $userId('superadmin@rsud.local');
            $kepalaIgdId   = $kepalaIgdId   ?? $userId('kepala.igd@rsud.local');
            $rekamMedisId  = $userId('rekam.medis@rsud.local') ?? null;
            $poliUmumId    = $poliUmumId    ?? $unitId('poliklinik-umum');

// Pastikan ada kunjungan & antrian untuk relasi transaksi
            $kunjunganId = $kunjunganId ?? DB::table('kunjungan')->orderByDesc('id')->value('id');
            if (!$kunjunganId) {
                $kunjunganId = DB::table('kunjungan')->insertGetId([
                    'ticket_code'   => 'KJ-'.date('Ymd').'-B001',
                    'unit_kerja_id' => $poliUmumId,
                    'tanggal'       => $now,
                    'created_at'    => $now, 'updated_at' => $now,
                ]);
            }

            $antrianId = $antrianId ?? DB::table('antrian_pasien')->orderByDesc('id')->value('id');
            if (!$antrianId) {
                $antrianId = DB::table('antrian_pasien')->insertGetId([
                    'tanggal_antri'          => $now->toDateString(),
                    'nomor_antrian'          => 2,
                    'status_antrian'         => 'Menunggu',
                    'waktu_masuk_antrian'    => $now->format('H:i:s'),
                    'waktu_mulai_dilayani'   => null,
                    'waktu_selesai_dilayani' => null,
                    'dokter_bertugas_id'     => $dokterId,
                    'unit_kerja_id'          => $poliUmumId,
                    'patient_ref'            => 'RM-EX-0002',
                    'kunjungan_id'           => $kunjunganId,
                    'created_at'             => $now, 'updated_at' => $now,
                ]);
            }

            // =========================================================
            // (21) ENTRI LOGBOOK — contoh 3 entri (dokter, perawat, rekam medis)
            // =========================================================
            DB::table('entri_logbook')->insert([
                [
                    'user_id'        => 4,
                    'tanggal'        => '2025-09-09',
                    'jam_mulai'      => '08:00:00',
                    'jam_selesai'    => '11:00:00',
                    'durasi_menit'   => 180,
                    'aktivitas'      => 'Pelayanan pasien rawat jalan Poli Umum (5 pasien).',
                    'kategori'       => 'pelayanan',
                    'status'         => 'disetujui',
                    'penyetuju_id'   => 2,
                    'disetujui_pada' => '2025-09-09 13:01:11',
                    'lampiran_json'  => '[]',
                    'created_at'     => '2025-09-09 13:01:11',
                    'updated_at'     => '2025-09-09 13:01:11',
                ],
                [
                    'user_id'        => 5,
                    'tanggal'        => '2025-09-09',
                    'jam_mulai'      => '07:30:00',
                    'jam_selesai'    => '12:00:00',
                    'durasi_menit'   => 270,
                    'aktivitas'      => 'Triase, pengukuran TTV, dan edukasi pra-konsultasi.',
                    'kategori'       => 'keperawatan',
                    'status'         => 'diajukan',
                    'penyetuju_id'   => 5,
                    'disetujui_pada' => null,
                    'lampiran_json'  => '[]',
                    'created_at'     => '2025-09-09 13:01:11',
                    'updated_at'     => '2025-09-09 13:01:11',
                ],
                [
                    'user_id'        => 3,
                    'tanggal'        => '2025-09-09',
                    'jam_mulai'      => '09:00:00',
                    'jam_selesai'    => '10:30:00',
                    'durasi_menit'   => 90,
                    'aktivitas'      => 'Verifikasi dan penyusunan berkas rekam medis kunjungan hari ini.',
                    'kategori'       => 'administrasi',
                    'status'         => 'draf',
                    'penyetuju_id'   => 3,
                    'disetujui_pada' => null,
                    'lampiran_json'  => '[]',
                    'created_at'     => '2025-09-09 13:01:11',
                    'updated_at'     => '2025-09-09 13:01:11',
                ],
            ]);

            // =========================================================
            // (17) TRANSAKSI PEMBAYARAN — 2 contoh transaksi
            // =========================================================
            DB::table('transaksi_pembayaran')->insert([
                [
                    'kunjungan_id'               => $kunjunganId,
                    'antrian_id'                 => $antrianId,
                    'tanggal_transaksi'          => $now->toDateString(),
                    'jumlah_pembayaran'          => 75000.00,
                    'metode_pembayaran'          => 'QRIS',
                    'channel'                    => 'QRIS',
                    'status_pembayaran'          => 'Berhasil',
                    'paid_at'                    => $now,
                    'nomor_referensi_pembayaran' => 'INV-'.strtoupper(Str::random(10)),
                    'created_by'                 => $superAdminId,
                    'created_at'                 => $now, 'updated_at' => $now,
                ],
                [
                    'kunjungan_id'               => $kunjunganId,
                    'antrian_id'                 => $antrianId,
                    'tanggal_transaksi'          => $now->toDateString(),
                    'jumlah_pembayaran'          => 150000.00,
                    'metode_pembayaran'          => 'Virtual Account',
                    'channel'                    => 'VA',
                    'status_pembayaran'          => 'Menunggu',
                    'paid_at'                    => null,
                    'nomor_referensi_pembayaran' => 'INV-'.strtoupper(Str::random(10)),
                    'created_by'                 => $superAdminId,
                    'created_at'                 => $now, 'updated_at' => $now,
                ],
            ]);

        });
    }
}
