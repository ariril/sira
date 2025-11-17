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
            // 1) UNITS
            // =========================================================
            $managementId = DB::table('units')->insertGetId([
                'name' => 'Manajemen Rumah Sakit',
                'slug' => 'manajemen-rumah-sakit',
                'code' => 'MNG',
                'type' => 'manajemen',
                'parent_id' => null,
                'location' => 'Kantor Direksi',
                'phone' => null,
                'email' => null,
                'remuneration_ratio' => 0.00,
                'is_active' => 1,
                'created_at' => $now, 'updated_at' => $now,
            ]);

            $units = [
                // Hanya unit manajemen dan poliklinik sesuai kebutuhan RS
                // Manajemen (untuk Super Admin, Admin RS, dan Kepala Poliklinik)
                // Catatan: kepala poliklinik ditempatkan di Manajemen RS sesuai instruksi

                // Poliklinik (sesuai daftar pada lampiran)
                ['name' => 'Poliklinik Umum',       'slug' => 'poliklinik-umum',      'code' => 'POL-UM',  'type' => 'poliklinik', 'parent_id' => null, 'location' => 'Gedung Poli', 'phone' => null, 'email' => null],
                ['name' => 'Poliklinik Gigi & Mulut','slug' => 'poliklinik-gigi',      'code' => 'POL-GG',  'type' => 'poliklinik', 'parent_id' => null, 'location' => 'Gedung Poli', 'phone' => null, 'email' => null],
                ['name' => 'Poli KIA/KB',           'slug' => 'poli-kia',             'code' => 'POL-KIA', 'type' => 'poliklinik', 'parent_id' => null, 'location' => 'Gedung Poli', 'phone' => null, 'email' => null],
                ['name' => 'Poli Anak',             'slug' => 'poli-anak',            'code' => 'POL-AN',  'type' => 'poliklinik', 'parent_id' => null, 'location' => 'Gedung Poli', 'phone' => null, 'email' => null],
                ['name' => 'Poli Bedah',            'slug' => 'poli-bedah',           'code' => 'POL-BD',  'type' => 'poliklinik', 'parent_id' => null, 'location' => 'Gedung Poli', 'phone' => null, 'email' => null],
                ['name' => 'Poli Interna',          'slug' => 'poli-interna',         'code' => 'POL-INT', 'type' => 'poliklinik', 'parent_id' => null, 'location' => 'Gedung Poli', 'phone' => null, 'email' => null],
                ['name' => 'Poli Fisioterapi',      'slug' => 'poli-fisioterapi',     'code' => 'POL-FIS', 'type' => 'poliklinik', 'parent_id' => null, 'location' => 'Gedung Poli', 'phone' => null, 'email' => null],
                ['name' => 'Poli Hemodialisa',      'slug' => 'poli-hemodialisa',     'code' => 'POL-HEM', 'type' => 'poliklinik', 'parent_id' => null, 'location' => 'Gedung Poli', 'phone' => null, 'email' => null],
            ];
            foreach ($units as &$u) {
                $u['remuneration_ratio'] = 0.00;
                $u['is_active'] = 1;
                $u['created_at'] = $now;
                $u['updated_at'] = $now;
            }
            DB::table('units')->insert($units);

            $unitId = fn(string $slug) => DB::table('units')->where('slug', $slug)->value('id');

            // =========================================================
            // 2) PROFESSIONS
            // =========================================================
            $professions = [
                // Profesi khusus pegawai medis untuk skenario ini
                ['name' => 'Dokter Umum', 'code' => 'DOK-UM', 'description' => 'Dokter layanan primer'],
                ['name' => 'Perawat',     'code' => 'PRW',    'description' => 'Tenaga keperawatan'],
            ];
            foreach ($professions as &$p) {
                $p['created_at'] = $now;
                $p['updated_at'] = $now;
            }
            DB::table('professions')->insert($professions);

            $professionId = fn(string $code) => DB::table('professions')->where('code', $code)->value('id');

            // =========================================================
            // 3) USERS
            // =========================================================
            DB::table('users')->insert([
                [
                    'employee_number' => '197909102008032001',
                    'name' => 'Super Admin',
                    'start_date' => '2020-01-01',
                    'gender' => 'Laki-laki',
                    'nationality' => 'Indonesia',
                    'address' => 'Jl. Dr. Sutomo No. 2, Atambua',
                    'phone' => '0389-2513137',
                    'email' => 'superadmin@rsud.local',
                    'last_education' => 'S1',
                    'position' => 'Administrator',
                    'unit_id' => $unitId('manajemen-rumah-sakit'),
                    'profession_id' => null,
                    'password' => Hash::make('password'),
                    'role' => 'super_admin',
                    'email_verified_at' => $now,
                    'remember_token' => Str::random(10),
                    'created_at' => $now, 'updated_at' => $now,
                ],
                // >>> Kepala Poliklinik (baru)
                [
                    'employee_number' => '000000000000000002',
                    'name' => 'dr. Kepala Poliklinik',
                    'start_date' => '2016-06-01',
                    'gender' => 'Laki-laki',
                    'nationality' => 'Indonesia',
                    'address' => 'Atambua',
                    'phone' => '0812-0000-0002',
                    'email' => 'kepala.poliklinik@rsud.local',
                    'last_education' => 'Sp.',
                    'position' => 'Kepala Poliklinik',
                    'unit_id' => $managementId, // ditempatkan di Manajemen RS
                    'profession_id' => $professionId('DOK-UM'),
                    'password' => Hash::make('password'),
                    'role' => 'kepala_poliklinik',
                    'email_verified_at' => $now,
                    'remember_token' => Str::random(10),
                    'created_at' => $now, 'updated_at' => $now,
                ],
                // >>> Kepala Unit – ganti jadi Kepala Poli Gigi
                [
                    'employee_number' => '000000000000000003',
                    'name' => 'drg. Kepala Poli Gigi',
                    'start_date' => '2017-03-12',
                    'gender' => 'Perempuan',
                    'nationality' => 'Indonesia',
                    'address' => 'Atambua',
                    'phone' => '0812-2467-11027',
                    'email' => 'kepala.gigi@rsud.local',
                    'last_education' => 'Sp.KG',
                    'position' => 'Kepala Poli Gigi',
                    'unit_id' => $unitId('poliklinik-gigi'),
                    'profession_id' => $professionId('DOK-UM'),
                    'password' => Hash::make('password'),
                    'role' => 'kepala_unit',
                    'email_verified_at' => $now,
                    'remember_token' => Str::random(10),
                    'created_at' => $now, 'updated_at' => $now,
                ],
                [
                    'employee_number' => '000000000000000004',
                    'name' => 'Admin RS',
                    'start_date' => '2019-07-01',
                    'gender' => 'Laki-laki',
                    'nationality' => 'Indonesia',
                    'address' => 'Atambua',
                    'phone' => '0813-3333-3333',
                    'email' => 'admin.rs@rsud.local',
                    'last_education' => 'D3',
                    'position' => 'Admin RS',
                    'unit_id' => $managementId,
                    'profession_id' => null,
                    'password' => Hash::make('password'),
                    'role' => 'admin_rs',
                    'email_verified_at' => $now,
                    'remember_token' => Str::random(10),
                    'created_at' => $now, 'updated_at' => $now,
                ],
                [
                    'employee_number' => '000000000000000005',
                    'name' => 'dr. Umum Poliklinik',
                    'start_date' => '2021-02-10',
                    'gender' => 'Laki-laki',
                    'nationality' => 'Indonesia',
                    'address' => 'Atambua',
                    'phone' => '0814-4444-4444',
                    'email' => 'dokter.umum@rsud.local',
                    'last_education' => 'S.Ked',
                    'position' => 'Dokter Umum',
                    'unit_id' => $unitId('poliklinik-umum'),
                    'profession_id' => $professionId('DOK-UM'),
                    'password' => Hash::make('password'),
                    'role' => 'pegawai_medis',
                    'email_verified_at' => $now,
                    'remember_token' => Str::random(10),
                    'created_at' => $now, 'updated_at' => $now,
                ],
                [
                    'employee_number' => '000000000000000006',
                    'name' => 'Perawat Poli Umum',
                    'start_date' => '2018-11-05',
                    'gender' => 'Perempuan',
                    'nationality' => 'Indonesia',
                    'address' => 'Atambua',
                    'phone' => '0815-5555-5555',
                    'email' => 'perawat@rsud.local',
                    'last_education' => 'D3 Keperawatan',
                    'position' => 'Perawat',
                    'unit_id' => $unitId('poliklinik-umum'),
                    'profession_id' => $professionId('PRW'),
                    'password' => Hash::make('password'),
                    'role' => 'pegawai_medis',
                    'email_verified_at' => $now,
                    'remember_token' => Str::random(10),
                    'created_at' => $now, 'updated_at' => $now,
                ],
            ]);

            $userId = fn(string $email) => DB::table('users')->where('email', $email)->value('id');

            // helper ids
            $doctorId = $userId('dokter.umum@rsud.local');
            $nurseId = $userId('perawat@rsud.local');
            $superAdminId = $userId('superadmin@rsud.local');
            $adminRsId = $userId('admin.rs@rsud.local');

            // yang baru:
            $polyclinicHeadId = $userId('kepala.poliklinik@rsud.local');
            $unitHeadGigiId = $userId('kepala.gigi@rsud.local');

            $poliklinikUmumId = $unitId('poliklinik-umum');


            // =========================================================
            // 4) SITE SETTINGS
            // =========================================================
            DB::table('site_settings')->insert([
                'name' => 'RSUD Mgr. Gabriel Manek, SVD Atambua',
                'short_name' => 'RSUD GM Atambua',
                'short_description' => 'RSUD GM Atambua melayani IGD 24 jam, poliklinik, rawat inap, dan penunjang medis.',
                'address' => "Jl. Dr Sutomo No. 2, Atambua, Belu, NTT",
                'phone' => '(0389)2513137',
                'email' => 'rsudatambua66@gmail.com',
                'logo_path' => 'images/logo-rsudmgr.jpeg',
                'favicon_path' => null,
                'hero_path' => 'images/hero.jpeg',
                'facebook_url' => 'https://www.facebook.com/people/RSUD-MGR-GABRIEL-MANEK-SVD/100054300359896/',
                'instagram_url' => null,
                'twitter_url' => null,
                'youtube_url' => null,
                'footer_text' => '© ' . date('Y') . ' RSUD Mgr. Gabriel Manek, SVD Atambua. Semua hak cipta.',
                'updated_by' => $superAdminId,
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            // =========================================================
            // 5) ABOUT PAGES
            // =========================================================
            $about = [
                [
                    'type' => 'profil_rs',
                    'title' => 'Profil RSUD',
                    'content' => "RSUD Mgr. Gabriel Manek, SVD Atambua berlokasi di Jl. Dr Sutomo No. 2, Atambua. Telepon (0389)2513137. Melayani IGD 24 jam, poliklinik, rawat inap, dan penunjang medis.",
                    'image_path' => null,
                    'attachments' => json_encode([]),
                    'published_at' => $now,
                    'is_active' => 1,
                ],
                [
                    'type' => 'visi',
                    'title' => 'Visi',
                    'content' => "Mewujudkan pelayanan kesehatan yang prima, terjangkau, dan berpihak pada masyarakat—bersama yang tak mampu, kita berupaya maju.",
                    'image_path' => null,
                    'attachments' => json_encode([]),
                    'published_at' => $now,
                    'is_active' => 1,
                ],
                [
                    'type' => 'misi',
                    'title' => 'Misi',
                    'content' => "Memberikan pelayanan kesehatan yang berkualitas tanpa membebani biaya pasien; mendukung akses layanan berdasarkan kebutuhan, bukan kemampuan ekonomi; dan terus meningkatkan kapasitas RSUD melalui pengembangan SDM dan fasilitas.",
                    'image_path' => null,
                    'attachments' => json_encode([]),
                    'published_at' => $now,
                    'is_active' => 1,
                ],
                [
                    'type' => 'struktur',
                    'title' => 'Struktur Organisasi',
                    'content' => null,
                    'image_path' => null,
                    'attachments' => json_encode([]),
                    'published_at' => null,
                    'is_active' => 1,
                ],
                [
                    'type' => 'tugas_fungsi',
                    'title' => 'Tugas & Fungsi',
                    'content' => null,
                    'image_path' => null,
                    'attachments' => json_encode([]),
                    'published_at' => null,
                    'is_active' => 1,
                ],
            ];
            foreach ($about as &$a) {
                $a['created_at'] = $now;
                $a['updated_at'] = $now;
            }
            DB::table('about_pages')->insert($about);

            // =========================================================
            // 6) ASSESSMENT PERIODS
            // =========================================================
            // Periode bulanan (sesuai kebutuhan remunerasi per-bulan)
            DB::table('assessment_periods')->insert([
                [
                    'name' => 'September 2025',
                    'start_date' => '2025-09-01',
                    'end_date' => '2025-09-30',
                    'status' => 'closed',
                    'locked_at' => null,
                    'created_at' => $now,
                    'updated_at' => $now,
                ],
                [
                    'name' => 'Oktober 2025',
                    'start_date' => '2025-10-01',
                    'end_date' => '2025-10-31',
                    'status' => 'active',
                    'locked_at' => null,
                    'created_at' => $now,
                    'updated_at' => $now,
                ],
            ]);
            $periodSeptId = DB::table('assessment_periods')->where('name', 'September 2025')->value('id');
            $periodOctId  = DB::table('assessment_periods')->where('name', 'Oktober 2025')->value('id');

            // =========================================================
            // 7) PERFORMANCE CRITERIAS
            // =========================================================
            $criterias = [
                ['name' => 'Kedisiplinan', 'type' => 'benefit', 'description' => null, 'is_active' => 1],
                ['name' => 'Pelayanan Pasien', 'type' => 'benefit', 'description' => null, 'is_active' => 1],
                ['name' => 'Kepatuhan Prosedur', 'type' => 'benefit', 'description' => null, 'is_active' => 1],
            ];
            foreach ($criterias as &$k) {
                $k['created_at'] = $now;
                $k['updated_at'] = $now;
            }
            DB::table('performance_criterias')->insert($criterias);

            $criteriaId = fn(string $name) => DB::table('performance_criterias')->where('name', $name)->value('id');

            // =========================================================
            // 8) UNIT CRITERIA WEIGHTS (contoh Poli Umum)
            // =========================================================
            DB::table('unit_criteria_weights')->insert([
                [
                    'unit_id' => $poliklinikUmumId,
                    'performance_criteria_id' => $criteriaId('Kedisiplinan'),
                    'weight' => 40.00,
                    'created_at' => $now, 'updated_at' => $now
                ],
                [
                    'unit_id' => $poliklinikUmumId,
                    'performance_criteria_id' => $criteriaId('Pelayanan Pasien'),
                    'weight' => 40.00,
                    'created_at' => $now, 'updated_at' => $now
                ],
                [
                    'unit_id' => $poliklinikUmumId,
                    'performance_criteria_id' => $criteriaId('Kepatuhan Prosedur'),
                    'weight' => 20.00,
                    'created_at' => $now, 'updated_at' => $now
                ],
            ]);

            // =========================================================
            // 9) ANNOUNCEMENTS
            // =========================================================
            DB::table('announcements')->insert([
                'title' => 'Selamat datang di Sistem Informasi RSUD GM Atambua',
                'slug' => 'selamat-datang',
                'summary' => 'Portal internal untuk kinerja, remunerasi, dan layanan klinik.',
                'content' => '<p>Silakan gunakan menu di atas untuk mengakses modul-modul yang tersedia.</p>',
                'category' => 'lainnya',
                'label' => 'info',
                'is_featured' => 1,
                'published_at' => $now,
                'expired_at' => null,
                'attachments' => json_encode([]),
                'author_id' => $superAdminId,
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            // =========================================================
            // 10) FAQS (dengan user_id)
            // =========================================================
            DB::table('faqs')->insert([
                [
                    'question' => 'Jam operasional IGD?',
                    'answer' => 'IGD melayani 24 jam, 7 hari seminggu.',
                    'order' => 1, 'is_active' => 1, 'category' => 'layanan',
                    'user_id' => $superAdminId,
                    'created_at' => $now, 'updated_at' => $now
                ],
                [
                    'question' => 'Cara ambil nomor antrian poli?',
                    'answer' => 'Datang ke loket pendaftaran atau gunakan aplikasi internal bila tersedia.',
                    'order' => 2, 'is_active' => 1, 'category' => 'antrian',
                    'user_id' => $superAdminId,
                    'created_at' => $now, 'updated_at' => $now
                ],
                [
                    'question' => 'Kontak RS?',
                    'answer' => 'Telepon (0389)2513137, Email rsudatambua66@gmail.com.',
                    'order' => 3, 'is_active' => 1, 'category' => 'kontak',
                    'user_id' => $superAdminId,
                    'created_at' => $now, 'updated_at' => $now
                ],
            ]);

            // // =========================================================
            // // 11) ATTENDANCE IMPORT BATCH + ATTENDANCES
            // // =========================================================
            // $batchId = DB::table('attendance_import_batches')->insertGetId([
            //     'file_name' => 'simrs_khanza_' . date('Y-m-d') . '.xlsx',
            //     'imported_by' => $superAdminId,
            //     'imported_at' => $now,
            //     'total_rows' => 2,
            //     'success_rows' => 2,
            //     'failed_rows' => 0,
            //     'created_at' => $now, 'updated_at' => $now,
            // ]);

            // DB::table('attendances')->insert([
            //     [
            //         'user_id' => $doctorId,
            //         'attendance_date' => $now->toDateString(),
            //         'check_in' => '07:45:00',
            //         'check_out' => '14:30:00',
            //         'attendance_status' => 'Hadir',
            //         'overtime_note' => null,
            //         'source' => 'import',
            //         'import_batch_id' => $batchId,
            //         'created_at' => $now, 'updated_at' => $now,
            //     ],
            //     [
            //         'user_id' => $nurseId,
            //         'attendance_date' => $now->toDateString(),
            //         'check_in' => '07:30:00',
            //         'check_out' => '15:00:00',
            //         'attendance_status' => 'Hadir',
            //         'overtime_note' => null,
            //         'source' => 'import',
            //         'import_batch_id' => $batchId,
            //         'created_at' => $now, 'updated_at' => $now,
            //     ],
            // ]);

            // =========================================================
            // 12) ADDITIONAL CONTRIBUTIONS
            // =========================================================
            DB::table('additional_contributions')->insert([
                [
                    'user_id' => $nurseId,
                    'title' => 'Penyusunan SOP Triase Poli Umum',
                    'description' => 'Draft SOP triase pasien poli umum untuk percepatan alur.',
                    'submission_date' => $now->toDateString(),
                    'evidence_file' => null,
                    'validation_status' => 'Menunggu Persetujuan',
                    'supervisor_comment' => null,
                    'assessment_period_id' => $periodOctId,
                    'created_at' => $now, 'updated_at' => $now,
                ],
                [
                    'user_id' => $doctorId,
                    'title' => 'Edukasi DM Hipertensi Komunitas',
                    'description' => 'Materi penyuluhan singkat untuk pasien rawat jalan.',
                    'submission_date' => $now->toDateString(),
                    'evidence_file' => null,
                    'validation_status' => 'Disetujui',
                    'supervisor_comment' => 'Bagus, lanjutkan implementasi.',
                    'assessment_period_id' => $periodOctId,
                    'created_at' => $now, 'updated_at' => $now,
                ],
            ]);

            // =========================================================
            // 13) PERFORMANCE ASSESSMENTS
            // =========================================================
            // Penilaian kinerja bulanan (Oktober 2025)
            $pkDoctorOctId = DB::table('performance_assessments')->insertGetId([
                'user_id' => $doctorId,
                'assessment_period_id' => $periodOctId,
                'assessment_date' => $now->toDateString(),
                'total_wsm_score' => 86.50,
                'validation_status' => 'Menunggu Validasi',
                'supervisor_comment' => null,
                'created_at' => $now, 'updated_at' => $now,
            ]);
            $pkNurseOctId = DB::table('performance_assessments')->insertGetId([
                'user_id' => $nurseId,
                'assessment_period_id' => $periodOctId,
                'assessment_date' => $now->toDateString(),
                'total_wsm_score' => 78.25,
                'validation_status' => 'Menunggu Validasi',
                'supervisor_comment' => null,
                'created_at' => $now, 'updated_at' => $now,
            ]);

            // =========================================================
            // 14) PERFORMANCE ASSESSMENT DETAILS
            // =========================================================
            DB::table('performance_assessment_details')->insert([
                [
                    'performance_assessment_id' => $pkDoctorOctId,
                    'performance_criteria_id' => $criteriaId('Kedisiplinan'),
                    'score' => 90.00,
                    'created_at' => $now, 'updated_at' => $now,
                ],
                [
                    'performance_assessment_id' => $pkDoctorOctId,
                    'performance_criteria_id' => $criteriaId('Pelayanan Pasien'),
                    'score' => 85.00,
                    'created_at' => $now, 'updated_at' => $now,
                ],
                [
                    'performance_assessment_id' => $pkDoctorOctId,
                    'performance_criteria_id' => $criteriaId('Kepatuhan Prosedur'),
                    'score' => 80.00,
                    'created_at' => $now, 'updated_at' => $now,
                ],
                [
                    'performance_assessment_id' => $pkNurseOctId,
                    'performance_criteria_id' => $criteriaId('Kedisiplinan'),
                    'score' => 82.00,
                    'created_at' => $now, 'updated_at' => $now,
                ],
                [
                    'performance_assessment_id' => $pkNurseOctId,
                    'performance_criteria_id' => $criteriaId('Pelayanan Pasien'),
                    'score' => 79.00,
                    'created_at' => $now, 'updated_at' => $now,
                ],
                [
                    'performance_assessment_id' => $pkNurseOctId,
                    'performance_criteria_id' => $criteriaId('Kepatuhan Prosedur'),
                    'score' => 74.00,
                    'created_at' => $now, 'updated_at' => $now,
                ],
            ]);

            // =========================================================
            // 15) ASSESSMENT APPROVALS (dengan snapshot profesi - opsional)
            // =========================================================
            $firstApproverId = $unitHeadGigiId ?? $polyclinicHeadId ?? $adminRsId ?? $superAdminId;

            DB::table('assessment_approvals')->insert([
                [
                    'performance_assessment_id' => $pkDoctorOctId,
                    'approver_id' => $firstApproverId,
                    'level' => 1,
                    'status' => 'approved',
                    'note' => 'Disetujui, lanjut ke manajemen.',
                    'acted_at' => $now,
                    'created_at' => $now, 'updated_at' => $now,
                ],
                [
                    'performance_assessment_id' => $pkDoctorOctId,
                    'approver_id' => $superAdminId,
                    'level' => 2,
                    'status' => 'pending',
                    'note' => null,
                    'acted_at' => null,
                    'created_at' => $now, 'updated_at' => $now,
                ],
                [
                    'performance_assessment_id' => $pkNurseOctId,
                    'approver_id' => $firstApproverId,
                    'level' => 1,
                    'status' => 'approved',
                    'note' => 'Disetujui.',
                    'acted_at' => $now,
                    'created_at' => $now, 'updated_at' => $now,
                ],
                [
                    'performance_assessment_id' => $pkNurseOctId,
                    'approver_id' => $superAdminId,
                    'level' => 2,
                    'status' => 'pending',
                    'note' => null,
                    'acted_at' => null,
                    'created_at' => $now, 'updated_at' => $now,
                ],
            ]);

            // =========================================================
            // 16) REMUNERATIONS
            // =========================================================
            // Remunerasi bulanan (2-3 data untuk pegawai medis)
            DB::table('remunerations')->insert([
                [
                    'user_id' => $doctorId,
                    'assessment_period_id' => $periodSeptId,
                    'amount' => 3200000.00,
                    'payment_date' => null,
                    'payment_status' => 'Belum Dibayar',
                    'calculation_details' => json_encode([
                        'komponen' => [
                            'dasar' => 2800000,
                            'pasien_ditangani' => ['jumlah' => 95, 'nilai' => 285000],
                            'review_pelanggan' => ['jumlah' => 8,  'nilai' => 80000],
                            'kontribusi_tambahan' => ['jumlah' => 1, 'nilai' => 35000],
                        ],
                        'rincian' => [
                            'kontribusi' => ['SOP Triase Poli Umum'],
                        ],
                        'catatan' => 'Periode September 2025',
                    ], JSON_UNESCAPED_UNICODE),
                    'published_at' => null,
                    'calculated_at' => $now,
                    'revised_by' => null,
                    'created_at' => $now, 'updated_at' => $now,
                ],
                [
                    'user_id' => $doctorId,
                    'assessment_period_id' => $periodOctId,
                    'amount' => 3500000.00,
                    'payment_date' => null,
                    'payment_status' => 'Belum Dibayar',
                    'calculation_details' => json_encode([
                        'komponen' => [
                            'dasar' => 3000000,
                            'pasien_ditangani' => ['jumlah' => 120, 'nilai' => 360000],
                            'review_pelanggan' => ['jumlah' => 12,  'nilai' => 120000],
                            'kontribusi_tambahan' => ['jumlah' => 1, 'nilai' => 40000],
                        ],
                        'rincian' => [
                            'kontribusi' => ['Edukasi DM Hipertensi Komunitas'],
                        ],
                        'catatan' => 'Periode Oktober 2025',
                    ], JSON_UNESCAPED_UNICODE),
                    'published_at' => null,
                    'calculated_at' => $now,
                    'revised_by' => null,
                    'created_at' => $now, 'updated_at' => $now,
                ],
                [
                    'user_id' => $nurseId,
                    'assessment_period_id' => $periodOctId,
                    'amount' => 2100000.00,
                    'payment_date' => null,
                    'payment_status' => 'Belum Dibayar',
                    'calculation_details' => json_encode([
                        'komponen' => [
                            'dasar' => 1900000,
                            'pasien_ditangani' => ['jumlah' => 140, 'nilai' => 140000],
                            'review_pelanggan' => ['jumlah' => 10,  'nilai' => 50000],
                            'kontribusi_tambahan' => ['jumlah' => 1, 'nilai' => 10000],
                        ],
                        'rincian' => [
                            'kontribusi' => ['SOP Triase Poli Umum'],
                        ],
                        'catatan' => 'Periode Oktober 2025',
                    ], JSON_UNESCAPED_UNICODE),
                    'published_at' => null,
                    'calculated_at' => $now,
                    'revised_by' => null,
                    'created_at' => $now, 'updated_at' => $now,
                ],
            ]);

            // =========================================================
            // 17) REVIEWS (tanpa relasi visit)
            // =========================================================
            $reviewId = DB::table('reviews')->insertGetId([
                'registration_ref' => 'REG-' . date('Ymd') . '-0001',
                'unit_id' => $poliklinikUmumId,
                'overall_rating' => 5,
                'comment' => 'Pelayanan cepat dan ramah.',
                'patient_name' => 'Bapak A',
                'contact' => '08xxxxxxxxxx',
                'client_ip' => '127.0.0.1',
                'user_agent' => 'Seeder',
                'created_at' => $now, 'updated_at' => $now,
            ]);
            $reviewId2 = DB::table('reviews')->insertGetId([
                'registration_ref' => 'REG-' . date('Ymd') . '-0002',
                'unit_id' => $poliklinikUmumId,
                'overall_rating' => 4,
                'comment' => 'Dokter informatif, proses cepat.',
                'patient_name' => 'Ibu B',
                'contact' => '08xxxxxxxxxx',
                'client_ip' => '127.0.0.1',
                'user_agent' => 'Seeder',
                'created_at' => $now, 'updated_at' => $now,
            ]);

            // =========================================================
            // 18) REVIEW DETAILS (per nakes)
            // =========================================================
            DB::table('review_details')->insert([
                [
                    'review_id' => $reviewId,
                    'medical_staff_id' => $doctorId,
                    'role' => 'dokter',
                    'rating' => 5,
                    'comment' => 'Dokter komunikatif.',
                    'created_at' => $now, 'updated_at' => $now,
                ],
                [
                    'review_id' => $reviewId,
                    'medical_staff_id' => $nurseId,
                    'role' => 'perawat',
                    'rating' => 5,
                    'comment' => 'Perawat membantu dengan sigap.',
                    'created_at' => $now, 'updated_at' => $now,
                ],
                [
                    'review_id' => $reviewId2,
                    'medical_staff_id' => $doctorId,
                    'role' => 'dokter',
                    'rating' => 4,
                    'comment' => 'Penjelasan jelas.',
                    'created_at' => $now, 'updated_at' => $now,
                ],
            ]);
        });
    }
}
