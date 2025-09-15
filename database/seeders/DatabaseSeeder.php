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
            // 1) UNITS (lengkap: manajemen, admin, poli, penunjang)
            // =========================================================

            // Insert Manajemen dulu agar parent_id bisa direferensikan
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
                // Administrasi
                ['name'=>'Sumber Daya Manusia','slug'=>'sdm','code'=>'SDM','type'=>'administrasi','parent_id'=>$managementId,'location'=>'Gedung Administrasi','phone'=>null,'email'=>null],
                ['name'=>'Keuangan','slug'=>'keuangan','code'=>'KEU','type'=>'administrasi','parent_id'=>$managementId,'location'=>'Gedung Administrasi','phone'=>null,'email'=>null],
                ['name'=>'Rekam Medis & Informasi','slug'=>'rekam-medis','code'=>'RM','type'=>'administrasi','parent_id'=>$managementId,'location'=>'Lantai Dasar','phone'=>null,'email'=>null],

                // Gawat darurat & rawat inap
                ['name'=>'Instalasi Gawat Darurat (IGD)','slug'=>'igd','code'=>'IGD','type'=>'igd','parent_id'=>null,'location'=>'Gedung IGD 24 Jam','phone'=>null,'email'=>null],
                ['name'=>'Rawat Inap','slug'=>'rawat-inap','code'=>'RWI','type'=>'rawat_inap','parent_id'=>null,'location'=>'Gedung Rawat Inap','phone'=>null,'email'=>null],

                // Poliklinik
                ['name'=>'Poliklinik Umum','slug'=>'poliklinik-umum','code'=>'POL-UM','type'=>'poliklinik','parent_id'=>null,'location'=>'Gedung Poli','phone'=>null,'email'=>null],
                ['name'=>'Poliklinik Gigi & Mulut','slug'=>'poliklinik-gigi','code'=>'POL-GG','type'=>'poliklinik','parent_id'=>null,'location'=>'Gedung Poli','phone'=>null,'email'=>null],
                ['name'=>'Poliklinik KIA/KB','slug'=>'poliklinik-kia-kb','code'=>'POL-KIA','type'=>'poliklinik','parent_id'=>null,'location'=>'Gedung Poli','phone'=>null,'email'=>null],
                ['name'=>'Penyakit Dalam','slug'=>'penyakit-dalam','code'=>'POL-PD','type'=>'poliklinik','parent_id'=>null,'location'=>'Gedung Poli','phone'=>null,'email'=>null],
                ['name'=>'Kesehatan Anak','slug'=>'kesehatan-anak','code'=>'POL-AN','type'=>'poliklinik','parent_id'=>null,'location'=>'Gedung Poli','phone'=>null,'email'=>null],
                ['name'=>'Bedah','slug'=>'bedah','code'=>'POL-BD','type'=>'poliklinik','parent_id'=>null,'location'=>'Gedung Poli','phone'=>null,'email'=>null],
                ['name'=>'Obstetri & Ginekologi','slug'=>'obgyn','code'=>'POL-OBG','type'=>'poliklinik','parent_id'=>null,'location'=>'Gedung Poli','phone'=>null,'email'=>null],
                ['name'=>'Mata','slug'=>'mata','code'=>'POL-MT','type'=>'poliklinik','parent_id'=>null,'location'=>'Gedung Poli','phone'=>null,'email'=>null],
                ['name'=>'Paru','slug'=>'paru','code'=>'POL-PR','type'=>'poliklinik','parent_id'=>null,'location'=>'Gedung Poli','phone'=>null,'email'=>null],
                ['name'=>'Saraf','slug'=>'saraf','code'=>'POL-SR','type'=>'poliklinik','parent_id'=>null,'location'=>'Gedung Poli','phone'=>null,'email'=>null],
                ['name'=>'Jantung & Pembuluh Darah','slug'=>'jantung','code'=>'POL-JTG','type'=>'poliklinik','parent_id'=>null,'location'=>'Gedung Poli','phone'=>null,'email'=>null],

                // Penunjang
                ['name'=>'Laboratorium','slug'=>'laboratorium','code'=>'LAB','type'=>'penunjang','parent_id'=>null,'location'=>'Gedung Penunjang','phone'=>null,'email'=>null],
                ['name'=>'Radiologi','slug'=>'radiologi','code'=>'RAD','type'=>'penunjang','parent_id'=>null,'location'=>'Gedung Penunjang','phone'=>null,'email'=>null],
                ['name'=>'Farmasi','slug'=>'farmasi','code'=>'FAR','type'=>'penunjang','parent_id'=>null,'location'=>'Gedung Penunjang','phone'=>null,'email'=>null],
                ['name'=>'Rehabilitasi Medik','slug'=>'rehabilitasi-medik','code'=>'RHM','type'=>'penunjang','parent_id'=>null,'location'=>'Gedung Penunjang','phone'=>null,'email'=>null],
                ['name'=>'CSSD (Sterilisasi)','slug'=>'cssd','code'=>'CSSD','type'=>'penunjang','parent_id'=>null,'location'=>'Penunjang','phone'=>null,'email'=>null],
                ['name'=>'Bank Darah','slug'=>'bank-darah','code'=>'BD','type'=>'penunjang','parent_id'=>null,'location'=>'Penunjang','phone'=>null,'email'=>null],
                ['name'=>'Laundry','slug'=>'laundry','code'=>'LDY','type'=>'penunjang','parent_id'=>null,'location'=>'Penunjang','phone'=>null,'email'=>null],
            ];
            foreach ($units as &$u) {
                $u['remuneration_ratio'] = 0.00;
                $u['is_active'] = 1;
                $u['created_at'] = $now;
                $u['updated_at'] = $now;
            }
            DB::table('units')->insert($units);

            // Helper ambil id unit cepat
            $unitId = fn(string $slug) => DB::table('units')->where('slug', $slug)->value('id');

            // =========================================================
            // 2) PROFESSIONS
            // =========================================================
            $professions = [
                ['name'=>'Dokter Umum','code'=>'DOK-UM','description'=>'Dokter layanan primer'],
                ['name'=>'Dokter Spesialis','code'=>'DOK-SP','description'=>'Dokter spesialis klinis'],
                ['name'=>'Perawat','code'=>'PRW','description'=>'Tenaga keperawatan'],
                ['name'=>'Bidan','code'=>'BDN','description'=>'Tenaga kebidanan'],
                ['name'=>'Apoteker','code'=>'APT','description'=>'Tenaga farmasi'],
                ['name'=>'Analis Lab','code'=>'LAB-AN','description'=>'Tenaga laboratorium'],
                ['name'=>'Radiografer','code'=>'RAD','description'=>'Tenaga radiologi'],
                ['name'=>'Administrasi','code'=>'ADM','description'=>'Staf administrasi'],
            ];
            foreach ($professions as &$p) { $p['created_at']=$now; $p['updated_at']=$now; }
            DB::table('professions')->insert($professions);

            $professionId = fn(string $code) => DB::table('professions')->where('code', $code)->value('id');

            // =========================================================
            // 3) USERS (akun awal)
            // =========================================================
            $users = [
                [
                    'employee_number' => '000000000000000001', // nip
                    'name' => 'Super Admin',
                    'start_date' => '2020-01-01',
                    'gender' => 'Laki-laki',
                    'nationality' => 'Indonesia',
                    'id_number' => 'ID-0001',
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
                [
                    'employee_number' => '000000000000000002',
                    'name' => 'dr. Kepala IGD',
                    'start_date' => '2017-03-12',
                    'gender' => 'Perempuan',
                    'nationality' => 'Indonesia',
                    'id_number' => 'ID-0002',
                    'address' => 'Atambua',
                    'phone' => '0812-2467-11027',
                    'email' => 'kepala.igd@rsud.local',
                    'last_education' => 'Sp.',
                    'position' => 'Kepala Unit IGD',
                    'unit_id' => $unitId('igd'),
                    'profession_id' => $professionId('DOK-UM'),
                    'password' => Hash::make('password'),
                    'role' => 'kepala_unit',
                    'email_verified_at' => $now,
                    'remember_token' => Str::random(10),
                    'created_at' => $now, 'updated_at' => $now,
                ],
                [
                    'employee_number' => '000000000000000003',
                    'name' => 'Staf Rekam Medis',
                    'start_date' => '2019-07-01',
                    'gender' => 'Laki-laki',
                    'nationality' => 'Indonesia',
                    'id_number' => 'ID-0003',
                    'address' => 'Atambua',
                    'phone' => '0813-3333-3333',
                    'email' => 'rekam.medis@rsud.local',
                    'last_education' => 'D3',
                    'position' => 'Staf Administrasi',
                    'unit_id' => $unitId('rekam-medis'),
                    'profession_id' => $professionId('ADM'),
                    'password' => Hash::make('password'),
                    'role' => 'administrasi',
                    'email_verified_at' => $now,
                    'remember_token' => Str::random(10),
                    'created_at' => $now, 'updated_at' => $now,
                ],
                [
                    'employee_number' => '000000000000000004',
                    'name' => 'dr. Umum Poliklinik',
                    'start_date' => '2021-02-10',
                    'gender' => 'Laki-laki',
                    'nationality' => 'Indonesia',
                    'id_number' => 'ID-0004',
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
                    'employee_number' => '000000000000000005',
                    'name' => 'Perawat Poli Umum',
                    'start_date' => '2018-11-05',
                    'gender' => 'Perempuan',
                    'nationality' => 'Indonesia',
                    'id_number' => 'ID-0005',
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
            ];
            DB::table('users')->insert($users);

            $userId = fn(string $email) => DB::table('users')->where('email', $email)->value('id');

            // =========================================================
            // 4) SITE SETTINGS
            // =========================================================
            DB::table('site_settings')->insert([
                'name'             => 'RSUD Mgr. Gabriel Manek, SVD Atambua',
                'short_name'       => 'RSUD GM Atambua',
                'short_description'=> 'RSUD GM Atambua melayani IGD 24 jam, poliklinik, rawat inap, dan penunjang medis.',
                'address'          => "Jl. Dr Sutomo No. 2, Atambua, Belu, NTT",
                'phone'            => '(0389)2513137',
                'email'            => 'rsudatambua66@gmail.com',
                'logo_path'        => null,
                'favicon_path'     => null,
                'facebook_url'     => 'https://www.facebook.com/people/RSUD-MGR-GABRIEL-MANEK-SVD/100054300359896/',
                'instagram_url'    => null,
                'twitter_url'      => null,
                'youtube_url'      => null,
                'footer_text'      => '© ' . date('Y') . ' RSUD Mgr. Gabriel Manek, SVD Atambua. Semua hak cipta.',
                'updated_by'       => $userId('superadmin@rsud.local'),
                'created_at'       => $now,
                'updated_at'       => $now,
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
            foreach ($about as &$a) { $a['created_at']=$now; $a['updated_at']=$now; }
            DB::table('about_pages')->insert($about);

            // =========================================================
            // 6) ASSESSMENT PERIODS
            // =========================================================
            DB::table('assessment_periods')->insert([
                'name' => 'Triwulan III 2025',
                'start_date' => '2025-07-01',
                'end_date' => '2025-09-30',
                'cycle' => 'triwulan',
                'status' => 'berjalan',
                'is_active' => 1,
                'locked_at' => null,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
            $assessmentPeriodId = DB::table('assessment_periods')->where('name','Triwulan III 2025')->value('id');

            // =========================================================
            // 7) PERFORMANCE CRITERIAS
            // =========================================================
            $criterias = [
                ['name'=>'Kedisiplinan','type'=>'benefit','description'=>null,'is_active'=>1],
                ['name'=>'Pelayanan Pasien','type'=>'benefit','description'=>null,'is_active'=>1],
                ['name'=>'Kepatuhan Prosedur','type'=>'benefit','description'=>null,'is_active'=>1],
            ];
            foreach ($criterias as &$k) { $k['created_at']=$now; $k['updated_at']=$now; }
            DB::table('performance_criterias')->insert($criterias);

            $criteriaId = fn(string $name) =>
            DB::table('performance_criterias')->where('name', $name)->value('id');

            // =========================================================
            // 8) UNIT CRITERIA WEIGHTS (contoh untuk Poli Umum)
            // =========================================================
            DB::table('unit_criteria_weights')->insert([
                [
                    'unit_id' => $unitId('poliklinik-umum'),
                    'performance_criteria_id' => $criteriaId('Kedisiplinan'),
                    'weight' => 40.00,
                    'created_at'=>$now,'updated_at'=>$now
                ],
                [
                    'unit_id' => $unitId('poliklinik-umum'),
                    'performance_criteria_id' => $criteriaId('Pelayanan Pasien'),
                    'weight' => 40.00,
                    'created_at'=>$now,'updated_at'=>$now
                ],
                [
                    'unit_id' => $unitId('poliklinik-umum'),
                    'performance_criteria_id' => $criteriaId('Kepatuhan Prosedur'),
                    'weight' => 20.00,
                    'created_at'=>$now,'updated_at'=>$now
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
                'author_id' => $userId('superadmin@rsud.local'),
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            // =========================================================
            // 10) FAQS
            // =========================================================
            DB::table('faqs')->insert([
                [
                    'question'=>'Jam operasional IGD?',
                    'answer'=>'IGD melayani 24 jam, 7 hari seminggu.',
                    'order'=>1,'is_active'=>1,'category'=>'layanan',
                    'created_at'=>$now,'updated_at'=>$now
                ],
                [
                    'question'=>'Cara ambil nomor antrian poli?',
                    'answer'=>'Datang ke loket pendaftaran atau gunakan aplikasi internal bila tersedia.',
                    'order'=>2,'is_active'=>1,'category'=>'antrian',
                    'created_at'=>$now,'updated_at'=>$now
                ],
                [
                    'question'=>'Kontak RS?',
                    'answer'=>'Telepon (0389)2513137, Email rsudatambua66@gmail.com.',
                    'order'=>3,'is_active'=>1,'category'=>'kontak',
                    'created_at'=>$now,'updated_at'=>$now
                ],
            ]);

            // =========================================================
            // (Bonus UI) VISITS & QUEUE HARI INI
            // =========================================================
            $visitId = DB::table('visits')->insertGetId([
                'ticket_code' => 'KJ-'.date('Ymd').'-0001',
                'unit_id' => $unitId('poliklinik-umum'),
                'visit_date' => $now,
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            $queueId = DB::table('patient_queues')->insertGetId([
                'queue_date' => $now->toDateString(),
                'queue_number' => 1,
                'queue_status' => 'Menunggu',
                'queued_at' => $now->format('H:i:s'),
                'service_started_at' => null,
                'service_finished_at' => null,
                'on_duty_doctor_id' => $userId('dokter.umum@rsud.local'),
                'unit_id' => $unitId('poliklinik-umum'),
                'patient_ref' => 'RM-EX-0001',
                'visit_id' => $visitId,
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            DB::table('visit_medical_staff')->insert([
                [
                    'visit_id'=>$visitId,
                    'medical_staff_id'=>$userId('dokter.umum@rsud.local'),
                    'role'=>'dokter',
                    'duration_minutes'=>15,
                    'created_at'=>$now,'updated_at'=>$now
                ],
                [
                    'visit_id'=>$visitId,
                    'medical_staff_id'=>$userId('perawat@rsud.local'),
                    'role'=>'perawat',
                    'duration_minutes'=>10,
                    'created_at'=>$now,'updated_at'=>$now
                ],
            ]);

            // =========================================================
            // Helper IDs (dipakai di bawah)
            // =========================================================
            $doctorId     = $userId('dokter.umum@rsud.local');
            $nurseId      = $userId('perawat@rsud.local');
            $superAdminId = $userId('superadmin@rsud.local');
            $headIgdId    = $userId('kepala.igd@rsud.local');
            $poliklinikUmumUnitId = $unitId('poliklinik-umum');
            $assessmentPeriodId = DB::table('assessment_periods')->orderBy('id', 'desc')->value('id');

            // Pastikan ada $visitId
            $visitId = $visitId ?? DB::table('visits')->insertGetId([
                'ticket_code' => 'KJ-'.date('Ymd').'-X001',
                'unit_id'     => $poliklinikUmumUnitId,
                'visit_date'  => $now,
                'created_at'  => $now, 'updated_at' => $now,
            ]);

            // =========================================================
            // 11) DOCTOR SCHEDULES (3 hari ke depan)
            // =========================================================
            $schedules = [];
            for ($i=0; $i<3; $i++) {
                $date = Carbon::now()->addDays($i)->toDateString();
                $schedules[] = [
                    'doctor_id'   => $doctorId,
                    'schedule_date' => $date,
                    'start_time'  => '08:00:00',
                    'end_time'    => '12:00:00',
                    'unit_id'     => $poliklinikUmumUnitId,
                    'created_at'  => $now, 'updated_at' => $now,
                ];
            }
            DB::table('doctor_schedules')->insert($schedules);

            // =========================================================
            // 12) ATTENDANCE IMPORT BATCH (dummy 1 file) -> relasi attendances
            // =========================================================
            $batchId = DB::table('attendance_import_batches')->insertGetId([
                'file_name'       => 'simrs_khanza_'.date('Y-m-d').'.xlsx',
                'imported_by'     => $superAdminId,
                'imported_at'     => $now,
                'total_rows'      => 2,
                'success_rows'    => 2,
                'failed_rows'     => 0,
                'created_at'      => $now, 'updated_at' => $now,
            ]);

            // =========================================================
            // 13) ATTENDANCES (dokter & perawat hari ini, sumber import)
            // =========================================================
            DB::table('attendances')->insert([
                [
                    'user_id'          => $doctorId,
                    'attendance_date'  => $now->toDateString(),
                    'check_in'         => '07:45:00',
                    'check_out'        => '14:30:00',
                    'attendance_status'=> 'Hadir',
                    'overtime_note'    => null,
                    'source'           => 'import',
                    'import_batch_id'  => $batchId,
                    'created_at'       => $now, 'updated_at' => $now,
                ],
                [
                    'user_id'          => $nurseId,
                    'attendance_date'  => $now->toDateString(),
                    'check_in'         => '07:30:00',
                    'check_out'        => '15:00:00',
                    'attendance_status'=> 'Hadir',
                    'overtime_note'    => null,
                    'source'           => 'import',
                    'import_batch_id'  => $batchId,
                    'created_at'       => $now, 'updated_at' => $now,
                ],
            ]);

            // =========================================================
            // 14) ADDITIONAL CONTRIBUTIONS (contoh dua usulan pegawai)
            // =========================================================
            DB::table('additional_contributions')->insert([
                [
                    'user_id'              => $nurseId,
                    'title'                => 'Penyusunan SOP Triase Poli Umum',
                    'description'          => 'Draft SOP triase pasien poli umum untuk percepatan alur.',
                    'submission_date'      => $now->toDateString(),
                    'evidence_file'        => null,
                    'validation_status'    => 'Menunggu Persetujuan', // mapped from 'menunggu'
                    'supervisor_comment'   => null,
                    'assessment_period_id' => $assessmentPeriodId,
                    'created_at'           => $now, 'updated_at' => $now,
                ],
                [
                    'user_id'              => $doctorId,
                    'title'                => 'Edukasi DM Hipertensi Komunitas',
                    'description'          => 'Materi penyuluhan singkat untuk pasien rawat jalan.',
                    'submission_date'      => $now->toDateString(),
                    'evidence_file'        => null,
                    'validation_status'    => 'Disetujui',           // mapped from 'disetujui'
                    'supervisor_comment'   => 'Bagus, lanjutkan implementasi.',
                    'assessment_period_id' => $assessmentPeriodId,
                    'created_at'           => $now, 'updated_at' => $now,
                ],
            ]);

            // =========================================================
            // 15) PERFORMANCE ASSESSMENTS (dokter poli umum pada periode aktif)
            // =========================================================
            $pkDoctorId = DB::table('performance_assessments')->insertGetId([
                'user_id'                => $doctorId,
                'assessment_period_id'   => $assessmentPeriodId,
                'assessment_date'        => $now->toDateString(),
                'total_wsm_score'        => 86.50,
                'validation_status'      => 'Menunggu Validasi', // mapped from 'menunggu'
                'supervisor_comment'     => null,
                'created_at'             => $now, 'updated_at' => $now,
            ]);

            // =========================================================
            // 16) PERFORMANCE ASSESSMENT DETAILS (sinkron dgn criterias yang ada)
            // =========================================================
            $kedisiplinanId    = $criteriaId('Kedisiplinan');
            $pelayananPasienId = $criteriaId('Pelayanan Pasien');
            $kepatuhanId       = $criteriaId('Kepatuhan Prosedur');

            DB::table('performance_assessment_details')->insert([
                [
                    'performance_assessment_id' => $pkDoctorId,
                    'performance_criteria_id'   => $kedisiplinanId,
                    'score'                     => 90.00,
                    'created_at'                => $now, 'updated_at' => $now,
                ],
                [
                    'performance_assessment_id' => $pkDoctorId,
                    'performance_criteria_id'   => $pelayananPasienId,
                    'score'                     => 85.00,
                    'created_at'                => $now, 'updated_at' => $now,
                ],
                [
                    'performance_assessment_id' => $pkDoctorId,
                    'performance_criteria_id'   => $kepatuhanId,
                    'score'                     => 80.00,
                    'created_at'                => $now, 'updated_at' => $now,
                ],
            ]);

            // =========================================================
            // 17) ASSESSMENT APPROVALS (2 level: Kepala Unit -> Manajemen)
            // =========================================================
            DB::table('assessment_approvals')->insert([
                [
                    'performance_assessment_id' => $pkDoctorId,
                    'approver_id'               => $headIgdId,
                    'level'                     => 1,
                    'status'                    => 'approved',
                    'note'                      => 'Disetujui, lanjut ke manajemen.',
                    'acted_at'                  => $now,
                    'created_at'                => $now, 'updated_at' => $now,
                ],
                [
                    'performance_assessment_id' => $pkDoctorId,
                    'approver_id'               => $superAdminId,
                    'level'                     => 2,
                    'status'                    => 'pending',
                    'note'                      => null,
                    'acted_at'                  => null,
                    'created_at'                => $now, 'updated_at' => $now,
                ],
            ]);

            // =========================================================
            // 18) REMUNERATIONS (berdasarkan periode & user yang sama)
            // =========================================================
            DB::table('remunerations')->insert([
                'user_id'              => $doctorId,
                'assessment_period_id' => $assessmentPeriodId,
                'amount'               => 3500000.00,
                'payment_date'         => null,
                'payment_status'       => 'Belum Dibayar',
                'calculation_details'  => json_encode([
                    'dasar' => 3000000,
                    'insentif_kinerja' => 500000,
                    'catatan' => 'Mengacu skor WSM & kontribusi tambahan',
                ]),
                'published_at'         => null,
                'calculated_at'        => $now,
                'revised_by'           => null,
                'created_at'           => $now, 'updated_at' => $now,
            ]);

            // =========================================================
            // 19) REVIEWS (feedback pasien sederhana)
            // =========================================================
            $reviewId = DB::table('reviews')->insertGetId([
                'visit_id'        => $visitId,
                'overall_rating'  => 5,
                'comment'         => 'Pelayanan cepat dan ramah.',
                'patient_name'    => 'Bapak A',
                'contact'         => '08xxxxxxxxxx',
                'client_ip'       => request()->ip() ?? '127.0.0.1',
                'user_agent'      => request()->userAgent() ?? 'Seeder',
                'created_at'      => $now, 'updated_at' => $now,
            ]);

            // =========================================================
            // 20) REVIEW DETAILS (per nakes di kunjungan tersebut)
            // =========================================================
            DB::table('review_details')->insert([
                [
                    'review_id'        => $reviewId,
                    'medical_staff_id' => $doctorId,
                    'role'             => 'dokter',
                    'rating'           => 5,
                    'comment'          => 'Dokter komunikatif.',
                    'created_at'       => $now, 'updated_at' => $now,
                ],
                [
                    'review_id'        => $reviewId,
                    'medical_staff_id' => $nurseId,
                    'role'             => 'perawat',
                    'rating'           => 5,
                    'comment'          => 'Perawat membantu dengan sigap.',
                    'created_at'       => $now, 'updated_at' => $now,
                ],
            ]);

            // =========================================================
            // SISA TABEL YANG BELUM DI-SEED:
            // - (21) logbook_entries
            // - (17) payment_transactions
            // =========================================================

            // Pastikan helper siap
            $doctorId     = $doctorId     ?? $userId('dokter.umum@rsud.local');
            $nurseId      = $nurseId      ?? $userId('perawat@rsud.local');
            $superAdminId = $superAdminId ?? $userId('superadmin@rsud.local');
            $headIgdId    = $headIgdId    ?? $userId('kepala.igd@rsud.local');
            $rekamMedisId = $rekamMedisId ?? $userId('rekam.medis@rsud.local');
            $poliklinikUmumId = $poliklinikUmumId ?? $unitId('poliklinik-umum');

            // Pastikan ada visit & queue untuk relasi transaksi
            $visitId = $visitId ?? DB::table('visits')->orderByDesc('id')->value('id');
            if (!$visitId) {
                $visitId = DB::table('visits')->insertGetId([
                    'ticket_code' => 'KJ-'.date('Ymd').'-B001',
                    'unit_id'     => $poliklinikUmumId,
                    'visit_date'  => $now,
                    'created_at'  => $now, 'updated_at' => $now,
                ]);
            }

            $queueId = $queueId ?? DB::table('patient_queues')->orderByDesc('id')->value('id');
            if (!$queueId) {
                $queueId = DB::table('patient_queues')->insertGetId([
                    'queue_date'           => $now->toDateString(),
                    'queue_number'         => 2,
                    'queue_status'         => 'Menunggu',
                    'queued_at'            => $now->format('H:i:s'),
                    'service_started_at'   => null,
                    'service_finished_at'  => null,
                    'on_duty_doctor_id'    => $doctorId,
                    'unit_id'              => $poliklinikUmumId,
                    'patient_ref'          => 'RM-EX-0002',
                    'visit_id'             => $visitId,
                    'created_at'           => $now, 'updated_at' => $now,
                ]);
            }

            // =========================================================
            // (21) LOGBOOK ENTRIES — contoh 3 entri (dokter, perawat, rekam medis)
            // =========================================================
            DB::table('logbook_entries')->insert([
                [
                    'user_id'         => $doctorId,
                    'entry_date'      => '2025-09-09',
                    'start_time'      => '08:00:00',
                    'end_time'        => '11:00:00',
                    'duration_minutes'=> 180,
                    'activity'        => 'Pelayanan pasien rawat jalan Poli Umum (5 pasien).',
                    'category'        => 'pelayanan',
                    'status'          => 'disetujui',
                    'approver_id'     => $headIgdId,
                    'approved_at'     => '2025-09-09 13:01:11',
                    'attachments'     => '[]',
                    'created_at'      => '2025-09-09 13:01:11',
                    'updated_at'      => '2025-09-09 13:01:11',
                ],
                [
                    'user_id'         => $nurseId,
                    'entry_date'      => '2025-09-09',
                    'start_time'      => '07:30:00',
                    'end_time'        => '12:00:00',
                    'duration_minutes'=> 270,
                    'activity'        => 'Triase, pengukuran TTV, dan edukasi pra-konsultasi.',
                    'category'        => 'keperawatan',
                    'status'          => 'diajukan',
                    'approver_id'     => $nurseId, // sesuai contoh asli (boleh diganti null)
                    'approved_at'     => null,
                    'attachments'     => '[]',
                    'created_at'      => '2025-09-09 13:01:11',
                    'updated_at'      => '2025-09-09 13:01:11',
                ],
                [
                    'user_id'         => $rekamMedisId,
                    'entry_date'      => '2025-09-09',
                    'start_time'      => '09:00:00',
                    'end_time'        => '10:30:00',
                    'duration_minutes'=> 90,
                    'activity'        => 'Verifikasi dan penyusunan berkas rekam medis kunjungan hari ini.',
                    'category'        => 'administrasi',
                    'status'          => 'draf',
                    'approver_id'     => $rekamMedisId,
                    'approved_at'     => null,
                    'attachments'     => '[]',
                    'created_at'      => '2025-09-09 13:01:11',
                    'updated_at'      => '2025-09-09 13:01:11',
                ],
            ]);

            // =========================================================
            // (17) PAYMENT TRANSACTIONS — 2 contoh transaksi
            // =========================================================
            DB::table('payment_transactions')->insert([
                [
                    'visit_id'                 => $visitId,
                    'queue_id'                 => $queueId,
                    'transaction_date'         => $now->toDateString(),
                    'amount'                   => 75000.00,
                    'payment_method'           => 'QRIS',
                    'channel'                  => 'QRIS',
                    'payment_status'           => 'Berhasil',   // enum: Berhasil | Pending | Gagal
                    'paid_at'                  => $now,
                    'payment_reference_number' => 'INV-'.strtoupper(Str::random(10)),
                    'created_by'               => $superAdminId,
                    'created_at'               => $now, 'updated_at' => $now,
                ],
                [
                    'visit_id'                 => $visitId,
                    'queue_id'                 => $queueId,
                    'transaction_date'         => $now->toDateString(),
                    'amount'                   => 150000.00,
                    'payment_method'           => 'Virtual Account',
                    'channel'                  => 'VA',
                    'payment_status'           => 'Pending',    // mapped dari 'Menunggu'
                    'paid_at'                  => null,
                    'payment_reference_number' => 'INV-'.strtoupper(Str::random(10)),
                    'created_by'               => $superAdminId,
                    'created_at'               => $now, 'updated_at' => $now,
                ],
            ]);

        });
    }
}
