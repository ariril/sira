<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
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

        // Helpers
        $criteriaId = fn(string $name) => DB::table('performance_criterias')->where('name', $name)->value('id');
        $userId = fn(string $email) => DB::table('users')->where('email', $email)->value('id');
        $professionId = fn(string $code) => DB::table('professions')->where('code', $code)->value('id');
        $unitId = fn(string $slug) => DB::table('units')->where('slug', $slug)->value('id');

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

        DB::table('criteria_metrics')->whereIn('user_id', $targets)->whereIn('assessment_period_id', $periodIds)->delete();
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
            'melria' => ['attendance' => 25, 'discipline' => 82, 'contrib' => 8,  'patients' => 120, 'rating' => 4.4],
            'janBeria' => ['attendance' => 25, 'discipline' => 80, 'contrib' => 7,  'patients' => 110, 'rating' => 4.3],
        ];
        $novRaw = [
            'felix' => ['attendance' => 25, 'discipline' => 87, 'contrib' => 10, 'patients' => 205, 'rating' => 4.6],
            'fransisca' => ['attendance' => 24, 'discipline' => 80, 'contrib' => 8,  'patients' => 135, 'rating' => 4.4],
            'theodorus' => ['attendance' => 24, 'discipline' => 82, 'contrib' => 9,  'patients' => 150, 'rating' => 4.5],
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
            unitIdResolver: $unitId
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
            unitIdResolver: $unitId
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
        callable $unitIdResolver
    ): void {
        $now = Carbon::now();
        $calc = app(\App\Services\BestScenarioCalculator::class);

        $absensiId    = $criteriaIds['absensiId'];
        $kedis360Id   = $criteriaIds['kedis360Id'];
        $kontribusiId = $criteriaIds['kontribusiId'];
        $pasienId     = $criteriaIds['pasienId'];
        $ratingId     = $criteriaIds['ratingId'];

        // Insert source data
        foreach ($data as $key => $row) {
            $userId = $staff[$key]['id'];
            $unitId = $unitIdResolver($staff[$key]['unit_slug']);

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
            DB::table('criteria_metrics')->insert([
                'user_id' => $userId,
                'assessment_period_id' => $period->id,
                'performance_criteria_id' => $pasienId,
                'value_numeric' => $row['patients'],
                'source_type' => 'import',
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
        }

        // Build WSM via BestScenarioCalculator per unit
        $unitBuckets = [];
        foreach ($staff as $key => $meta) {
            $unitId = $unitIdResolver($meta['unit_slug']);
            $unitBuckets[$unitId][] = $meta['id'];
        }

        foreach ($unitBuckets as $unitId => $userIds) {
            $result = $calc->calculateForUnit($unitId, $this->periodModel($period), $userIds);
            foreach ($result['users'] as $uid => $userRow) {
                $assessmentId = DB::table('performance_assessments')->insertGetId([
                    'user_id' => $uid,
                    'assessment_period_id' => $period->id,
                    'assessment_date' => $assessmentDate,
                    'total_wsm_score' => round($userRow['total_wsm'], 2),
                    'validation_status' => $validationStatus,
                    'supervisor_comment' => 'Dihitung otomatis dari data tabel.',
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);

                $detailRows = [];
                foreach ($userRow['criteria'] as $crit) {
                    $cid = match ($crit['key']) {
                        'absensi' => $absensiId,
                        'kedisiplinan' => $kedis360Id,
                        'kontribusi' => $kontribusiId,
                        'pasien' => $pasienId,
                        'rating' => $ratingId,
                        default => null,
                    };
                    if (!$cid) {
                        continue;
                    }
                    $detailRows[] = [
                        'performance_assessment_id' => $assessmentId,
                        'performance_criteria_id' => $cid,
                        'score' => round($crit['normalized'], 2),
                        'created_at' => $now,
                        'updated_at' => $now,
                    ];
                }
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
                DB::table('remunerations')->insert([
                    'user_id' => $uid,
                    'assessment_period_id' => $period->id,
                    'amount' => round($share, 2),
                    'payment_date' => $paymentDate,
                    'payment_status' => $paymentDate ? 'Dibayar' : 'Belum Dibayar',
                    'calculation_details' => json_encode([
                        'allocation' => $amount,
                        'unit_id' => $unitId,
                        'profession_id' => $profId,
                        'user_wsm' => round($wsm, 2),
                        'total_wsm_unit_profession' => round($sumWsm, 2),
                    ], JSON_UNESCAPED_UNICODE),
                    'published_at' => $publishDate,
                    'calculated_at' => $publishDate,
                    'revised_by' => null,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }

            DB::table('unit_remuneration_allocations')->updateOrInsert(
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
