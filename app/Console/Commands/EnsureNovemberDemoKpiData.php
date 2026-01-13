<?php

namespace App\Console\Commands;

use App\Services\AssessmentPeriods\PeriodPerformanceAssessmentService;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class EnsureNovemberDemoKpiData extends Command
{
    protected $signature = 'kpi:ensure-november-demo
        {--period=November 2025 : Nama AssessmentPeriod (contoh: "November 2025")}
        {--units=poliklinik-umum,poliklinik-gigi : Unit slug dipisah koma}
        {--professions=DOK-UM,DOK-SP,PRW,KPL-UNIT-DOK,KPL-POLI-DOK : Kode profesi dipisah koma}
        {--dry-run : Tampilkan rencana tanpa insert}
        {--recalc=1 : 1 untuk hitung ulang performance_assessments (default), 0 untuk skip}';

    protected $description = 'Ensure demo KPI RAW data exists for all active users in a November period (no overwrite), including absensi/import, 360, kontribusi, metric import, dan rating.';

    public function handle(): int
    {
        $now = Carbon::now();
        $dryRun = (bool) $this->option('dry-run');
        $doRecalc = (string) $this->option('recalc') !== '0';

        $periodName = trim((string) $this->option('period'));
        $period = DB::table('assessment_periods')->where('name', $periodName)->first();
        if (!$period) {
            $period = DB::table('assessment_periods')
                ->where('name', 'like', '%November%')
                ->orderByDesc('start_date')
                ->first();
        }
        if (!$period) {
            $this->error('AssessmentPeriod November tidak ditemukan.');
            return self::FAILURE;
        }

        $periodId = (int) $period->id;
        $start = Carbon::parse((string) $period->start_date)->startOfDay();
        $end = Carbon::parse((string) $period->end_date)->endOfDay();

        $unitSlugs = array_values(array_filter(array_map('trim', explode(',', (string) $this->option('units')))));
        $professionCodes = array_values(array_filter(array_map('trim', explode(',', (string) $this->option('professions')))));

        $unitIds = DB::table('units')->whereIn('slug', $unitSlugs)->pluck('id')->map(fn($v) => (int) $v)->all();
        $profIds = DB::table('professions')->whereIn('code', $professionCodes)->pluck('id')->map(fn($v) => (int) $v)->all();

        if (empty($unitIds) || empty($profIds)) {
            $this->error('Unit/profession target kosong. Cek opsi --units/--professions.');
            return self::FAILURE;
        }

        $criteriaNames = [
            'Kehadiran (Absensi)',
            'Jam Kerja (Absensi)',
            'Lembur (Absensi)',
            'Keterlambatan (Absensi)',
            'Kedisiplinan (360)',
            'Kerjasama (360)',
            'Tugas Tambahan',
            'Jumlah Pasien Ditangani',
            'Jumlah Komplain Pasien',
            'Rating',
        ];

        $criteriaByName = DB::table('performance_criterias')
            ->whereIn('name', $criteriaNames)
            ->pluck('id', 'name')
            ->map(fn($v) => (int) $v)
            ->all();

        $missingCriteria = array_values(array_diff($criteriaNames, array_keys($criteriaByName)));
        if (!empty($missingCriteria)) {
            $this->error('Missing performance_criterias: ' . implode(', ', $missingCriteria) . '. Jalankan DatabaseSeeder dulu.');
            return self::FAILURE;
        }

        $users = DB::table('users')
            ->select('id', 'name', 'email', 'unit_id', 'profession_id', 'employee_number')
            ->whereIn('unit_id', $unitIds)
            ->whereIn('profession_id', $profIds)
            ->orderBy('unit_id')
            ->orderBy('profession_id')
            ->orderBy('id')
            ->get();

        if ($users->isEmpty()) {
            $this->warn('Tidak ada user untuk kombinasi unit+profesi target.');
            return self::SUCCESS;
        }

        // Ensure active unit_criteria_weights (if empty) for each unit.
        $defaultWeightsByCriteriaName = [
            'Kehadiran (Absensi)' => 20,
            'Jam Kerja (Absensi)' => 10,
            'Lembur (Absensi)' => 5,
            'Keterlambatan (Absensi)' => 10,
            'Kedisiplinan (360)' => 15,
            'Kerjasama (360)' => 10,
            'Tugas Tambahan' => 10,
            'Jumlah Pasien Ditangani' => 5,
            'Jumlah Komplain Pasien' => 3,
            'Rating' => 12,
        ];

        foreach ($unitIds as $unitId) {
            $periodStatus = (string) ($period->status ?? '');
            $seedStatus = $periodStatus === 'active' ? 'active' : 'archived';
            $hasWasActiveBefore = 
                \Illuminate\Support\Facades\Schema::hasTable('unit_criteria_weights')
                && \Illuminate\Support\Facades\Schema::hasColumn('unit_criteria_weights', 'was_active_before');

            $existingCount = (int) DB::table('unit_criteria_weights')
                ->where('assessment_period_id', $periodId)
                ->where('unit_id', $unitId)
                ->where('status', $seedStatus)
                ->count();

            if ($existingCount > 0) {
                continue;
            }

            foreach ($defaultWeightsByCriteriaName as $name => $w) {
                $critId = (int) ($criteriaByName[$name] ?? 0);
                if ($critId <= 0) continue;

                if ($dryRun) {
                    continue;
                }

                DB::table('unit_criteria_weights')->updateOrInsert(
                    [
                        'unit_id' => $unitId,
                        'performance_criteria_id' => $critId,
                        'assessment_period_id' => $periodId,
                        'status' => $seedStatus,
                    ],
                    [
                        'weight' => (float) $w,
                        // IMPORTANT: do NOT force was_active_before=1 for demo-seeded archived rows.
                        // A row should only be marked as previously active if it really was active.
                        'policy_doc_path' => null,
                        'policy_note' => 'Auto demo seed (Nov) - default weights',
                        'proposed_by' => null,
                        'proposed_note' => null,
                        'decided_by' => null,
                        'decided_at' => null,
                        'updated_at' => $now,
                        'created_at' => $now,
                    ]
                );
            }
        }

        // Ensure one active attendance import batch.
        $attendanceBatchId = (int) (DB::table('attendance_import_batches')
            ->where('assessment_period_id', $periodId)
            ->where('is_superseded', 0)
            ->value('id') ?? 0);
        if ($attendanceBatchId <= 0 && !$dryRun) {
            $attendanceBatchId = (int) DB::table('attendance_import_batches')->insertGetId([
                'file_name' => 'auto-demo-attendance-' . $periodId . '.xlsx',
                'assessment_period_id' => $periodId,
                'imported_by' => null,
                'imported_at' => $now,
                'total_rows' => 0,
                'success_rows' => 0,
                'failed_rows' => 0,
                'is_superseded' => 0,
                'previous_batch_id' => null,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        // Ensure one metric import batch.
        $metricsBatchId = (int) (DB::table('metric_import_batches')
            ->where('assessment_period_id', $periodId)
            ->where('status', 'processed')
            ->orderByDesc('id')
            ->value('id') ?? 0);
        if ($metricsBatchId <= 0 && !$dryRun) {
            $metricsBatchId = (int) DB::table('metric_import_batches')->insertGetId([
                'file_name' => 'auto-demo-metrics-' . $periodId . '.xlsx',
                'assessment_period_id' => $periodId,
                'imported_by' => null,
                'status' => 'processed',
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        $groupsToRecalc = [];

        foreach ($users as $u) {
            $userId = (int) $u->id;
            $unitId = (int) $u->unit_id;
            $profId = (int) $u->profession_id;

            $groupsToRecalc[$unitId . ':' . $profId] = [$unitId, $profId];

            $seed = crc32($userId . '|' . $periodId . '|nov-demo');
            mt_srand($seed);

            $professionCode = (string) (DB::table('professions')->where('id', $profId)->value('code') ?? '');

            $raw = $this->buildDefaultRaw($professionCode);

            // A) Absensi
            $hasAttendance = (int) DB::table('attendances')
                ->where('user_id', $userId)
                ->whereBetween('attendance_date', [$start->toDateString(), $end->toDateString()])
                ->count() > 0;

            if (!$hasAttendance) {
                $dates = $this->takeDates($start->toDateString(), $end->toDateString(), (int) $raw['attendance_days']);
                $plan = $this->buildAttendancePlan($userId, $dates, (int) $raw['late_minutes'], (int) $raw['work_minutes'], (int) $raw['overtime_days']);

                if (!$dryRun) {
                    $rowNo = (int) (DB::table('attendance_import_rows')->where('batch_id', $attendanceBatchId)->max('row_no') ?? 1);
                    foreach ($plan as $pr) {
                        $rowNo++;

                        DB::table('attendance_import_rows')->insert([
                            'batch_id' => $attendanceBatchId,
                            'row_no' => $rowNo,
                            'user_id' => $userId,
                            'employee_number' => (string) ($u->employee_number ?? ''),
                            'raw_data' => json_encode($pr['raw_data'], JSON_UNESCAPED_UNICODE),
                            'parsed_data' => json_encode($pr['parsed_data'], JSON_UNESCAPED_UNICODE),
                            'success' => true,
                            'error_code' => null,
                            'error_message' => null,
                            'created_at' => $now,
                            'updated_at' => $now,
                        ]);

                        DB::table('attendances')->updateOrInsert(
                            ['user_id' => $userId, 'attendance_date' => (string) $pr['attendance_date']],
                            [
                                'check_in' => (string) $pr['check_in'],
                                'check_out' => (string) $pr['check_out'],
                                'shift_name' => null,
                                'scheduled_in' => (string) $pr['scheduled_in'],
                                'scheduled_out' => (string) $pr['scheduled_out'],
                                'late_minutes' => (int) $pr['late_minutes'],
                                'early_leave_minutes' => null,
                                'work_duration_minutes' => (int) $pr['work_duration_minutes'],
                                'break_duration_minutes' => null,
                                'extra_break_minutes' => null,
                                'overtime_end' => $pr['overtime_end'],
                                'holiday_public' => 0,
                                'holiday_regular' => 0,
                                'overtime_shift' => (int) $pr['overtime_shift'],
                                'attendance_status' => (string) $pr['attendance_status'],
                                'note' => 'Auto demo seed (Nov)',
                                'overtime_note' => null,
                                'source' => 'import',
                                'import_batch_id' => $attendanceBatchId > 0 ? $attendanceBatchId : null,
                                'updated_at' => $now,
                                'created_at' => $now,
                            ]
                        );
                    }
                }
            }

            // B) 360
            $has360 = (int) DB::table('multi_rater_assessments')
                ->where('assessee_id', $userId)
                ->where('assessment_period_id', $periodId)
                ->where('assessor_type', 'supervisor')
                ->count() > 0;

            if (!$has360 && !$dryRun) {
                $assessorId = (int) (DB::table('users')
                    ->where('unit_id', $unitId)
                    ->where('id', '!=', $userId)
                    ->orderBy('id')
                    ->value('id') ?? 0);

                if ($assessorId <= 0) {
                    $assessorId = $userId;
                }

                DB::table('multi_rater_assessments')->updateOrInsert(
                    [
                        'assessee_id' => $userId,
                        'assessment_period_id' => $periodId,
                        'assessor_type' => 'supervisor',
                        'assessor_id' => $assessorId,
                    ],
                    [
                        'status' => 'submitted',
                        'submitted_at' => $now,
                        'updated_at' => $now,
                        'created_at' => $now,
                    ]
                );

                $mraId = (int) DB::table('multi_rater_assessments')
                    ->where('assessee_id', $userId)
                    ->where('assessment_period_id', $periodId)
                    ->where('assessor_type', 'supervisor')
                    ->where('assessor_id', $assessorId)
                    ->value('id');

                DB::table('multi_rater_assessment_details')->updateOrInsert(
                    ['multi_rater_assessment_id' => $mraId, 'performance_criteria_id' => $criteriaByName['Kedisiplinan (360)']],
                    ['score' => (float) $raw['discipline_360'], 'comment' => 'Auto demo seed (Nov)', 'updated_at' => $now, 'created_at' => $now]
                );
                DB::table('multi_rater_assessment_details')->updateOrInsert(
                    ['multi_rater_assessment_id' => $mraId, 'performance_criteria_id' => $criteriaByName['Kerjasama (360)']],
                    ['score' => (float) $raw['teamwork_360'], 'comment' => 'Auto demo seed (Nov)', 'updated_at' => $now, 'created_at' => $now]
                );
            }

            // C) Metric import: pasien + komplain
            foreach ([
                'Jumlah Pasien Ditangani' => (float) $raw['patients'],
                'Jumlah Komplain Pasien' => (float) $raw['complaints'],
            ] as $critName => $value) {
                $critId = (int) ($criteriaByName[$critName] ?? 0);
                if ($critId <= 0) continue;

                $exists = (int) DB::table('imported_criteria_values')
                    ->where('user_id', $userId)
                    ->where('assessment_period_id', $periodId)
                    ->where('performance_criteria_id', $critId)
                    ->count() > 0;

                if ($exists || $dryRun) {
                    continue;
                }

                DB::table('imported_criteria_values')->insert([
                    'import_batch_id' => $metricsBatchId,
                    'user_id' => $userId,
                    'assessment_period_id' => $periodId,
                    'performance_criteria_id' => $critId,
                    'value_numeric' => $value,
                    'value_datetime' => null,
                    'value_text' => null,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }

            // E) Rating via invitations + reviews
            $prefix = 'DEMO-' . $periodId . '-' . $userId . '-';
            $hasInv = (int) DB::table('review_invitations')
                ->where('assessment_period_id', $periodId)
                ->where('registration_ref', 'like', $prefix . '%')
                ->count() > 0;

            if (!$hasInv && !$dryRun) {
                $count = 10;
                $avg = (float) $raw['rating_avg'];
                $ratings = $this->buildRatingDistribution($avg, $count);

                $role = $professionCode === 'PRW' ? 'perawat' : 'dokter';

                foreach ($ratings as $idx => $rt) {
                    $registrationRef = $prefix . ($idx + 1);

                    $tokenHash = hash('sha256', Str::random(64) . '|' . $registrationRef);
                    $invId = (int) DB::table('review_invitations')->insertGetId([
                        'registration_ref' => $registrationRef,
                        'unit_id' => $unitId ?: null,
                        'patient_name' => 'Demo Pasien ' . ($idx + 1),
                        'contact' => '08xxxxxxxxxx',
                        'assessment_period_id' => $periodId,
                        'token_hash' => $tokenHash,
                        'status' => 'used',
                        'expires_at' => $now->copy()->addDays(7),
                        'sent_at' => $now->copy()->subDays(1),
                        'clicked_at' => $now->copy()->subHours(2),
                        'used_at' => $now,
                        'client_ip' => '127.0.0.1',
                        'user_agent' => 'AutoDemoSeeder',
                        'created_at' => $now,
                        'updated_at' => $now,
                    ]);

                    DB::table('review_invitation_staff')->updateOrInsert(
                        ['invitation_id' => $invId, 'user_id' => $userId],
                        ['role' => $role, 'updated_at' => $now, 'created_at' => $now]
                    );

                    $revId = (int) DB::table('reviews')->insertGetId([
                        'registration_ref' => $registrationRef,
                        'unit_id' => $unitId ?: null,
                        'overall_rating' => (int) $rt,
                        'comment' => 'Auto demo rating (Nov)',
                        'status' => 'approved',
                        'decision_note' => 'Auto approve demo',
                        'decided_by' => $userId,
                        'decided_at' => $now,
                        'patient_name' => 'Demo Pasien ' . ($idx + 1),
                        'contact' => '08xxxxxxxxxx',
                        'client_ip' => '127.0.0.1',
                        'user_agent' => 'AutoDemoSeeder',
                        'created_at' => $now,
                        'updated_at' => $now,
                    ]);

                    DB::table('review_details')->updateOrInsert(
                        ['review_id' => $revId, 'medical_staff_id' => $userId],
                        ['role' => $role, 'rating' => (int) $rt, 'comment' => 'Auto demo rating detail', 'updated_at' => $now, 'created_at' => $now]
                    );
                }
            }
        }

        if ($doRecalc && !$dryRun) {
            /** @var PeriodPerformanceAssessmentService $svc */
            $svc = app(PeriodPerformanceAssessmentService::class);
            foreach ($groupsToRecalc as [$unitId, $profId]) {
                if ($unitId > 0 && $profId > 0) {
                    $svc->recalculateForGroup($periodId, $unitId, $profId);
                }
            }
        }

        $this->info('OK: ensure demo data selesai untuk ' . $period->name . ' (users=' . $users->count() . ').');
        return self::SUCCESS;
    }

    private function buildDefaultRaw(string $professionCode): array
    {
        // Base ranges (from prompt) for DOK-UM. Other professions use reasonable demo adjustments.
        $base = [
            'attendance_days' => mt_rand(23, 26),
            'work_minutes' => mt_rand(8500, 9200),
            'overtime_days' => mt_rand(2, 4),
            'late_minutes' => mt_rand(30, 70),
            'discipline_360' => mt_rand(80, 90),
            'teamwork_360' => mt_rand(78, 88),
            'contrib' => mt_rand(8, 12),
            'patients' => mt_rand(140, 220),
            'complaints' => mt_rand(2, 6),
            'rating_avg' => mt_rand(43, 48) / 10,
        ];

        if ($professionCode === 'PRW') {
            $base['patients'] = 0;
            $base['complaints'] = 0;
            $base['rating_avg'] = mt_rand(42, 48) / 10;
            $base['discipline_360'] = mt_rand(82, 92);
            $base['teamwork_360'] = mt_rand(82, 92);
        }

        if ($professionCode === 'DOK-SP') {
            $base['patients'] = mt_rand(130, 200);
            $base['complaints'] = mt_rand(0, 4);
            $base['rating_avg'] = mt_rand(43, 49) / 10;
        }

        return $base;
    }

    private function takeDates(string $startDate, string $endDate, int $count): array
    {
        $out = [];
        $d = Carbon::parse($startDate);
        $end = Carbon::parse($endDate);

        while ($d->lte($end) && count($out) < $count) {
            $out[] = $d->toDateString();
            $d->addDay();
        }

        return $out;
    }

    private function buildAttendancePlan(int $userId, array $dates, int $totalLate, int $totalWork, int $overtimeCount): array
    {
        $days = max(1, count($dates));
        $lateBase = intdiv(max(0, $totalLate), $days);
        $lateRem = max(0, $totalLate) - ($lateBase * $days);

        $workBase = intdiv(max(0, $totalWork), $days);
        $workRem = max(0, $totalWork) - ($workBase * $days);

        $plan = [];
        foreach ($dates as $idx => $date) {
            $late = $lateBase + ($idx < $lateRem ? 1 : 0);
            $work = $workBase + ($idx < $workRem ? 1 : 0);
            $isOvertime = $idx < $overtimeCount;

            $scheduledIn = '08:00:00';
            $scheduledOut = '16:00:00';

            $checkIn = Carbon::parse($date . ' ' . $scheduledIn)->addMinutes($late);
            $checkOut = Carbon::parse($date . ' ' . $scheduledOut)->addMinutes(max(0, $work - 480));

            $plan[] = [
                'attendance_date' => $date,
                'scheduled_in' => $scheduledIn,
                'scheduled_out' => $scheduledOut,
                'check_in' => $checkIn->toDateTimeString(),
                'check_out' => $checkOut->toDateTimeString(),
                'late_minutes' => $late,
                'work_duration_minutes' => $work,
                'overtime_shift' => $isOvertime ? 1 : 0,
                'overtime_end' => $isOvertime ? $checkOut->copy()->addHours(1)->format('H:i:s') : null,
                'attendance_status' => $late > 0 ? 'Terlambat' : 'Hadir',
                'raw_data' => [
                    'NIP' => '',
                    'Tanggal' => $date,
                    'Jam Masuk' => $scheduledIn,
                    'Jam Keluar' => $scheduledOut,
                    'Scan Masuk' => $checkIn->format('H:i:s'),
                    'Scan Keluar' => $checkOut->format('H:i:s'),
                    'Datang Terlambat' => $late,
                    'Durasi Kerja' => $work,
                    'Shift Lembur' => $isOvertime ? 1 : 0,
                    'Status' => $late > 0 ? 'Terlambat' : 'Hadir',
                ],
                'parsed_data' => [
                    'attendance_date' => $date,
                    'scheduled_in' => $scheduledIn,
                    'scheduled_out' => $scheduledOut,
                    'check_in' => $checkIn->toDateTimeString(),
                    'check_out' => $checkOut->toDateTimeString(),
                    'late_minutes' => $late,
                    'work_duration_minutes' => $work,
                    'overtime_shift' => $isOvertime ? 1 : 0,
                    'overtime_end' => $isOvertime ? $checkOut->copy()->addHours(1)->toDateTimeString() : null,
                    'attendance_status' => $late > 0 ? 'Terlambat' : 'Hadir',
                ],
            ];
        }

        return $plan;
    }

    private function buildRatingDistribution(float $avg, int $count): array
    {
        $count = max(1, $count);
        $avg = max(1.0, min(5.0, $avg));

        $targetSum = (int) round($avg * $count);
        $minSum = 1 * $count;
        $maxSum = 5 * $count;
        $targetSum = max($minSum, min($maxSum, $targetSum));

        // Start all as floor(avg)
        $base = (int) floor($avg);
        $base = max(1, min(5, $base));
        $ratings = array_fill(0, $count, $base);
        $currentSum = $base * $count;

        // Increase ratings to reach targetSum
        $i = 0;
        while ($currentSum < $targetSum && $i < 100000) {
            $idx = $i % $count;
            if ($ratings[$idx] < 5) {
                $ratings[$idx]++;
                $currentSum++;
            }
            $i++;
        }

        // Decrease if overshoot
        $i = 0;
        while ($currentSum > $targetSum && $i < 100000) {
            $idx = $i % $count;
            if ($ratings[$idx] > 1) {
                $ratings[$idx]--;
                $currentSum--;
            }
            $i++;
        }

        shuffle($ratings);
        return $ratings;
    }
}
