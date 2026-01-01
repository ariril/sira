<?php

namespace Database\Seeders;

use App\Enums\RaterWeightStatus;
use App\Models\RaterWeight;
use App\Services\RaterWeights\RaterWeightGenerator;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use App\Models\AssessmentPeriod;
use Carbon\Carbon;
use Database\Seeders\NovemberRaterWeightSeeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        DB::transaction(function () {
            $now = Carbon::now();

            // =========================================================
            // 1) UNITS
            // =========================================================
            DB::table('units')->updateOrInsert(
                ['slug' => 'manajemen-rumah-sakit'],
                [
                    'name' => 'Manajemen Rumah Sakit',
                    'code' => 'MNG',
                    'type' => 'manajemen',
                    'parent_id' => null,
                    'location' => 'Kantor Direksi',
                    'phone' => null,
                    'email' => null,
                    'is_active' => 1,
                    'updated_at' => $now,
                    'created_at' => $now,
                ]
            );
            $managementId = (int) (DB::table('units')->where('slug', 'manajemen-rumah-sakit')->value('id') ?? 0);

            $units = [
                // Poliklinik (sesuai daftar pada lampiran)
                ['name' => 'Poliklinik Umum', 'slug' => 'poliklinik-umum', 'code' => 'POL-UM', 'type' => 'poliklinik', 'parent_id' => null, 'location' => 'Gedung Poli', 'phone' => null, 'email' => null],
                ['name' => 'Poliklinik Gigi & Mulut', 'slug' => 'poliklinik-gigi', 'code' => 'POL-GG', 'type' => 'poliklinik', 'parent_id' => null, 'location' => 'Gedung Poli', 'phone' => null, 'email' => null],
                ['name' => 'Poli KIA/KB', 'slug' => 'poli-kia', 'code' => 'POL-KIA', 'type' => 'poliklinik', 'parent_id' => null, 'location' => 'Gedung Poli', 'phone' => null, 'email' => null],
                ['name' => 'Poli Anak', 'slug' => 'poli-anak', 'code' => 'POL-AN', 'type' => 'poliklinik', 'parent_id' => null, 'location' => 'Gedung Poli', 'phone' => null, 'email' => null],
                ['name' => 'Poli Bedah', 'slug' => 'poli-bedah', 'code' => 'POL-BD', 'type' => 'poliklinik', 'parent_id' => null, 'location' => 'Gedung Poli', 'phone' => null, 'email' => null],
                ['name' => 'Poli Interna', 'slug' => 'poli-interna', 'code' => 'POL-INT', 'type' => 'poliklinik', 'parent_id' => null, 'location' => 'Gedung Poli', 'phone' => null, 'email' => null],
                ['name' => 'Poli Fisioterapi', 'slug' => 'poli-fisioterapi', 'code' => 'POL-FIS', 'type' => 'poliklinik', 'parent_id' => null, 'location' => 'Gedung Poli', 'phone' => null, 'email' => null],
                ['name' => 'Poli Hemodialisa', 'slug' => 'poli-hemodialisa', 'code' => 'POL-HEM', 'type' => 'poliklinik', 'parent_id' => null, 'location' => 'Gedung Poli', 'phone' => null, 'email' => null],
            ];
            foreach ($units as &$u) {
                $u['is_active'] = 1;
                $u['created_at'] = $now;
                $u['updated_at'] = $now;
            }
            DB::table('units')->upsert(
                $units,
                ['slug'],
                ['name','code','type','parent_id','location','phone','email','is_active','updated_at']
            );

            $unitId = fn(string $slug) => DB::table('units')->where('slug', $slug)->value('id');

            // =========================================================
            // 2) PROFESSIONS
            // =========================================================
            $professions = [
                ['name' => 'Dokter Umum', 'code' => 'DOK-UM', 'description' => 'Dokter layanan primer'],
                ['name' => 'Dokter Spesialis', 'code' => 'DOK-SP', 'description' => 'Dokter spesialis'],
                ['name' => 'Perawat', 'code' => 'PRW', 'description' => 'Perawat'],
            ];
            foreach ($professions as &$p) {
                $p['is_active'] = 1;
                $p['created_at'] = $now;
                $p['updated_at'] = $now;
            }
            DB::table('professions')->upsert(
                $professions,
                ['code'],
                ['name', 'description', 'is_active', 'updated_at']
            );

            $professionId = fn(string $code) => DB::table('professions')->where('code', $code)->value('id');

            // =========================================================
            // 3) USERS
            // =========================================================
            $users = [
                [
                    'employee_number' => '00.0001',
                    'name' => 'Super Admin',
                    'start_date' => '2020-01-01',
                    'gender' => 'Laki-laki',
                    'nationality' => 'Indonesia',
                    'address' => 'Atambua',
                    'phone' => '0800-0000-0001',
                    'email' => 'superadmin@rsud.local',
                    'last_education' => 'S1',
                    'position' => 'Super Admin',
                    'unit_id' => $managementId ?: null,
                    'profession_id' => null,
                    'password' => Hash::make('password'),
                    'last_role' => 'super_admin',
                    'email_verified_at' => $now,
                    'remember_token' => Str::random(10),
                    'created_at' => $now,
                    'updated_at' => $now,
                ],
                [
                    'employee_number' => '00.0002',
                    'name' => 'Admin RS',
                    'start_date' => '2020-01-01',
                    'gender' => 'Laki-laki',
                    'nationality' => 'Indonesia',
                    'address' => 'Atambua',
                    'phone' => '0800-0000-0002',
                    'email' => 'admin.rs@rsud.local',
                    'last_education' => 'S1',
                    'position' => 'Admin RS',
                    'unit_id' => $managementId ?: null,
                    'profession_id' => null,
                    'password' => Hash::make('password'),
                    'last_role' => 'admin_rs',
                    'email_verified_at' => $now,
                    'remember_token' => Str::random(10),
                    'created_at' => $now,
                    'updated_at' => $now,
                ],
                [
                    'employee_number' => '00.0004',
                    'name' => 'Kepala Poliklinik',
                    'start_date' => '2020-01-01',
                    'gender' => 'Laki-laki',
                    'nationality' => 'Indonesia',
                    'address' => 'Atambua',
                    'phone' => '0800-0000-0004',
                    'email' => 'kepala.poliklinik@rsud.local',
                    'last_education' => 'S1',
                    'position' => 'Kepala Poliklinik',
                    'unit_id' => $managementId ?: null,
                    'profession_id' => null,
                    'password' => Hash::make('password'),
                    'last_role' => 'kepala_poliklinik',
                    'email_verified_at' => $now,
                    'remember_token' => Str::random(10),
                    'created_at' => $now,
                    'updated_at' => $now,
                ],
                [
                    'employee_number' => '10.0001',
                    'name' => 'dr. Felix Christian Tjiptadi',
                    'start_date' => '2022-01-15',
                    'gender' => 'Laki-laki',
                    'nationality' => 'Indonesia',
                    'address' => 'Atambua',
                    'phone' => '0816-7777-7777',
                    'email' => 'kepala.umum@rsud.local',
                    'last_education' => 'Sp.',
                    'position' => 'Kepala Unit / Dokter',
                    'unit_id' => $unitId('poliklinik-umum'),
                    'profession_id' => $professionId('DOK-UM'),
                    'password' => Hash::make('password'),
                    'last_role' => 'kepala_unit',
                    'email_verified_at' => $now,
                    'remember_token' => Str::random(10),
                    'created_at' => $now,
                    'updated_at' => $now,
                ],
                [
                    'employee_number' => '10.0002',
                    'name' => 'dr. Theodorus L. Mau bere',
                    'start_date' => '2021-02-10',
                    'gender' => 'Laki-laki',
                    'nationality' => 'Indonesia',
                    'address' => 'Atambua',
                    'phone' => '0814-4444-4444',
                    'email' => 'dokter.umum1@rsud.local',
                    'last_education' => 'S.Ked',
                    'position' => 'Dokter Umum',
                    'unit_id' => $unitId('poliklinik-umum'),
                    'profession_id' => $professionId('DOK-UM'),
                    'password' => Hash::make('password'),
                    'last_role' => 'pegawai_medis',
                    'email_verified_at' => $now,
                    'remember_token' => Str::random(10),
                    'created_at' => $now,
                    'updated_at' => $now,
                ],
                [
                    'employee_number' => '10.0003',
                    'name' => 'dr. Charles Saputra',
                    'start_date' => '2023-01-10',
                    'gender' => 'Laki-laki',
                    'nationality' => 'Indonesia',
                    'address' => 'Atambua',
                    'phone' => '0812-0000-0017',
                    'email' => 'dokter.umum2@rsud.local',
                    'last_education' => 'S.Ked',
                    'position' => 'Dokter Umum',
                    'unit_id' => $unitId('poliklinik-umum'),
                    'profession_id' => $professionId('DOK-UM'),
                    'password' => Hash::make('password'),
                    'last_role' => 'pegawai_medis',
                    'email_verified_at' => $now,
                    'remember_token' => Str::random(10),
                    'created_at' => $now,
                    'updated_at' => $now,
                ],
                [
                    'employee_number' => '10.0004',
                    'name' => 'Fransisca Tjitra N. Seran, SE',
                    'start_date' => '2018-11-05',
                    'gender' => 'Perempuan',
                    'nationality' => 'Indonesia',
                    'address' => 'Atambua',
                    'phone' => '0815-5555-5555',
                    'email' => 'perawat1@rsud.local',
                    'last_education' => 'D3 Keperawatan',
                    'position' => 'Perawat Poli Umum',
                    'unit_id' => $unitId('poliklinik-umum'),
                    'profession_id' => $professionId('PRW'),
                    'password' => Hash::make('password'),
                    'last_role' => 'pegawai_medis',
                    'email_verified_at' => $now,
                    'remember_token' => Str::random(10),
                    'created_at' => $now,
                    'updated_at' => $now,
                ],
                [
                    'employee_number' => '10.0005',
                    'name' => 'Maria Magdalena Seran',
                    'start_date' => '2010-01-01',
                    'gender' => 'Perempuan',
                    'nationality' => 'Indonesia',
                    'address' => 'Atambua',
                    'phone' => '0812-0000-0200',
                    'email' => 'perawat2@rsud.local',
                    'last_education' => 'D3 Keperawatan',
                    'position' => 'Perawat Poli Umum',
                    'unit_id' => $unitId('poliklinik-umum'),
                    'profession_id' => $professionId('PRW'),
                    'password' => Hash::make('password'),
                    'last_role' => 'pegawai_medis',
                    'email_verified_at' => $now,
                    'remember_token' => Str::random(10),
                    'created_at' => $now,
                    'updated_at' => $now,
                ],
                [
                    'employee_number' => '197909102008032001',
                    'name' => 'dr Melriawati Gunawan',
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
                    'last_role' => 'kepala_unit',
                    'email_verified_at' => $now,
                    'remember_token' => Str::random(10),
                    'created_at' => $now,
                    'updated_at' => $now,
                ],
                [
                    'employee_number' => '10.0006',
                    'name' => 'dr. Januario E. Bria M.Kes, Sp.B',
                    'start_date' => '2021-05-01',
                    'gender' => 'Laki-laki',
                    'nationality' => 'Indonesia',
                    'address' => 'Atambua',
                    'phone' => '0812-0000-0161',
                    'email' => 'dokter.spes1@rsud.local',
                    'last_education' => 'Sp.B',
                    'position' => 'Dokter Spesialis',
                    'unit_id' => $unitId('poliklinik-gigi'),
                    'profession_id' => $professionId('DOK-SP'),
                    'password' => Hash::make('password'),
                    'last_role' => 'pegawai_medis',
                    'email_verified_at' => $now,
                    'remember_token' => Str::random(10),
                    'created_at' => $now,
                    'updated_at' => $now,
                ],
                [
                    'employee_number' => '10.0007',
                    'name' => 'drg. Susanti S. Leo',
                    'start_date' => '2022-02-02',
                    'gender' => 'Perempuan',
                    'nationality' => 'Indonesia',
                    'address' => 'Atambua',
                    'phone' => '0812-0000-0888',
                    'email' => 'dokter.spes2@rsud.local',
                    'last_education' => 'drg.',
                    'position' => 'Dokter Spesialis Gigi',
                    'unit_id' => $unitId('poliklinik-gigi'),
                    'profession_id' => $professionId('DOK-SP'),
                    'password' => Hash::make('password'),
                    'last_role' => 'pegawai_medis',
                    'email_verified_at' => $now,
                    'remember_token' => Str::random(10),
                    'created_at' => $now,
                    'updated_at' => $now,
                ],
            ];

            DB::table('users')->upsert(
                $users,
                ['email'],
                [
                    'employee_number',
                    'name',
                    'start_date',
                    'gender',
                    'nationality',
                    'address',
                    'phone',
                    'last_education',
                    'position',
                    'unit_id',
                    'profession_id',
                    'last_role',
                    'updated_at',
                ]
            );

            // 3b) ROLES & ASSIGNMENTS (many-to-many)
            $roles = [
                ['slug' => 'super_admin', 'name' => 'Super Admin'],
                ['slug' => 'admin_rs', 'name' => 'Admin RS'],
                ['slug' => 'kepala_poliklinik', 'name' => 'Kepala Poliklinik'],
                ['slug' => 'kepala_unit', 'name' => 'Kepala Unit'],
                ['slug' => 'pegawai_medis', 'name' => 'Pegawai Medis'],
            ];
            foreach ($roles as &$r) {
                $r['created_at'] = $now;
                $r['updated_at'] = $now;
            }
            DB::table('roles')->upsert(
                $roles,
                ['slug'],
                ['name', 'updated_at']
            );

            $userId = fn(string $email) => DB::table('users')->where('email', $email)->value('id');

            // helper ids (8 akun demo mudah)
            $unitHeadUmumId = $userId('kepala.umum@rsud.local');
            $doctorUmum1Id = $userId('dokter.umum1@rsud.local');
            $doctorUmum2Id = $userId('dokter.umum2@rsud.local');
            $nurse1Id = $userId('perawat1@rsud.local');
            $nurse2Id = $userId('perawat2@rsud.local');
            $unitHeadGigiId = $userId('kepala.gigi@rsud.local');
            $doctorSpes1Id = $userId('dokter.spes1@rsud.local');
            $doctorSpes2Id = $userId('dokter.spes2@rsud.local');

            // helper ids (admin)
            $superAdminId = $userId('superadmin@rsud.local');
            $adminRsId = $userId('admin.rs@rsud.local');

            // Guardrail: fail fast if any required seeded users are missing.
            $requiredUserIds = [
                'superadmin@rsud.local' => $superAdminId,
                'admin.rs@rsud.local' => $adminRsId,
                'kepala.poliklinik@rsud.local' => $userId('kepala.poliklinik@rsud.local'),
                'kepala.umum@rsud.local' => $unitHeadUmumId,
                'dokter.umum1@rsud.local' => $doctorUmum1Id,
                'dokter.umum2@rsud.local' => $doctorUmum2Id,
                'perawat1@rsud.local' => $nurse1Id,
                'perawat2@rsud.local' => $nurse2Id,
                'kepala.gigi@rsud.local' => $unitHeadGigiId,
                'dokter.spes1@rsud.local' => $doctorSpes1Id,
                'dokter.spes2@rsud.local' => $doctorSpes2Id,
            ];
            $missingUsers = array_keys(array_filter($requiredUserIds, fn($id) => !$id));
            if (!empty($missingUsers)) {
                throw new \RuntimeException('DatabaseSeeder: user tidak ditemukan: ' . implode(', ', $missingUsers));
            }

            // yang baru:
            $polyclinicHeadId = $userId('kepala.poliklinik@rsud.local');

            // Aliases for readability (dipakai di bawah)
            $doctorId = $doctorUmum1Id;
            $nurseId = $nurse1Id;
            $medisUnitHeadId = $unitHeadUmumId;

            $poliklinikUmumId = $unitId('poliklinik-umum');
            $poliklinikGigiId = $unitId('poliklinik-gigi');

            // Assign roles to users via pivot (single role each, kecuali beberapa user dual head+medis)
            $roleId = fn(string $slug) => DB::table('roles')->where('slug', $slug)->value('id');
            $attach = function ($uid, array $slugs) use ($roleId) {
                foreach ($slugs as $slug) {
                    DB::table('role_user')->updateOrInsert([
                        'user_id' => $uid,
                        'role_id' => $roleId($slug),
                    ], []);
                }
            };

            // Bersihkan pivot lama (jika seeder dijalankan ulang tanpa migrate:fresh)
            $uids = array_filter([
                $superAdminId,
                $polyclinicHeadId,
                $unitHeadGigiId,
                $adminRsId,
                $unitHeadUmumId,
                $doctorUmum1Id,
                $doctorUmum2Id,
                $nurse1Id,
                $nurse2Id,
                $doctorSpes1Id,
                $doctorSpes2Id,
            ]);
            if (!empty($uids)) {
                DB::table('role_user')->whereIn('user_id', $uids)->delete();
            }

            // Single-role assignments
            $attach($superAdminId, ['super_admin']);
            $attach($polyclinicHeadId, ['kepala_poliklinik']);
            $attach($adminRsId, ['admin_rs']);
            $attach($doctorUmum1Id, ['pegawai_medis']);
            $attach($doctorUmum2Id, ['pegawai_medis']);
            $attach($nurse1Id, ['pegawai_medis']);
            $attach($nurse2Id, ['pegawai_medis']);
            $attach($doctorSpes1Id, ['pegawai_medis']);
            $attach($doctorSpes2Id, ['pegawai_medis']);

            // Dual roles: kepala_unit + pegawai_medis
            if ($unitHeadGigiId) {
                $attach($unitHeadGigiId, ['kepala_unit', 'pegawai_medis']);
            }
            if ($medisUnitHeadId) {
                $attach($medisUnitHeadId, ['kepala_unit', 'pegawai_medis']);
            }

            // Pastikan last_role tidak diubah ke role lain secara otomatis
            // Super Admin tetap 'super_admin' (sudah diset di array insert)


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
            DB::table('about_pages')->upsert(
                $about,
                ['type'],
                ['title','content','image_path','attachments','published_at','is_active','updated_at']
            );

            // =========================================================
            // 6) ASSESSMENT PERIODS
            // =========================================================
            // Periode bulanan (sesuai kebutuhan remunerasi per-bulan)
            $periods = [
                [
                    'name' => 'September 2025',
                    'start_date' => '2025-09-01',
                    'end_date' => '2025-09-30',
                    'status' => AssessmentPeriod::STATUS_CLOSED,
                    'locked_at' => null,
                    'created_at' => $now,
                    'updated_at' => $now,
                ],
                [
                    'name' => 'Oktober 2025',
                    'start_date' => '2025-10-01',
                    'end_date' => '2025-10-31',
                    'status' => AssessmentPeriod::STATUS_CLOSED,
                    'locked_at' => null,
                    'created_at' => $now,
                    'updated_at' => $now,
                ],
                [
                    'name' => 'November 2025',
                    'start_date' => '2025-11-01',
                    'end_date' => '2025-11-30',
                    'status' => AssessmentPeriod::STATUS_CLOSED,
                    'locked_at' => null,
                    'created_at' => $now,
                    'updated_at' => $now,
                ],
            ];
            DB::table('assessment_periods')->upsert(
                $periods,
                ['name'],
                ['start_date','end_date','status','locked_at','updated_at']
            );
            $periodSeptId = DB::table('assessment_periods')->where('name', 'September 2025')->value('id');
            $periodOctId = DB::table('assessment_periods')->where('name', 'Oktober 2025')->value('id');
            $periodNovId = DB::table('assessment_periods')->where('name', 'November 2025')->value('id');

            // =========================================================
            // Requirement: purge ALL October performance assessments
            // =========================================================
            // Hapus dari performance_assessments (detail/approvals cascade) + remunerations.
            if (!empty($periodOctId)) {
                DB::table('performance_assessments')->where('assessment_period_id', $periodOctId)->delete();

                if (Schema::hasTable('remunerations')) {
                    DB::table('remunerations')->where('assessment_period_id', $periodOctId)->delete();
                }
            }

            // =========================================================
            // 7) PERFORMANCE CRITERIAS
            // =========================================================
            // Safety: if DB already has legacy "Absensi", rename it in-place to keep FK references valid.
            DB::table('performance_criterias')
                ->where('name', 'Absensi')
                ->update([
                    'name' => 'Kehadiran (Absensi)',
                    'type' => 'benefit',
                    'data_type' => 'numeric',
                    'input_method' => 'system',
                    ...(Schema::hasColumn('performance_criterias', 'source') ? ['source' => 'system'] : []),
                    'is_360' => 0,
                    'aggregation_method' => 'count',
                    // Excel: hari_hadir / total_hari_periode × 100
                    // Implementasi DB: gunakan custom_target; jika custom_target_value null,
                    // engine baru akan pakai total hari periode sebagai target dinamis.
                    'normalization_basis' => 'custom_target',
                    'custom_target_value' => null,
                    'suggested_weight' => 20,
                    'description' => "Kode: KEHADIRAN\nNama: Kehadiran (Absensi)\nRumus Excel: hari_hadir / total_hari_periode × 100\nBasis: periode_days (dinamis dari AssessmentPeriod).",
                    'is_active' => 1,
                    'updated_at' => $now,
                ]);

            $criterias = [
                // Excel mapping (source-of-truth)
                ['name' => 'Kehadiran (Absensi)', 'type' => 'benefit', 'data_type' => 'numeric', 'input_method' => 'system', 'source' => 'system', 'is_360' => 0, 'aggregation_method' => 'count', 'normalization_basis' => 'custom_target', 'custom_target_value' => null, 'suggested_weight' => 20, 'description' => "Kode: KEHADIRAN\nRumus Excel: hari_hadir / total_hari_periode × 100\nBasis: periode_days", 'is_active' => 1],
                ['name' => 'Jam Kerja (Absensi)', 'type' => 'benefit', 'data_type' => 'numeric', 'input_method' => 'system', 'source' => 'system', 'is_360' => 0, 'aggregation_method' => 'sum', 'normalization_basis' => 'total_unit', 'custom_target_value' => null, 'suggested_weight' => 10, 'description' => "Kode: JAM_KERJA\nRumus Excel: menit_kerja / total_menit_grup × 100\nBasis: total_unit", 'is_active' => 1],
                ['name' => 'Lembur (Absensi)', 'type' => 'benefit', 'data_type' => 'numeric', 'input_method' => 'system', 'source' => 'system', 'is_360' => 0, 'aggregation_method' => 'count', 'normalization_basis' => 'total_unit', 'custom_target_value' => null, 'suggested_weight' => 5, 'description' => "Kode: LEMBUR\nRumus Excel: jumlah_lembur / total_lembur_grup × 100\nBasis: total_unit", 'is_active' => 1],
                ['name' => 'Keterlambatan (Absensi)', 'type' => 'cost', 'data_type' => 'numeric', 'input_method' => 'system', 'source' => 'system', 'is_360' => 0, 'aggregation_method' => 'sum', 'normalization_basis' => 'max_unit', 'custom_target_value' => null, 'suggested_weight' => 10, 'description' => "Kode: KETERLAMBATAN\nRumus Excel: (1 - menit_terlambat / max) × 100\nBasis: max_unit", 'is_active' => 1],
                ['name' => 'Kedisiplinan (360)', 'type' => 'benefit', 'data_type' => 'numeric', 'input_method' => '360', 'source' => 'assessment_360', 'is_360' => 1, 'aggregation_method' => 'avg', 'normalization_basis' => 'total_unit', 'custom_target_value' => null, 'suggested_weight' => 15, 'description' => "Kode: KEDISIPLINAN_360\nRumus Excel: skor / total_skor_grup × 100\nBasis: total_unit", 'is_active' => 1],
                ['name' => 'Kerjasama (360)', 'type' => 'benefit', 'data_type' => 'numeric', 'input_method' => '360', 'source' => 'assessment_360', 'is_360' => 1, 'aggregation_method' => 'avg', 'normalization_basis' => 'total_unit', 'custom_target_value' => null, 'suggested_weight' => 10, 'description' => "Kode: KERJASAMA_360\nRumus Excel: skor / total_skor_grup × 100\nBasis: total_unit", 'is_active' => 1],
                ['name' => 'Kontribusi Tambahan', 'type' => 'benefit', 'data_type' => 'numeric', 'input_method' => 'system', 'source' => 'system', 'is_360' => 0, 'aggregation_method' => 'sum', 'normalization_basis' => 'total_unit', 'custom_target_value' => null, 'suggested_weight' => 10, 'description' => "Kode: KONTRIBUSI\nRumus Excel: poin / total_poin_grup × 100\nBasis: total_unit", 'is_active' => 1],
                ['name' => 'Jumlah Pasien Ditangani', 'type' => 'benefit', 'data_type' => 'numeric', 'input_method' => 'import', 'source' => 'metric_import', 'is_360' => 0, 'aggregation_method' => 'sum', 'normalization_basis' => 'total_unit', 'custom_target_value' => null, 'suggested_weight' => 5, 'description' => "Kode: PASIEN\nRumus Excel: pasien / total_pasien_grup × 100\nBasis: total_unit", 'is_active' => 1],
                ['name' => 'Jumlah Komplain Pasien', 'type' => 'cost', 'data_type' => 'numeric', 'input_method' => 'import', 'source' => 'metric_import', 'is_360' => 0, 'aggregation_method' => 'sum', 'normalization_basis' => 'max_unit', 'custom_target_value' => null, 'suggested_weight' => 3, 'description' => "Kode: KOMPLAIN\nRumus Excel: (1 - komplain / max) × 100\nBasis: max_unit", 'is_active' => 1],
                ['name' => 'Rating', 'type' => 'benefit', 'data_type' => 'numeric', 'input_method' => 'public_review', 'source' => 'system', 'is_360' => 0, 'aggregation_method' => 'avg', 'normalization_basis' => 'custom_target', 'custom_target_value' => 5, 'suggested_weight' => 12, 'description' => "Kode: RATING\nRumus Excel: rating / 5 × 100\nBasis: max_rating (custom_target=5)", 'is_active' => 1],
            ];
            foreach ($criterias as &$k) {
                $k['created_at'] = $now;
                $k['updated_at'] = $now;
            }

            // Upsert by name to avoid duplicates and keep FK stable.
            $upsertRows = $criterias;
            if (!Schema::hasColumn('performance_criterias', 'source')) {
                foreach ($upsertRows as &$r) {
                    unset($r['source']);
                }
                unset($r);
            }

            $updateCols = [
                'type',
                'description',
                'is_active',
                'data_type',
                'input_method',
                'is_360',
                'aggregation_method',
                'normalization_basis',
                'custom_target_value',
                'suggested_weight',
                'updated_at',
            ];
            if (Schema::hasColumn('performance_criterias', 'source')) {
                $updateCols[] = 'source';
            }

            DB::table('performance_criterias')->upsert(
                $upsertRows,
                ['name'],
                $updateCols
            );

            // NOTE:
            // rater_weights is now per (period + unit + 360-criteria + profession + assessor_type)
            // and is auto-generated from unit_criteria_weights + criteria_rater_rules.
            // We intentionally do NOT seed rater_weights here to avoid conflicting with the new workflow.

            $criteriaId = fn(string $name) => DB::table('performance_criterias')->where('name', $name)->value('id');

            // =========================================================
            // 7b) CRITERIA RATER RULES (untuk kriteria 360)
            // =========================================================
            // NOTE: Seeded via CriteriaRaterRuleSeeder (keeps the rules matrix consistent).

            // =========================================================
            // 8) UNIT CRITERIA WEIGHTS (contoh Poli Umum)
            // =========================================================
            // DB::table('unit_criteria_weights')->insert([
            //     [
            //         'unit_id' => $poliklinikUmumId,
            //         'performance_criteria_id' => $criteriaId('Kedisiplinan'),
            //         'weight' => 40.00,
            //         'created_at' => $now,
            //         'updated_at' => $now
            //     ],
            //     [
            //         'unit_id' => $poliklinikUmumId,
            //         'performance_criteria_id' => $criteriaId('Pelayanan Pasien'),
            //         'weight' => 40.00,
            //         'created_at' => $now,
            //         'updated_at' => $now
            //     ],
            //     [
            //         'unit_id' => $poliklinikUmumId,
            //         'performance_criteria_id' => $criteriaId('Kepatuhan Prosedur'),
            //         'weight' => 20.00,
            //         'created_at' => $now,
            //         'updated_at' => $now
            //     ],
            // ]);

            // =========================================================
            // 9) ANNOUNCEMENTS
            // =========================================================
            DB::table('announcements')->updateOrInsert(
                ['slug' => 'selamat-datang'],
                [
                    'title' => 'Selamat datang di Sistem Informasi RSUD GM Atambua',
                    'summary' => 'Portal internal untuk kinerja, remunerasi, dan layanan klinik.',
                    'content' => '<p>Silakan gunakan menu di atas untuk mengakses modul-modul yang tersedia.</p>',
                    'category' => 'lainnya',
                    'label' => 'info',
                    'is_featured' => 1,
                    'published_at' => $now,
                    'expired_at' => null,
                    'attachments' => json_encode([]),
                    'author_id' => $superAdminId,
                    'updated_at' => $now,
                    'created_at' => $now,
                ]
            );

            // =========================================================
            // 10) FAQS (dengan user_id)
            // =========================================================
            DB::table('faqs')->insert([
                [
                    'question' => 'Jam operasional IGD?',
                    'answer' => 'IGD melayani 24 jam, 7 hari seminggu.',
                    'order' => 1,
                    'is_active' => 1,
                    'category' => 'layanan',
                    'user_id' => $superAdminId,
                    'created_at' => $now,
                    'updated_at' => $now
                ],
                [
                    'question' => 'Cara ambil nomor antrian poli?',
                    'answer' => 'Datang ke loket pendaftaran atau gunakan aplikasi internal bila tersedia.',
                    'order' => 2,
                    'is_active' => 1,
                    'category' => 'antrian',
                    'user_id' => $superAdminId,
                    'created_at' => $now,
                    'updated_at' => $now
                ],
                [
                    'question' => 'Kontak RS?',
                    'answer' => 'Telepon (0389)2513137, Email rsudatambua66@gmail.com.',
                    'order' => 3,
                    'is_active' => 1,
                    'category' => 'kontak',
                    'user_id' => $superAdminId,
                    'created_at' => $now,
                    'updated_at' => $now
                ],
            ]);

            // // =========================================================
            // // 11) ATTENDANCE IMPORT BATCH + ATTENDANCES
            // // =========================================================
            

            // =========================================================
            // 12) ADDITIONAL CONTRIBUTIONS
            // =========================================================
            // NOTE: demo data untuk Oktober sengaja tidak di-seed.

            // =========================================================
            // 13) PERFORMANCE ASSESSMENTS
            // =========================================================
            // NOTE: demo data untuk Oktober sengaja tidak di-seed.

            // =========================================================
            // 14) PERFORMANCE ASSESSMENT DETAILS
            // =========================================================
            // NOTE: demo data untuk Oktober sengaja tidak di-seed.


            // =========================================================
            // 17) REVIEWS (tanpa relasi visit)
            // =========================================================
            $reg1 = 'REG-' . date('Ymd') . '-0001';
            DB::table('reviews')->updateOrInsert(
                ['registration_ref' => $reg1],
                [
                    'unit_id' => $poliklinikUmumId,
                    'overall_rating' => 5,
                    'comment' => 'Pelayanan cepat dan ramah.',
                    'patient_name' => 'Bapak A',
                    'contact' => '08xxxxxxxxxx',
                    'client_ip' => '127.0.0.1',
                    'user_agent' => 'Seeder',
                    'status' => 'approved',
                    'decision_note' => 'Data contoh disetujui otomatis.',
                    'decided_by' => $medisUnitHeadId,
                    'decided_at' => $now,
                    'updated_at' => $now,
                    'created_at' => $now,
                ]
            );
            $reviewId = (int) (DB::table('reviews')->where('registration_ref', $reg1)->value('id') ?? 0);

            $reg2 = 'REG-' . date('Ymd') . '-0002';
            DB::table('reviews')->updateOrInsert(
                ['registration_ref' => $reg2],
                [
                    'unit_id' => $poliklinikUmumId,
                    'overall_rating' => 4,
                    'comment' => 'Dokter informatif, proses cepat.',
                    'patient_name' => 'Ibu B',
                    'contact' => '08xxxxxxxxxx',
                    'client_ip' => '127.0.0.1',
                    'user_agent' => 'Seeder',
                    'status' => 'approved',
                    'decision_note' => 'Data contoh disetujui otomatis.',
                    'decided_by' => $medisUnitHeadId,
                    'decided_at' => $now,
                    'updated_at' => $now,
                    'created_at' => $now,
                ]
            );
            $reviewId2 = (int) (DB::table('reviews')->where('registration_ref', $reg2)->value('id') ?? 0);

            // =========================================================
            // 18) REVIEW DETAILS (per nakes)
            // =========================================================
            DB::table('review_details')->whereIn('review_id', array_values(array_filter([$reviewId, $reviewId2])))->delete();
            DB::table('review_details')->insert([
                [
                    'review_id' => $reviewId,
                    'medical_staff_id' => $doctorId,
                    'role' => 'dokter',
                    'rating' => 5,
                    'comment' => 'Dokter komunikatif.',
                    'created_at' => $now,
                    'updated_at' => $now,
                ],
                [
                    'review_id' => $reviewId,
                    'medical_staff_id' => $nurseId,
                    'role' => 'perawat',
                    'rating' => 5,
                    'comment' => 'Perawat membantu dengan sigap.',
                    'created_at' => $now,
                    'updated_at' => $now,
                ],
                [
                    'review_id' => $reviewId2,
                    'medical_staff_id' => $doctorId,
                    'role' => 'dokter',
                    'rating' => 4,
                    'comment' => 'Penjelasan jelas.',
                    'created_at' => $now,
                    'updated_at' => $now,
                ],
            ]);
        });

        $this->seedProfessionHierarchy();
        $this->seedCriteriaRaterRules();
        $this->call(EightStaffKpiSeeder::class);
        $this->call(NovemberRaterWeightSeeder::class);
    }

    private function seedCriteriaRaterRules(): void
    {
        if (!Schema::hasTable('criteria_rater_rules') || !Schema::hasTable('performance_criterias')) {
            return;
        }

        $now = now();

        $criteriaId = fn (string $name) => (int) (DB::table('performance_criterias')->where('name', $name)->value('id') ?? 0);

        $kedisId = $criteriaId('Kedisiplinan (360)');
        $kerjaId = $criteriaId('Kerjasama (360)');

        $matrix = [];

        // Matrix per requirement:
        // - Kedisiplinan (360): supervisor (utama), self (kecil) => allowed types: supervisor, self
        // - Kerjasama (360): peer (utama), supervisor/subordinate/self (kecil) => allowed types: supervisor, peer, subordinate, self
        if ($kedisId > 0) {
            $matrix[$kedisId] = ['supervisor', 'self'];
        }
        if ($kerjaId > 0) {
            $matrix[$kerjaId] = ['supervisor', 'peer', 'subordinate', 'self'];
        }

        if (empty($matrix)) {
            return;
        }

        DB::transaction(function () use ($matrix, $now) {
            foreach ($matrix as $pcId => $allowedTypes) {
                $allowedTypes = array_values(array_unique(array_values(array_filter($allowedTypes))));

                if (empty($allowedTypes)) {
                    continue;
                }

                DB::table('criteria_rater_rules')
                    ->where('performance_criteria_id', (int) $pcId)
                    ->whereNotIn('assessor_type', $allowedTypes)
                    ->delete();

                $rows = [];
                foreach ($allowedTypes as $type) {
                    $rows[] = [
                        'performance_criteria_id' => (int) $pcId,
                        'assessor_type' => (string) $type,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ];
                }

                DB::table('criteria_rater_rules')->upsert(
                    $rows,
                    ['performance_criteria_id', 'assessor_type'],
                    ['updated_at']
                );
            }
        });
    }

    private function seedProfessionHierarchy(): void
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