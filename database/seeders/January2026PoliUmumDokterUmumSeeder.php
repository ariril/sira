<?php

namespace Database\Seeders;

use App\Enums\AttendanceStatus;
use App\Models\AssessmentPeriod;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class January2026PoliUmumDokterUmumSeeder extends Seeder
{
    public function run(): void
    {
        $requiredTables = [
            'assessment_periods',
            'units',
            'professions',
            'users',
            'attendances',
            'performance_criterias',
            'unit_criteria_weights',
            'unit_rater_weights',
            'additional_tasks',
            'additional_task_claims',
            'metric_import_batches',
            'imported_criteria_values',
            'multi_rater_assessments',
            'multi_rater_assessment_details',
            'reviews',
            'review_details',
        ];

        foreach ($requiredTables as $t) {
            if (!Schema::hasTable($t)) {
                $this->command?->warn("Skipping January2026PoliUmumDokterUmumSeeder: table '{$t}' not found.");
                return;
            }
        }

        $now = now();

        // 1) Period (January 2026)
        $period = AssessmentPeriod::query()->where('name', 'Januari 2026')->first();
        if (!$period) {
            $period = AssessmentPeriod::query()->create([
                'name' => 'Januari 2026',
                'start_date' => '2026-01-01',
                'end_date' => '2026-01-31',
                'status' => AssessmentPeriod::STATUS_ACTIVE,
            ]);
        } else {
            $period->start_date = $period->start_date ?: '2026-01-01';
            $period->end_date = $period->end_date ?: '2026-01-31';
            $period->status = AssessmentPeriod::STATUS_ACTIVE;
            $period->save();
        }

        $periodId = (int) $period->id;
        $startDate = $period->start_date ? Carbon::parse((string) $period->start_date)->startOfDay() : Carbon::create(2026, 1, 1);
        $endDate = $period->end_date ? Carbon::parse((string) $period->end_date)->startOfDay() : Carbon::create(2026, 1, 31);

        // 2) Unit + Profession + Users
        $unitId = (int) (DB::table('units')->where('slug', 'poliklinik-umum')->value('id') ?? 0);
        if ($unitId <= 0) {
            $this->command?->warn('Unit poliklinik-umum not found.');
            return;
        }

        $professionId = (int) (DB::table('professions')->where('code', 'DOK-UM')->value('id') ?? 0);
        if ($professionId <= 0) {
            $this->command?->warn('Profession DOK-UM not found.');
            return;
        }

        $felixId = (int) (DB::table('users')->where('email', 'kepala.umum@rsud.local')->value('id') ?? 0);
        $theoId = (int) (DB::table('users')->where('email', 'dokter.umum1@rsud.local')->value('id') ?? 0);
        if ($felixId <= 0 || $theoId <= 0) {
            $this->command?->warn('Target users not found (kepala.umum@rsud.local / dokter.umum1@rsud.local).');
            return;
        }

        // 360 assessors setup (USE EXISTING USERS ONLY):
        // - Felix dinilai oleh Felix sendiri sebagai Atasan L1 (akun Kepala Unit)
        // - Atasan L2 untuk semua = Kepala Poliklinik (kepala.poliklinik@rsud.local)
        // - Subordinate assessors = perawat1/perawat2
        $kepalaPoliklinikId = (int) (DB::table('users')->where('email', 'kepala.poliklinik@rsud.local')->value('id') ?? 0);
        if ($kepalaPoliklinikId <= 0) {
            $this->command?->warn('User kepala.poliklinik@rsud.local not found. Cannot seed supervisor L2.');
        }

        $nurse1Id = (int) (DB::table('users')->where('email', 'perawat1@rsud.local')->value('id') ?? 0);
        $nurse2Id = (int) (DB::table('users')->where('email', 'perawat2@rsud.local')->value('id') ?? 0);
        $nurseAssessorIds = array_values(array_filter([$nurse1Id, $nurse2Id], fn ($v) => (int) $v > 0));

        // IMPORTANT: this seeder is meant to be re-runnable.
        // Clear existing 360 submissions for these seeded assessees to avoid stale rows
        // (e.g., after changing assessor-type logic).
        DB::table('multi_rater_assessments')
            ->where('assessment_period_id', $periodId)
            ->whereIn('assessee_id', [$felixId, $theoId])
            ->delete();

        // 3) Copy Unit Criteria Weights + Rater Weights from previous period (Dec 2025)
        $prevPeriodId = $this->resolvePreviousPeriodId();
        if ($prevPeriodId > 0) {
            $this->copyUnitCriteriaWeights($unitId, $periodId, $prevPeriodId);
            $this->copyRaterWeights($unitId, $periodId, $prevPeriodId);
        } else {
            $this->command?->warn('Previous period (December 2025) not found. Weights were not copied.');
        }

        // Shared resources for this period
        $taskTitle = 'Tugas Tambahan - Januari 2026 (Poli Umum)';
        $taskId = $this->ensureAdditionalTask($unitId, $periodId, $taskTitle, $endDate, $now);

        $batchId = $this->ensureMetricImportBatch($periodId, $now);
        $pasienId = (int) (DB::table('performance_criterias')->where('name', 'Jumlah Pasien Ditangani')->value('id') ?? 0);
        $komplainId = (int) (DB::table('performance_criterias')->where('name', 'Jumlah Komplain Pasien')->value('id') ?? 0);

        $kedis360Id = (int) (DB::table('performance_criterias')->where('name', 'Kedisiplinan (360)')->value('id') ?? 0);
        $kerja360Id = (int) (DB::table('performance_criterias')->where('name', 'Kerjasama (360)')->value('id') ?? 0);
        $criteria360Ids = array_values(array_filter([(int) $kedis360Id, (int) $kerja360Id], fn ($v) => $v > 0));

        // ===== User blocks (comment out to disable) =====
        $this->seedUserFelix(
            periodId: $periodId,
            unitId: $unitId,
            userId: $felixId,
            otherUserId: $theoId,
            kepalaPoliklinikId: $kepalaPoliklinikId,
            nurseAssessorIds: $nurseAssessorIds,
            taskId: $taskId,
            batchId: $batchId,
            pasienCriteriaId: $pasienId,
            komplainCriteriaId: $komplainId,
            criteria360Ids: $criteria360Ids,
            startDate: $startDate,
            endDate: $endDate,
            now: $now,
        );

        $this->seedUserTheo(
            periodId: $periodId,
            unitId: $unitId,
            userId: $theoId,
            otherUserId: $felixId,
            kepalaPoliklinikId: $kepalaPoliklinikId,
            nurseAssessorIds: $nurseAssessorIds,
            taskId: $taskId,
            batchId: $batchId,
            pasienCriteriaId: $pasienId,
            komplainCriteriaId: $komplainId,
            criteria360Ids: $criteria360Ids,
            startDate: $startDate,
            endDate: $endDate,
            now: $now,
        );
    }

    private function seedUserFelix(
        int $periodId,
        int $unitId,
        int $userId,
        int $otherUserId,
        int $kepalaPoliklinikId,
        array $nurseAssessorIds,
        int $taskId,
        int $batchId,
        int $pasienCriteriaId,
        int $komplainCriteriaId,
        array $criteria360Ids,
        Carbon $startDate,
        Carbon $endDate,
        $now,
    ): void {
        // Attendance
        $this->seedAttendanceForUser(
            userId: $userId,
            startDate: $startDate,
            endDate: $endDate,
            attendanceDays: 21,
            totalWorkMinutes: 7830,
            overtimeCount: 3,
            totalLateMinutes: 40
        );

        // Additional Task: Felix = no claim (leave empty intentionally)

        // Metric Import
        if ($batchId > 0 && $pasienCriteriaId > 0 && $komplainCriteriaId > 0) {
            $this->upsertMetricValue($batchId, $periodId, $userId, $pasienCriteriaId, 205, $now);
            $this->upsertMetricValue($batchId, $periodId, $userId, $komplainCriteriaId, 4, $now);
        }

        // 360: self + non-self (Theo as subordinate, optional peer if exists)
        if (!empty($criteria360Ids)) {
            $selfScores = [];
            foreach ($criteria360Ids as $cid) {
                $selfScores[(int) $cid] = 98.0;
            }
            $this->seed360Assessment($periodId, $userId, $userId, 'self', $selfScores, $now);

            // Supervisor L1: Felix dinilai oleh Felix (akun Kepala Unit)
            $supScoresL1 = [];
            foreach ($criteria360Ids as $cid) {
                $supScoresL1[(int) $cid] = 97.0;
            }
            $this->seed360Assessment($periodId, $userId, $userId, 'supervisor', $supScoresL1, $now, assessorLevel: 1);

            // Supervisor L2: Kepala Poliklinik
            if ($kepalaPoliklinikId > 0) {
                $supScoresL2 = [];
                foreach ($criteria360Ids as $cid) {
                    $supScoresL2[(int) $cid] = 96.0;
                }
                $this->seed360Assessment($periodId, $userId, $kepalaPoliklinikId, 'supervisor', $supScoresL2, $now, assessorLevel: 2);
            }

            // Peer: Theo evaluates Felix as peer (not subordinate)
            $peerScores = [];
            foreach ($criteria360Ids as $cid) {
                $peerScores[(int) $cid] = 95.0;
            }
            if ($otherUserId > 0) {
                $this->seed360Assessment($periodId, $userId, $otherUserId, 'peer', $peerScores, $now);
            }

            // Subordinate assessors: nurses assess Felix
            if (!empty($nurseAssessorIds)) {
                $subScores = [];
                foreach ($criteria360Ids as $cid) {
                    $subScores[(int) $cid] = 94.0;
                }
                foreach ($nurseAssessorIds as $nurseId) {
                    $nurseId = (int) $nurseId;
                    if ($nurseId > 0) {
                        $this->seed360Assessment($periodId, $userId, $nurseId, 'subordinate', $subScores, $now);
                    }
                }
            }
        }

        // Reviews
        $this->seedReviewsForUser($periodId, $unitId, $userId, 10, [5, 5, 5, 5, 5, 4, 4, 4, 4, 4]);
    }

    private function seedUserTheo(
        int $periodId,
        int $unitId,
        int $userId,
        int $otherUserId,
        int $kepalaPoliklinikId,
        array $nurseAssessorIds,
        int $taskId,
        int $batchId,
        int $pasienCriteriaId,
        int $komplainCriteriaId,
        array $criteria360Ids,
        Carbon $startDate,
        Carbon $endDate,
        $now,
    ): void {
        // Attendance
        $this->seedAttendanceForUser(
            userId: $userId,
            startDate: $startDate,
            endDate: $endDate,
            attendanceDays: 24,
            totalWorkMinutes: 8700,
            overtimeCount: 2,
            totalLateMinutes: 61
        );

        // Additional Task: Theo = 90 points
        if ($taskId > 0) {
            DB::table('additional_task_claims')->updateOrInsert(
                ['additional_task_id' => $taskId, 'user_id' => $userId],
                [
                    'status' => 'approved',
                    'submitted_at' => $now,
                    'result_file_path' => null,
                    'result_note' => 'Seeder kontribusi Januari 2026.',
                    'awarded_points' => 90,
                    'reviewed_by_id' => null,
                    'reviewed_at' => $now,
                    'review_comment' => null,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]
            );
        }

        // Metric Import
        if ($batchId > 0 && $pasienCriteriaId > 0 && $komplainCriteriaId > 0) {
            $this->upsertMetricValue($batchId, $periodId, $userId, $pasienCriteriaId, 150, $now);
            $this->upsertMetricValue($batchId, $periodId, $userId, $komplainCriteriaId, 6, $now);
        }

        // 360: self + non-self (Felix as supervisor L1; peer is a doctor peer; subordinates are nurses)
        if (!empty($criteria360Ids)) {
            $selfScores = [];
            foreach ($criteria360Ids as $cid) {
                $selfScores[(int) $cid] = 99.0;
            }
            $this->seed360Assessment($periodId, $userId, $userId, 'self', $selfScores, $now);

            // Felix assesses Theo as supervisor (Atasan L1)
            $supScores = [];
            foreach ($criteria360Ids as $cid) {
                $supScores[(int) $cid] = 97.0;
            }
            $this->seed360Assessment($periodId, $userId, $otherUserId, 'supervisor', $supScores, $now, assessorLevel: 1);

            // Supervisor L2: Kepala Poliklinik
            if ($kepalaPoliklinikId > 0) {
                $supScoresL2 = [];
                foreach ($criteria360Ids as $cid) {
                    $supScoresL2[(int) $cid] = 96.0;
                }
                $this->seed360Assessment($periodId, $userId, $kepalaPoliklinikId, 'supervisor', $supScoresL2, $now, assessorLevel: 2);
            }

            // Peer: use existing doctor account(s). Use Felix as peer too (so peer slot is filled without creating new users).
            $peerScores = [];
            foreach ($criteria360Ids as $cid) {
                $peerScores[(int) $cid] = 95.0;
            }
            $this->seed360Assessment($periodId, $userId, $otherUserId, 'peer', $peerScores, $now);

            // Subordinate assessors: nurses assess Theo
            if (!empty($nurseAssessorIds)) {
                $subScores = [];
                foreach ($criteria360Ids as $cid) {
                    $subScores[(int) $cid] = 94.0;
                }
                foreach ($nurseAssessorIds as $nurseId) {
                    $nurseId = (int) $nurseId;
                    if ($nurseId > 0) {
                        $this->seed360Assessment($periodId, $userId, $nurseId, 'subordinate', $subScores, $now);
                    }
                }
            }
        }

        // Reviews
        $this->seedReviewsForUser($periodId, $unitId, $userId, 10, [4, 4, 4, 4, 4, 4, 4, 4, 4, 4]);
    }

    private function resolvePreviousPeriodId(): int
    {
        // Prefer explicit December 2025 by name
        $id = (int) (DB::table('assessment_periods')
            ->whereIn('name', ['Desember 2025', 'December 2025'])
            ->orderByDesc('start_date')
            ->value('id') ?? 0);
        if ($id > 0) {
            return $id;
        }

        // Fallback by month/year
        return (int) (DB::table('assessment_periods')
            ->whereYear('start_date', 2025)
            ->whereMonth('start_date', 12)
            ->orderByDesc('start_date')
            ->value('id') ?? 0);
    }

    private function copyUnitCriteriaWeights(int $unitId, int $targetPeriodId, int $sourcePeriodId): void
    {
        $source = DB::table('unit_criteria_weights')
            ->where('unit_id', $unitId)
            ->where('assessment_period_id', $sourcePeriodId)
            ->where('status', 'active')
            ->get();

        if ($source->isEmpty()) {
            $source = DB::table('unit_criteria_weights')
                ->where('unit_id', $unitId)
                ->where('assessment_period_id', $sourcePeriodId)
                ->where('status', 'archived')
                ->when(Schema::hasColumn('unit_criteria_weights', 'was_active_before'), fn ($q) => $q->where('was_active_before', 1))
                ->get();
        }

        if ($source->isEmpty()) {
            return;
        }

        $hasWasActive = Schema::hasColumn('unit_criteria_weights', 'was_active_before');

        foreach ($source as $row) {
            $values = [
                'weight' => (float) $row->weight,
                'policy_doc_path' => null,
                'policy_note' => null,
                'proposed_by' => null,
                'proposed_note' => null,
                'decided_by' => null,
                'decided_at' => null,
                'decided_note' => null,
                'updated_at' => now(),
                'created_at' => now(),
            ];
            if ($hasWasActive) {
                $values['was_active_before'] = 0;
            }

            DB::table('unit_criteria_weights')->updateOrInsert(
                [
                    'unit_id' => $unitId,
                    'performance_criteria_id' => (int) $row->performance_criteria_id,
                    'assessment_period_id' => $targetPeriodId,
                    'status' => 'active',
                ],
                $values
            );
        }
    }

    private function copyRaterWeights(int $unitId, int $targetPeriodId, int $sourcePeriodId): void
    {
        $source = DB::table('unit_rater_weights')
            ->where('unit_id', $unitId)
            ->where('assessment_period_id', $sourcePeriodId)
            ->where('status', 'active')
            ->get();

        if ($source->isEmpty()) {
            $source = DB::table('unit_rater_weights')
                ->where('unit_id', $unitId)
                ->where('assessment_period_id', $sourcePeriodId)
                ->where('status', 'archived')
                ->when(Schema::hasColumn('unit_rater_weights', 'was_active_before'), fn ($q) => $q->where('was_active_before', 1))
                ->get();
        }

        if ($source->isEmpty()) {
            return;
        }

        $hasWasActive = Schema::hasColumn('unit_rater_weights', 'was_active_before');

        foreach ($source as $row) {
            $values = [
                'weight' => (float) $row->weight,
                'status' => 'active',
                'proposed_by' => null,
                'decided_by' => null,
                'decided_at' => null,
                'decided_note' => null,
                'updated_at' => now(),
                'created_at' => now(),
            ];
            if ($hasWasActive) {
                $values['was_active_before'] = 0;
            }

            DB::table('unit_rater_weights')->updateOrInsert(
                [
                    'assessment_period_id' => $targetPeriodId,
                    'unit_id' => $unitId,
                    'performance_criteria_id' => (int) $row->performance_criteria_id,
                    'assessee_profession_id' => (int) $row->assessee_profession_id,
                    'assessor_type' => (string) $row->assessor_type,
                    'assessor_profession_id' => $row->assessor_profession_id,
                    'assessor_level' => $row->assessor_level,
                ],
                $values
            );
        }
    }

    private function seedAttendanceForUser(
        int $userId,
        Carbon $startDate,
        Carbon $endDate,
        int $attendanceDays,
        int $totalWorkMinutes,
        int $overtimeCount,
        int $totalLateMinutes
    ): void {
        $days = $this->collectWeekdays($startDate, $endDate, $attendanceDays);
        if (empty($days)) {
            return;
        }

        $workMinutes = $this->distributeTotals($attendanceDays, $totalWorkMinutes);
        $lateMinutes = $this->distributeTotals($attendanceDays, $totalLateMinutes);

        foreach ($days as $idx => $day) {
            $dateStr = $day->toDateString();
            $work = $workMinutes[$idx] ?? 0;
            $late = $lateMinutes[$idx] ?? 0;
            $isOvertime = $idx < $overtimeCount;

            $scheduledIn = '07:30:00';
            $scheduledOut = '15:00:00';
            $checkIn = $day->copy()->setTime(7, 30)->addMinutes($late);
            $checkOut = $day->copy()->setTime(15, 0);

            $status = $late > 0 ? AttendanceStatus::TERLAMBAT->value : AttendanceStatus::HADIR->value;

            DB::table('attendances')->updateOrInsert(
                ['user_id' => $userId, 'attendance_date' => $dateStr],
                [
                    'check_in' => $checkIn->toDateTimeString(),
                    'check_out' => $checkOut->toDateTimeString(),
                    'shift_name' => 'Pagi',
                    'scheduled_in' => $scheduledIn,
                    'scheduled_out' => $scheduledOut,
                    'late_minutes' => $late,
                    'early_leave_minutes' => 0,
                    'work_duration_minutes' => $work,
                    'break_duration_minutes' => 30,
                    'extra_break_minutes' => 0,
                    'overtime_end' => $isOvertime ? '17:00:00' : null,
                    'holiday_public' => 0,
                    'holiday_regular' => 0,
                    'overtime_shift' => $isOvertime ? 1 : 0,
                    'attendance_status' => $status,
                    'note' => null,
                    'overtime_note' => $isOvertime ? 'Lembur (seeder Januari 2026)' : null,
                    'source' => 'manual',
                    'import_batch_id' => null,
                    'updated_at' => now(),
                    'created_at' => now(),
                ]
            );
        }
    }

    /** @return array<int, Carbon> */
    private function collectWeekdays(Carbon $start, Carbon $end, int $count): array
    {
        $days = [];
        $cursor = $start->copy();
        while ($cursor->lte($end) && count($days) < $count) {
            if (!$cursor->isWeekend()) {
                $days[] = $cursor->copy();
            }
            $cursor->addDay();
        }
        return $days;
    }

    /** @return array<int, int> */
    private function distributeTotals(int $count, int $total): array
    {
        if ($count <= 0) {
            return [];
        }
        $base = intdiv($total, $count);
        $remainder = $total - ($base * $count);
        $out = array_fill(0, $count, $base);
        for ($i = 0; $i < $remainder; $i++) {
            $out[$i]++;
        }
        return $out;
    }

    private function ensureMetricImportBatch(int $periodId, $now): int
    {
        $batch = DB::table('metric_import_batches')
            ->where('assessment_period_id', $periodId)
            ->where('is_superseded', 0)
            ->first(['id']);

        if ($batch) {
            return (int) $batch->id;
        }

        return (int) DB::table('metric_import_batches')->insertGetId([
            'file_name' => 'metrics_jan_2026_poli_umum.xlsx',
            'assessment_period_id' => $periodId,
            'imported_by' => null,
            'status' => 'processed',
            'is_superseded' => 0,
            'previous_batch_id' => null,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }

    private function ensureAdditionalTask(int $unitId, int $periodId, string $title, Carbon $endDate, $now): int
    {
        $taskId = (int) (DB::table('additional_tasks')
            ->where('unit_id', $unitId)
            ->where('assessment_period_id', $periodId)
            ->where('title', $title)
            ->value('id') ?? 0);

        if ($taskId > 0) {
            return $taskId;
        }

        return (int) DB::table('additional_tasks')->insertGetId([
            'unit_id' => $unitId,
            'assessment_period_id' => $periodId,
            'title' => $title,
            'description' => 'Seeder kontribusi tambahan untuk Januari 2026.',
            'due_date' => $endDate->toDateString(),
            'due_time' => '23:59:00',
            'points' => 90,
            'max_claims' => 10,
            'status' => 'open',
            'created_by' => null,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }

    private function upsertMetricValue(int $batchId, int $periodId, int $userId, int $criteriaId, float $value, $now): void
    {
        DB::table('imported_criteria_values')->updateOrInsert(
            [
                'user_id' => $userId,
                'assessment_period_id' => $periodId,
                'performance_criteria_id' => $criteriaId,
                'is_active' => 1,
            ],
            [
                'import_batch_id' => $batchId,
                'value_numeric' => $value,
                'value_datetime' => null,
                'value_text' => null,
                'superseded_at' => null,
                'superseded_by_batch_id' => null,
                'updated_at' => $now,
                'created_at' => $now,
            ]
        );
    }

    /**
     * Seed 360 submission for one assessee from one assessor.
     *
     * @param array<int,float> $criteriaScores criteria_id => score (1..100)
     */
    private function seed360Assessment(
        int $periodId,
        int $assesseeId,
        int $assessorId,
        string $assessorType,
        array $criteriaScores,
        $now,
        ?int $assessorLevel = null,
    ): void {
        if (empty($criteriaScores)) {
            return;
        }

        $assessorType = (string) $assessorType;
        if ($assessorType === '') {
            return;
        }

        $assessorProfessionId = $this->resolveUserProfessionId($assessorId);

        $where = [
            'assessee_id' => $assesseeId,
            'assessment_period_id' => $periodId,
            'assessor_type' => $assessorType,
            'assessor_id' => $assessorId,
        ];

        $values = [
            'assessor_profession_id' => $assessorProfessionId > 0 ? $assessorProfessionId : null,
            'status' => 'submitted',
            'submitted_at' => $now,
            'updated_at' => $now,
            'created_at' => $now,
        ];

        if (Schema::hasColumn('multi_rater_assessments', 'assessor_level')) {
            $level = 0;
            if ($assessorType === 'supervisor') {
                $level = (int) ($assessorLevel ?? 1);
            }
            $where['assessor_level'] = $level;
            $values['assessor_level'] = $level;
        }

        DB::table('multi_rater_assessments')->updateOrInsert($where, $values);

        $mraId = (int) (DB::table('multi_rater_assessments')
            ->where($where)
            ->value('id') ?? 0);

        if ($mraId <= 0) {
            return;
        }

        foreach ($criteriaScores as $criteriaId => $score) {
            $criteriaId = (int) $criteriaId;
            if ($criteriaId <= 0) {
                continue;
            }
            DB::table('multi_rater_assessment_details')->updateOrInsert(
                [
                    'multi_rater_assessment_id' => $mraId,
                    'performance_criteria_id' => $criteriaId,
                ],
                [
                    'score' => (float) $score,
                    'comment' => null,
                    'updated_at' => $now,
                    'created_at' => $now,
                ]
            );
        }
    }

    private function resolveUserProfessionId(int $userId): int
    {
        return (int) (DB::table('users')->where('id', $userId)->value('profession_id') ?? 0);
    }



    /** @param array<int,int> $ratings */
    private function seedReviewsForUser(int $periodId, int $unitId, int $userId, int $count, array $ratings): void
    {
        $endDate = (string) (DB::table('assessment_periods')->where('id', $periodId)->value('end_date') ?? null);
        $decidedAt = $endDate !== '' ? ($endDate . ' 12:00:00') : now()->toDateTimeString();

        $ratings = array_slice($ratings, 0, $count);
        if (count($ratings) < $count) {
            $ratings = array_pad($ratings, $count, 4);
        }

        foreach ($ratings as $idx => $rating) {
            $registrationRef = 'JAN2026-' . $periodId . '-' . $userId . '-' . ($idx + 1);
            $reviewId = (int) (DB::table('reviews')->where('registration_ref', $registrationRef)->value('id') ?? 0);

            if ($reviewId <= 0) {
                $reviewId = (int) DB::table('reviews')->insertGetId([
                    'registration_ref' => $registrationRef,
                    'unit_id' => $unitId,
                    'overall_rating' => (int) $rating,
                    'comment' => 'Seeder rating Januari 2026.',
                    'patient_name' => 'Pasien Seeder',
                    'contact' => null,
                    'client_ip' => '127.0.0.1',
                    'user_agent' => 'Seeder',
                    'status' => 'approved',
                    'decision_note' => 'Auto-approved (seeder).',
                    'decided_by' => null,
                    'decided_at' => $decidedAt,
                    'updated_at' => now(),
                    'created_at' => now(),
                ]);
            }

            DB::table('review_details')->updateOrInsert(
                ['review_id' => $reviewId, 'medical_staff_id' => $userId],
                [
                    'role' => 'dokter',
                    'rating' => (int) $rating,
                    'comment' => null,
                    'updated_at' => now(),
                    'created_at' => now(),
                ]
            );
        }
    }
}
