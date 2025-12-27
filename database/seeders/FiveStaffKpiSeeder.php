<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Carbon\Carbon;

class FiveStaffKpiSeeder extends Seeder
{
    public function run(): void
    {
        $now = Carbon::now();

        $periodOct = DB::table('assessment_periods')->where('name', 'Oktober 2025')->first();
        $periodNov = DB::table('assessment_periods')->where('name', 'November 2025')->first();
        if (!$periodOct || !$periodNov) {
            return;
        }

        $unitPoliUmumId = (int) (DB::table('units')->where('slug', 'poliklinik-umum')->value('id') ?? 0);

        // Helpers
        $criteriaId = fn(string $name) => DB::table('performance_criterias')->where('name', $name)->value('id');
        $userId = fn(string $email) => DB::table('users')->where('email', $email)->value('id');
        $professionId = fn(string $code) => DB::table('professions')->where('code', $code)->value('id');
        $unitId = fn(string $slug) => DB::table('units')->where('slug', $slug)->value('id');

        // Ensure we have >= 3 staff in the SAME unit+profession (poliklinik-umum + DOK-UM)
        $ensurePegawaiMedis = function (string $email, string $name, string $employeeNumber, string $unitSlug, string $professionCode) use ($now, $userId, $unitId, $professionId) {
            $existingId = $userId($email);
            if ($existingId) {
                return (int) $existingId;
            }

            $uId = (int) ($unitId($unitSlug) ?? 0);
            $pId = (int) ($professionId($professionCode) ?? 0);

            $newId = DB::table('users')->insertGetId([
                'employee_number' => $employeeNumber,
                'name' => $name,
                'start_date' => '2022-01-01',
                'gender' => 'Laki-laki',
                'nationality' => 'Indonesia',
                'address' => 'Atambua',
                'phone' => '0812-0000-9999',
                'email' => $email,
                'last_education' => 'S.Ked',
                'position' => 'Dokter Umum',
                'unit_id' => $uId ?: null,
                'profession_id' => $pId ?: null,
                'password' => Hash::make('password'),
                'last_role' => 'pegawai_medis',
                'email_verified_at' => $now,
                'remember_token' => Str::random(10),
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            $roleId = (int) (DB::table('roles')->where('slug', 'pegawai_medis')->value('id') ?? 0);
            if ($roleId > 0) {
                DB::table('role_user')->updateOrInsert([
                    'user_id' => (int) $newId,
                    'role_id' => $roleId,
                ], []);
            }

            return (int) $newId;
        };

        $dokterUmum3Id = $ensurePegawaiMedis(
            email: 'dokter.umum3@rsud.local',
            name: 'dr. Raka Pratama',
            employeeNumber: '197001012025123001',
            unitSlug: 'poliklinik-umum',
            professionCode: 'DOK-UM'
        );

        $absensiId    = $criteriaId('Absensi');
        $kedis360Id   = $criteriaId('Kedisiplinan (360)');
        $kontribusiId = $criteriaId('Kontribusi Tambahan');
        $pasienId     = $criteriaId('Jumlah Pasien Ditangani');
        $ratingId     = $criteriaId('Rating');

        // Target users (5 pegawai)
        $staff = [
            'felix' => [
                'id' => $userId('kepala.unit.medis@rsud.local'),
                'unit_slug' => 'poliklinik-umum',
                'profession' => 'DOK-UM',
            ],
            'fransisca' => [
                'id' => $userId('perawat@rsud.local'),
                'unit_slug' => 'poliklinik-umum',
                'profession' => 'PRW',
            ],
            'theodorus' => [
                'id' => $userId('dokter.umum@rsud.local'),
                'unit_slug' => 'poliklinik-umum',
                'profession' => 'DOK-UM',
            ],
            'raka' => [
                'id' => $dokterUmum3Id,
                'unit_slug' => 'poliklinik-umum',
                'profession' => 'DOK-UM',
            ],
            'melria' => [
                'id' => $userId('kepala.gigi@rsud.local'),
                'unit_slug' => 'poliklinik-gigi',
                'profession' => 'DOK-UM',
            ],
            'janBeria' => [
                'id' => $userId('januario.bria@rsud.local'),
                'unit_slug' => 'poliklinik-gigi',
                'profession' => 'DOK-SP',
            ],
        ];

        $targets = array_column($staff, 'id');

        // Clean old related data for these users/periods
        $periodIds = [$periodOct->id, $periodNov->id];
        $oldAssessmentIds = DB::table('performance_assessments')
            ->whereIn('user_id', $targets)
            ->whereIn('assessment_period_id', $periodIds)
            ->pluck('id');

        DB::table('assessment_approvals')->whereIn('performance_assessment_id', $oldAssessmentIds)->delete();
        DB::table('performance_assessment_details')->whereIn('performance_assessment_id', $oldAssessmentIds)->delete();
        DB::table('performance_assessments')->whereIn('id', $oldAssessmentIds)->delete();
        DB::table('remunerations')->whereIn('user_id', $targets)->whereIn('assessment_period_id', $periodIds)->delete();

        DB::table('imported_criteria_values')->whereIn('user_id', $targets)->whereIn('assessment_period_id', $periodIds)->delete();
        DB::table('additional_contributions')->whereIn('user_id', $targets)->whereIn('assessment_period_id', $periodIds)->delete();
        $reviewIds = DB::table('review_details')->whereIn('medical_staff_id', $targets)->pluck('review_id');
        DB::table('review_details')->whereIn('medical_staff_id', $targets)->delete();
        DB::table('reviews')->whereIn('id', $reviewIds)->delete();
        $mraIds = DB::table('multi_rater_assessments')->whereIn('assessee_id', $targets)->whereIn('assessment_period_id', $periodIds)->pluck('id');
        DB::table('multi_rater_assessment_details')->whereIn('multi_rater_assessment_id', $mraIds)->delete();
        DB::table('multi_rater_assessments')->whereIn('id', $mraIds)->delete();
        DB::table('attendances')->whereIn('user_id', $targets)->whereIn('attendance_date', function ($q) use ($periodIds) {
            $q->select(DB::raw('attendance_date'));
        })->delete();

        // Dataset per periode: raw sumber (absensi = jumlah hadir, discipline = skor 360, contrib = poin, patients = jumlah, rating = avg 1-5)
        $octRaw = [
            'felix' => ['attendance' => 26, 'discipline' => 92, 'contrib' => 12, 'patients' => 230, 'rating' => 4.7],
            'fransisca' => ['attendance' => 25, 'discipline' => 83, 'contrib' => 9,  'patients' => 140, 'rating' => 4.5],
            'theodorus' => ['attendance' => 26, 'discipline' => 86, 'contrib' => 10, 'patients' => 175, 'rating' => 4.6],
            // Third DOK-UM in the same unit (poliklinik-umum) to demonstrate relative-score scaling (100 / ~87 / ~76)
            'raka' => ['attendance' => 24, 'discipline' => 88, 'contrib' => 11, 'patients' => 200, 'rating' => 4.8],
            'melria' => ['attendance' => 25, 'discipline' => 82, 'contrib' => 8,  'patients' => 120, 'rating' => 4.4],
            'janBeria' => ['attendance' => 25, 'discipline' => 80, 'contrib' => 7,  'patients' => 110, 'rating' => 4.3],
        ];
        $novRaw = [
            'felix' => ['attendance' => 25, 'discipline' => 87, 'contrib' => 10, 'patients' => 205, 'rating' => 4.6],
            'fransisca' => ['attendance' => 24, 'discipline' => 80, 'contrib' => 8,  'patients' => 135, 'rating' => 4.4],
            'theodorus' => ['attendance' => 24, 'discipline' => 82, 'contrib' => 9,  'patients' => 150, 'rating' => 4.5],
            'raka' => ['attendance' => 23, 'discipline' => 84, 'contrib' => 9,  'patients' => 165, 'rating' => 4.6],
            'melria' => ['attendance' => 23, 'discipline' => 78, 'contrib' => 6,  'patients' => 95,  'rating' => 4.2],
            'janBeria' => ['attendance' => 23, 'discipline' => 77, 'contrib' => 6,  'patients' => 90,  'rating' => 4.1],
        ];

        $allocations = [
            // periode, unit, profession, amount
            [$periodOct->id, 'poliklinik-umum', 'DOK-UM', 7800000],
            [$periodOct->id, 'poliklinik-umum', 'PRW',    4500000],
            [$periodOct->id, 'poliklinik-gigi', 'DOK-UM', 3200000],
            [$periodOct->id, 'poliklinik-gigi', 'DOK-SP', 4300000],
            [$periodNov->id, 'poliklinik-umum', 'DOK-UM', 7500000],
            [$periodNov->id, 'poliklinik-umum', 'PRW',    4200000],
            [$periodNov->id, 'poliklinik-gigi', 'DOK-UM', 3000000],
            [$periodNov->id, 'poliklinik-gigi', 'DOK-SP', 4000000],
        ];

        $this->seedPeriod(
            period: $periodOct,
            data: $octRaw,
            staff: $staff,
            criteriaIds: compact('absensiId','kedis360Id','kontribusiId','pasienId','ratingId'),
            validationStatus: 'Tervalidasi',
            approvalStatus: 'approved',
            assessmentDate: Carbon::create(2025, 10, 31),
            publishDate: Carbon::create(2025, 11, 5),
            paymentDate: Carbon::create(2025, 11, 10),
            allocations: $allocations,
            professionIdResolver: $professionId,
            unitIdResolver: $unitId,
            exampleInactiveUnitId: $unitPoliUmumId
        );

        $this->seedPeriod(
            period: $periodNov,
            data: $novRaw,
            staff: $staff,
            criteriaIds: compact('absensiId','kedis360Id','kontribusiId','pasienId','ratingId'),
            validationStatus: 'Menunggu Validasi',
            approvalStatus: 'pending',
            assessmentDate: Carbon::create(2025, 11, 30),
            publishDate: null,
            paymentDate: null,
            allocations: $allocations,
            professionIdResolver: $professionId,
            unitIdResolver: $unitId,
            exampleInactiveUnitId: null
        );
    }

    private function seedPeriod(
        object $period,
        array $data,
        array $staff,
        array $criteriaIds,
        string $validationStatus,
        string $approvalStatus,
        Carbon $assessmentDate,
        ?Carbon $publishDate,
        ?Carbon $paymentDate,
        array $allocations,
        callable $professionIdResolver,
        callable $unitIdResolver,
        ?int $exampleInactiveUnitId
    ): void {
        $now = Carbon::now();

        $absensiId    = $criteriaIds['absensiId'];
        $kedis360Id   = $criteriaIds['kedis360Id'];
        $kontribusiId = $criteriaIds['kontribusiId'];
        $pasienId     = $criteriaIds['pasienId'];
        $ratingId     = $criteriaIds['ratingId'];

        // Pastikan bobot aktif per unit & periode tersedia agar tampil di ringkasan WSM
        $unitIds = collect($staff)->pluck('unit_slug')->unique()->map(fn($slug) => $unitIdResolver($slug))->all();
        DB::table('unit_criteria_weights')
            ->where('assessment_period_id', $period->id)
            ->whereIn('unit_id', $unitIds)
            ->delete();

        $defaultWeights = [
            $absensiId => 20,
            $kedis360Id => 20,
            $kontribusiId => 20,
            $pasienId => 20,
            $ratingId => 20,
        ];

        $weightRows = [];
        $activeWeightsByUnit = [];
        $sumWeightByUnit = [];
        foreach ($unitIds as $uId) {
            foreach ($defaultWeights as $critId => $weight) {
                $status = 'active';
                // Example: make one criterion NON-AKTIF (draft) for a unit in this period.
                if ($exampleInactiveUnitId && (int) $uId === (int) $exampleInactiveUnitId && (int) $critId === (int) $ratingId) {
                    $status = 'draft';
                }
                $weightRows[] = [
                    'unit_id' => $uId,
                    'performance_criteria_id' => $critId,
                    'weight' => $weight,
                    'assessment_period_id' => $period->id,
                    'status' => $status,
                    'policy_doc_path' => null,
                    'policy_note' => 'Seeder bobot default',
                    'unit_head_id' => null,
                    'unit_head_note' => null,
                    'polyclinic_head_id' => null,
                    'created_at' => $now,
                    'updated_at' => $now,
                ];

                if ($status === 'active') {
                    $activeWeightsByUnit[(int) $uId][(int) $critId] = (float) $weight;
                    $sumWeightByUnit[(int) $uId] = ($sumWeightByUnit[(int) $uId] ?? 0.0) + (float) $weight;
                }
            }
        }
        if (!empty($weightRows)) {
            DB::table('unit_criteria_weights')->insert($weightRows);
        }

        // Insert source data & collect raw values per user + totals per unit-profession
        $rawByUser = [];
        $totalsByUnitProf = [];

        // default jumlah rater per penilaian publik (karena kita isi 3 review)
        $defaultRaterCount = 3;

        $patientsBatchId = DB::table('metric_import_batches')->insertGetId([
            'file_name' => 'seeder-patients-' . $period->id . '.xlsx',
            'assessment_period_id' => $period->id,
            'imported_by' => null,
            'status' => 'processed',
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        foreach ($data as $key => $row) {
            $userId = $staff[$key]['id'];
            $unitId = $unitIdResolver($staff[$key]['unit_slug']);
            $profId = $professionIdResolver($staff[$key]['profession']);

            // Attendance Hadir rows
            $dates = $this->takeDates((string)$period->start_date, (string)$period->end_date, (int)$row['attendance']);
            $attRows = [];
            foreach ($dates as $d) {
                $attRows[] = [
                    'user_id' => $userId,
                    'attendance_date' => $d,
                    'attendance_status' => 'hadir',
                    'source' => 'import',
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }
            DB::table('attendances')->insert($attRows);

            // Multi-rater discipline (single header, one detail for discipline criteria)
            $mraId = DB::table('multi_rater_assessments')->insertGetId([
                'assessee_id' => $userId,
                'assessor_id' => $userId,
                'assessor_type' => 'self',
                'assessment_period_id' => $period->id,
                'status' => 'submitted',
                'submitted_at' => $assessmentDate,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
            DB::table('multi_rater_assessment_details')->insert([
                'multi_rater_assessment_id' => $mraId,
                'performance_criteria_id' => $kedis360Id,
                'score' => $row['discipline'],
                'comment' => 'Seeder discipline score',
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            // Additional contributions approved with score (points)
            DB::table('additional_contributions')->insert([
                'user_id' => $userId,
                'title' => 'Kontribusi periode ' . $period->name,
                'description' => 'Seeder contribution',
                'submission_date' => $assessmentDate->toDateString(),
                'validation_status' => 'Disetujui',
                'score' => $row['contrib'],
                'assessment_period_id' => $period->id,
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            // Patients metric
            DB::table('imported_criteria_values')->insert([
                'import_batch_id' => $patientsBatchId,
                'user_id' => $userId,
                'assessment_period_id' => $period->id,
                'performance_criteria_id' => $pasienId,
                'value_numeric' => $row['patients'],
                'value_datetime' => null,
                'value_text' => null,
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            // Rating via reviews (three ratings to maintain average)
            $ratings = [$row['rating'], $row['rating'], $row['rating']];
            $role = 'lainnya';
            $profCode = $staff[$key]['profession'] ?? '';
            if (str_contains($profCode, 'PRW')) {
                $role = 'perawat';
            } elseif (str_contains($profCode, 'DOK')) {
                $role = 'dokter';
            }
            $revIds = [];
            foreach ($ratings as $idx => $rt) {
                $revId = DB::table('reviews')->insertGetId([
                    'registration_ref' => 'DRV-' . $period->id . '-' . $userId . '-' . ($idx + 1),
                    'unit_id' => $unitId,
                    'overall_rating' => $rt,
                    'comment' => 'Seeder rating',
                    'patient_name' => 'Pasien ' . ($idx + 1),
                    'contact' => '08xxxxxxxxxx',
                    'client_ip' => '127.0.0.1',
                    'user_agent' => 'Seeder',
                    'status' => 'approved',
                    'decision_note' => 'Auto approved',
                    'decided_by' => $userId,
                    'decided_at' => $assessmentDate,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
                $revIds[] = $revId;
                DB::table('review_details')->insert([
                    'review_id' => $revId,
                    'medical_staff_id' => $userId,
                    'role' => $role,
                    'rating' => $rt,
                    'comment' => 'Seeder rating detail',
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }

            // Raw collector
            $rawByUser[$userId] = [
                'unit_id' => $unitId,
                'profession_id' => $profId,
                'absensi_days' => (int) $row['attendance'],
                'work_hours' => (int) $row['attendance'], // jam kerja sederhana = hari (karena tidak ada durasi), tetap gunakan hari untuk konsistensi
                'kedisiplinan' => (float) $row['discipline'],
                'kontribusi' => (float) $row['contrib'],
                'pasien' => (float) $row['patients'],
                'rating_avg' => (float) $row['rating'],
                'rating_count' => $defaultRaterCount,
            ];

            $totalsByUnitProf[$unitId][$profId]['absensi_days'] = ($totalsByUnitProf[$unitId][$profId]['absensi_days'] ?? 0) + (int) $row['attendance'];
            $totalsByUnitProf[$unitId][$profId]['work_hours'] = ($totalsByUnitProf[$unitId][$profId]['work_hours'] ?? 0) + (int) $row['attendance'];
            $totalsByUnitProf[$unitId][$profId]['kedisiplinan'] = ($totalsByUnitProf[$unitId][$profId]['kedisiplinan'] ?? 0) + (float) $row['discipline'];
            $totalsByUnitProf[$unitId][$profId]['kontribusi'] = ($totalsByUnitProf[$unitId][$profId]['kontribusi'] ?? 0) + (float) $row['contrib'];
            $totalsByUnitProf[$unitId][$profId]['pasien'] = ($totalsByUnitProf[$unitId][$profId]['pasien'] ?? 0) + (float) $row['patients'];
            $totalsByUnitProf[$unitId][$profId]['rating_weighted'] = ($totalsByUnitProf[$unitId][$profId]['rating_weighted'] ?? 0) + ((float)$row['rating'] * $defaultRaterCount);
        }

        $perUserScores = [];

        // Bangun WSM manual per user dengan pembagi unit + profesi
        foreach ($rawByUser as $uid => $raw) {
            $unitId = $raw['unit_id'];
            $profId = $raw['profession_id'];

            $den = $totalsByUnitProf[$unitId][$profId] ?? [];

            $absRaw = $raw['absensi_days'];
            $absDen = max((float)($den['absensi_days'] ?? 0), 0.0001);
            $absScore = ($absRaw / $absDen) * 100;

            $discRaw = $raw['kedisiplinan'];
            $discDen = max((float)($den['kedisiplinan'] ?? 0), 0.0001);
            $discScore = ($discRaw / $discDen) * 100;

            $contribRaw = $raw['kontribusi'];
            $contribDen = max((float)($den['kontribusi'] ?? 0), 0.0001);
            $contribScore = ($contribRaw / $contribDen) * 100;

            $patientRaw = $raw['pasien'];
            $patientDen = max((float)($den['pasien'] ?? 0), 0.0001);
            $patientScore = ($patientRaw / $patientDen) * 100;

            $ratingRaw = $raw['rating_avg'] * $raw['rating_count'];
            $ratingDen = max((float)($den['rating_weighted'] ?? 0), 0.0001);
            $ratingScore = ($ratingRaw / $ratingDen) * 100;

            $scores = [
                'absensi' => $absScore,
                'kedisiplinan' => $discScore,
                'kontribusi' => $contribScore,
                'pasien' => $patientScore,
                'rating' => $ratingScore,
            ];
            $perUserScores[$uid] = $scores;

            // Total WSM must only count criteria with ACTIVE weights for the period.
            $weightsActive = (array) ($activeWeightsByUnit[(int) $unitId] ?? []);
            $sumWeightActive = (float) ($sumWeightByUnit[(int) $unitId] ?? 0.0);
            $weightedSum = 0.0;
            if ($sumWeightActive > 0 && !empty($weightsActive)) {
                $weightedSum += ((float) ($weightsActive[(int) $absensiId] ?? 0.0)) * (float) $absScore;
                $weightedSum += ((float) ($weightsActive[(int) $kedis360Id] ?? 0.0)) * (float) $discScore;
                $weightedSum += ((float) ($weightsActive[(int) $kontribusiId] ?? 0.0)) * (float) $contribScore;
                $weightedSum += ((float) ($weightsActive[(int) $pasienId] ?? 0.0)) * (float) $patientScore;
                $weightedSum += ((float) ($weightsActive[(int) $ratingId] ?? 0.0)) * (float) $ratingScore;
            }
            $totalWsm = $sumWeightActive > 0 ? ($weightedSum / $sumWeightActive) : 0.0;

            $assessmentId = DB::table('performance_assessments')->insertGetId([
                'user_id' => $uid,
                'assessment_period_id' => $period->id,
                'assessment_date' => $assessmentDate,
                'total_wsm_score' => round($totalWsm, 2),
                'validation_status' => $validationStatus,
                'supervisor_comment' => 'Dihitung otomatis dari data tabel.',
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            $detailRows = [
                [
                    'performance_assessment_id' => $assessmentId,
                    'performance_criteria_id' => $absensiId,
                    'score' => round($absScore, 2),
                    'created_at' => $now,
                    'updated_at' => $now,
                ],
                [
                    'performance_assessment_id' => $assessmentId,
                    'performance_criteria_id' => $kedis360Id,
                    'score' => round($discScore, 2),
                    'created_at' => $now,
                    'updated_at' => $now,
                ],
                [
                    'performance_assessment_id' => $assessmentId,
                    'performance_criteria_id' => $kontribusiId,
                    'score' => round($contribScore, 2),
                    'created_at' => $now,
                    'updated_at' => $now,
                ],
                [
                    'performance_assessment_id' => $assessmentId,
                    'performance_criteria_id' => $pasienId,
                    'score' => round($patientScore, 2),
                    'created_at' => $now,
                    'updated_at' => $now,
                ],
                [
                    'performance_assessment_id' => $assessmentId,
                    'performance_criteria_id' => $ratingId,
                    'score' => round($ratingScore, 2),
                    'created_at' => $now,
                    'updated_at' => $now,
                ],
            ];
            DB::table('performance_assessment_details')->insert($detailRows);

            DB::table('assessment_approvals')->insert([
                'performance_assessment_id' => $assessmentId,
                'level' => 1,
                'approver_id' => $uid, // placeholder approver; adjust as needed
                'status' => $approvalStatus,
                'note' => $approvalStatus === 'approved' ? 'Disetujui otomatis seeder' : 'Menunggu persetujuan',
                'acted_at' => $approvalStatus === 'approved' ? $assessmentDate : null,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        // Distribute remuneration per unit+profession allocation proportionally to WSM
        $allocRows = array_filter($allocations, fn($row) => $row[0] === $period->id);
        foreach ($allocRows as [$pId, $unitSlug, $profCode, $amount]) {
            $unitId = $unitIdResolver($unitSlug);
            $profId = $professionIdResolver($profCode);
            $users = collect($staff)
                ->filter(fn($s) => $unitIdResolver($s['unit_slug']) === $unitId && $professionIdResolver($s['profession']) === $profId)
                ->pluck('id')
                ->all();
            if (empty($users)) {
                continue;
            }

            $wsmTotals = DB::table('performance_assessments')
                ->where('assessment_period_id', $period->id)
                ->whereIn('user_id', $users)
                ->pluck('total_wsm_score', 'user_id')
                ->map(fn($v) => (float)$v)
                ->all();
            $sumWsm = array_sum($wsmTotals);
            if ($sumWsm <= 0) {
                $sumWsm = count($users);
                $wsmTotals = array_fill_keys($users, 1.0);
            }

            foreach ($wsmTotals as $uid => $wsm) {
                $share = $amount * ($wsm / $sumWsm);

                $scores = $perUserScores[$uid] ?? [];

                $raw = $rawByUser[$uid] ?? [];
                $unitIdForUser = (int) ($raw['unit_id'] ?? 0);
                $weightsActive = (array) ($activeWeightsByUnit[$unitIdForUser] ?? []);
                $usedScores = [];
                if (!empty($weightsActive)) {
                    if (isset($weightsActive[(int) $absensiId])) $usedScores['absensi'] = (float) ($scores['absensi'] ?? 0);
                    if (isset($weightsActive[(int) $kedis360Id])) $usedScores['kedisiplinan'] = (float) ($scores['kedisiplinan'] ?? 0);
                    if (isset($weightsActive[(int) $kontribusiId])) $usedScores['kontribusi'] = (float) ($scores['kontribusi'] ?? 0);
                    if (isset($weightsActive[(int) $pasienId])) $usedScores['pasien'] = (float) ($scores['pasien'] ?? 0);
                    if (isset($weightsActive[(int) $ratingId])) $usedScores['rating'] = (float) ($scores['rating'] ?? 0);
                }

                $scoreSum = array_sum($usedScores) ?: 1;

                $comp = [
                    'absensi' => ($usedScores['absensi'] ?? 0) / $scoreSum * $share,
                    'kedisiplinan' => ($usedScores['kedisiplinan'] ?? 0) / $scoreSum * $share,
                    'kontribusi' => ($usedScores['kontribusi'] ?? 0) / $scoreSum * $share,
                    'pasien' => ($usedScores['pasien'] ?? 0) / $scoreSum * $share,
                    'rating' => ($usedScores['rating'] ?? 0) / $scoreSum * $share,
                ];
                $raterCount = $raw['rating_count'] ?? 0;
                $contribCount = ($raw['kontribusi'] ?? 0) > 0 ? 1 : 0;

                DB::table('remunerations')->insert([
                    'user_id' => $uid,
                    'assessment_period_id' => $period->id,
                    'amount' => round($share, 2),
                    'payment_date' => $paymentDate,
                    'payment_status' => $paymentDate ? 'Dibayar' : 'Belum Dibayar',
                    'calculation_details' => json_encode([
                        'method' => 'unit_profession_wsm_proportional',
                        'allocation' => $amount,
                        'unit_id' => $unitId,
                        'profession_id' => $profId,
                        'user_wsm' => round($wsm, 2),
                        'total_wsm_unit_profession' => round($sumWsm, 2),
                        'komponen' => [
                            'dasar' => 0,
                            'pasien_ditangani' => [
                                'jumlah' => $raw['pasien'] ?? null,
                                'nilai' => round($comp['pasien'], 2),
                            ],
                            'review_pelanggan' => [
                                'jumlah' => $raterCount,
                                'nilai' => round($comp['rating'], 2),
                            ],
                            'kontribusi_tambahan' => [
                                'jumlah' => $contribCount,
                                'nilai' => round($comp['kontribusi'], 2),
                            ],
                            'absensi' => [
                                'jumlah' => $raw['absensi_days'] ?? null,
                                'nilai' => round($comp['absensi'], 2),
                            ],
                            'kedisiplinan' => [
                                'jumlah' => $raw['kedisiplinan'] ?? null,
                                'nilai' => round($comp['kedisiplinan'], 2),
                            ],
                        ],
                    ], JSON_UNESCAPED_UNICODE),
                    'published_at' => $publishDate,
                    'calculated_at' => $publishDate,
                    'revised_by' => null,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }

            DB::table('unit_profession_remuneration_allocations')->updateOrInsert(
                [
                    'assessment_period_id' => $period->id,
                    'unit_id' => $unitId,
                    'profession_id' => $profId,
                ],
                [
                    'amount' => $amount,
                    'note' => 'Seeder alokasi ' . $period->name,
                    'published_at' => $publishDate,
                    'revised_by' => null,
                    'updated_at' => $now,
                    'created_at' => $now,
                ]
            );
        }
    }

    private function periodModel(object $period): \App\Models\AssessmentPeriod
    {
        return \App\Models\AssessmentPeriod::findOrFail($period->id);
    }

    /**
     * Ambil N tanggal dalam rentang periode untuk absensi Hadir.
     * @return array<int,string>
     */
    private function takeDates(string $start, string $end, int $count): array
    {
        $dates = [];
        $cursor = Carbon::parse($start);
        $endDate = Carbon::parse($end);
        while ($cursor->lte($endDate) && count($dates) < $count) {
            $dates[] = $cursor->toDateString();
            $cursor->addDay();
        }
        // If period shorter than requested count, repeat last date to satisfy count
        while (count($dates) < $count && !empty($dates)) {
            $dates[] = end($dates);
        }
        return $dates;
    }
}
