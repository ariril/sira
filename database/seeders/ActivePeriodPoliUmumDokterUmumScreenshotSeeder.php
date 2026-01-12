<?php

namespace Database\Seeders;

use App\Enums\AttendanceStatus;
use App\Models\AssessmentPeriod;
use App\Models\User;
use App\Services\AssessmentPeriods\PeriodPerformanceAssessmentService;
use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class ActivePeriodPoliUmumDokterUmumScreenshotSeeder extends Seeder
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
            'additional_tasks',
            'additional_task_claims',
            'metric_import_batches',
            'imported_criteria_values',
            'multi_rater_assessments',
            'multi_rater_assessment_details',
            'reviews',
            'review_details',
            'performance_assessments',
            'performance_assessment_details',
        ];

        foreach ($requiredTables as $t) {
            if (!Schema::hasTable($t)) {
                $this->command?->warn("Skipping ActivePeriodPoliUmumDokterUmumScreenshotSeeder: table '{$t}' not found.");
                return;
            }
        }

        /** @var AssessmentPeriod|null $period */
        $period = AssessmentPeriod::query()
            ->where('status', 'active')
            ->orderByDesc('id')
            ->first();

        if (!$period) {
            $this->command?->warn('No active assessment period found.');
            return;
        }

        $unitId = (int) (DB::table('units')->where('slug', 'poliklinik-umum')->value('id') ?? 0);
        if ($unitId <= 0) {
            $this->command?->warn('Unit with slug poliklinik-umum not found.');
            return;
        }

        $professionId = (int) (DB::table('professions')->where('code', 'DOK-UM')->value('id') ?? 0);
        if ($professionId <= 0) {
            $this->command?->warn('Profession with code DOK-UM not found.');
            return;
        }

        $users = User::query()
            ->role(User::ROLE_PEGAWAI_MEDIS)
            ->where('unit_id', $unitId)
            ->where('profession_id', $professionId)
            ->get(['id']);

        if ($users->isEmpty()) {
            $this->command?->warn('No users found for Poliklinik Umum + Dokter Umum.');
            return;
        }

        $userIds = $users->pluck('id')->map(fn ($v) => (int) $v)->all();

        $criteriaId = fn (string $name) => (int) (DB::table('performance_criterias')->where('name', $name)->value('id') ?? 0);

        $kedis360Id = $criteriaId('Kedisiplinan (360)');
        $kerjasama360Id = $criteriaId('Kerjasama (360)');
        $pasienId = $criteriaId('Jumlah Pasien Ditangani');
        $komplainId = $criteriaId('Jumlah Komplain Pasien');

        if ($kedis360Id <= 0 || $kerjasama360Id <= 0) {
            $this->command?->warn('Missing 360 criterias: pastikan performance_criterias berisi Kedisiplinan (360) dan Kerjasama (360).');
            return;
        }
        if ($pasienId <= 0 || $komplainId <= 0) {
            $this->command?->warn('Missing metric criterias: pastikan performance_criterias berisi Jumlah Pasien Ditangani dan Jumlah Komplain Pasien.');
            return;
        }

        $startDate = $period->start_date ? Carbon::parse((string) $period->start_date) : Carbon::now()->startOfMonth();
        $endDate = $period->end_date ? Carbon::parse((string) $period->end_date) : Carbon::now()->endOfMonth();
        $now = now();

        // 1) Attendance (Absensi)
        // Seed 10 working days from period start (or until end).
        $attendanceDays = [];
        $cursor = $startDate->copy();
        while (count($attendanceDays) < 10 && $cursor->lte($endDate)) {
            if (!$cursor->isWeekend()) {
                $attendanceDays[] = $cursor->copy();
            }
            $cursor->addDay();
        }

        foreach ($userIds as $idx => $uid) {
            $uid = (int) $uid;
            $lateBase = ($idx % 3) * 3; // 0,3,6 menit
            foreach ($attendanceDays as $dayIdx => $day) {
                $dateStr = $day->toDateString();
                $scheduledIn = '07:30:00';
                $scheduledOut = '15:00:00';

                $late = ($dayIdx % 4 === 0) ? $lateBase : 0;
                $checkIn = $day->copy()->setTime(7, 30)->addMinutes($late);
                $checkOut = $day->copy()->setTime(15, 0);

                $isOvertime = ($dayIdx % 5 === 0);

                DB::table('attendances')->updateOrInsert(
                    ['user_id' => $uid, 'attendance_date' => $dateStr],
                    [
                        'check_in' => $checkIn->toDateTimeString(),
                        'check_out' => $checkOut->toDateTimeString(),
                        'shift_name' => 'Pagi',
                        'scheduled_in' => $scheduledIn,
                        'scheduled_out' => $scheduledOut,
                        'late_minutes' => $late,
                        'early_leave_minutes' => 0,
                        'work_duration_minutes' => 450,
                        'break_duration_minutes' => 30,
                        'extra_break_minutes' => 0,
                        'overtime_end' => $isOvertime ? '17:00:00' : null,
                        'holiday_public' => 0,
                        'holiday_regular' => 0,
                        'overtime_shift' => $isOvertime ? 1 : 0,
                        'attendance_status' => AttendanceStatus::HADIR->value,
                        'note' => null,
                        'overtime_note' => $isOvertime ? 'Lembur untuk keperluan screenshot.' : null,
                        'source' => 'manual',
                        'import_batch_id' => null,
                        'updated_at' => $now,
                        'created_at' => $now,
                    ]
                );
            }
        }

        // 2) Tugas Tambahan (Contribution)
        $taskTitle = 'Tugas Tambahan (Screenshot) - ' . ($period->name ?? ('Periode #' . (int) $period->id));
        $task = DB::table('additional_tasks')
            ->where('unit_id', $unitId)
            ->where('assessment_period_id', (int) $period->id)
            ->where('title', $taskTitle)
            ->first(['id']);

        $taskId = $task ? (int) $task->id : (int) DB::table('additional_tasks')->insertGetId([
            'unit_id' => $unitId,
            'assessment_period_id' => (int) $period->id,
            'title' => $taskTitle,
            'description' => 'Seeder untuk demo/screenshot.',
            'policy_doc_path' => null,
            'start_date' => $startDate->toDateString(),
            'due_date' => $endDate->toDateString(),
            'start_time' => '00:00:00',
            'due_time' => '23:59:00',
            'bonus_amount' => null,
            'points' => 5,
            'max_claims' => 10,
            'cancel_window_hours' => 24,
            'default_penalty_type' => 'none',
            'default_penalty_value' => 0,
            'penalty_base' => 'task_bonus',
            'status' => 'open',
            'created_by' => null,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        foreach ($userIds as $idx => $uid) {
            $uid = (int) $uid;
            $awarded = 5 + ($idx % 3); // 5..7
            DB::table('additional_task_claims')->updateOrInsert(
                ['additional_task_id' => $taskId, 'user_id' => $uid],
                [
                    'status' => 'approved',
                    'claimed_at' => $now,
                    'completed_at' => $now,
                    'cancelled_at' => null,
                    'cancelled_by' => null,
                    'cancel_deadline_at' => null,
                    'cancel_reason' => null,
                    'penalty_type' => 'none',
                    'penalty_value' => 0,
                    'penalty_base' => 'task_bonus',
                    'penalty_applied' => 0,
                    'penalty_applied_at' => null,
                    'penalty_amount' => null,
                    'penalty_note' => null,
                    'result_file_path' => null,
                    'result_note' => 'Auto-approved for screenshot.',
                    'awarded_points' => $awarded,
                    'awarded_bonus_amount' => null,
                    'reviewed_by_id' => null,
                    'reviewed_at' => $now,
                    'review_comment' => null,
                    'is_violation' => 0,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]
            );
        }

        // 3) Metric Import (Patients & Complaints)
        $batchFile = 'screenshot_metrics_period_' . (int) $period->id . '.xlsx';
        $batch = DB::table('metric_import_batches')->where('file_name', $batchFile)->first(['id']);
        $batchId = $batch ? (int) $batch->id : (int) DB::table('metric_import_batches')->insertGetId([
            'file_name' => $batchFile,
            'assessment_period_id' => (int) $period->id,
            'imported_by' => null,
            'status' => 'processed',
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        foreach ($userIds as $idx => $uid) {
            $uid = (int) $uid;
            $patients = 120 + ($idx * 10);
            $complaints = max(0, 2 - ($idx % 3));

            DB::table('imported_criteria_values')->updateOrInsert(
                ['user_id' => $uid, 'assessment_period_id' => (int) $period->id, 'performance_criteria_id' => $pasienId],
                [
                    'import_batch_id' => $batchId,
                    'value_numeric' => $patients,
                    'value_datetime' => null,
                    'value_text' => null,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]
            );

            DB::table('imported_criteria_values')->updateOrInsert(
                ['user_id' => $uid, 'assessment_period_id' => (int) $period->id, 'performance_criteria_id' => $komplainId],
                [
                    'import_batch_id' => $batchId,
                    'value_numeric' => $complaints,
                    'value_datetime' => null,
                    'value_text' => null,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]
            );
        }

        // 4) Rating (Reviews)
        // Create 1 approved review per doctor with one detail row.
        $decidedAt = $startDate->copy()->addDays(7);
        if ($decidedAt->gt($endDate)) {
            $decidedAt = $endDate->copy();
        }

        foreach ($userIds as $uid) {
            $uid = (int) $uid;
            $registrationRef = 'SS-' . (int) $period->id . '-' . $unitId . '-' . $uid;

            $review = DB::table('reviews')->where('registration_ref', $registrationRef)->first(['id']);
            $reviewId = $review ? (int) $review->id : (int) DB::table('reviews')->insertGetId([
                'registration_ref' => $registrationRef,
                'unit_id' => $unitId,
                'overall_rating' => 5,
                'comment' => 'Seeder rating untuk screenshot.',
                'status' => 'approved',
                'decision_note' => 'Auto-approved for screenshot.',
                'decided_by' => null,
                'decided_at' => $decidedAt->toDateTimeString(),
                'patient_name' => 'Pasien Demo',
                'contact' => null,
                'client_ip' => '127.0.0.1',
                'user_agent' => 'Seeder',
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            DB::table('review_details')->updateOrInsert(
                ['review_id' => $reviewId, 'medical_staff_id' => $uid],
                [
                    'role' => 'dokter',
                    'rating' => 5,
                    'comment' => 'Pelayanan sangat baik (demo).',
                    'created_at' => $now,
                    'updated_at' => $now,
                ]
            );
        }

        // 5) 360 (Multi-rater) — create self assessment per doctor with submitted status.
        foreach ($userIds as $idx => $uid) {
            $uid = (int) $uid;
            $mraId = (int) DB::table('multi_rater_assessments')->updateOrInsert(
                [
                    'assessee_id' => $uid,
                    'assessment_period_id' => (int) $period->id,
                    'assessor_type' => 'self',
                    'assessor_id' => $uid,
                    'assessor_profession_id' => $professionId,
                ],
                [
                    'status' => 'submitted',
                    'submitted_at' => $now,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]
            ) ? (int) (DB::table('multi_rater_assessments')
                ->where('assessee_id', $uid)
                ->where('assessment_period_id', (int) $period->id)
                ->where('assessor_type', 'self')
                ->where('assessor_id', $uid)
                ->where('assessor_profession_id', $professionId)
                ->value('id')) : 0;

            if ($mraId <= 0) {
                continue;
            }

            $kedisScore = 88 + ($idx % 5); // 88..92
            $kerjaScore = 85 + (($idx + 2) % 6); // 85..90

            DB::table('multi_rater_assessment_details')->updateOrInsert(
                ['multi_rater_assessment_id' => $mraId, 'performance_criteria_id' => $kedis360Id],
                [
                    'score' => $kedisScore,
                    'comment' => 'Demo 360 (kedisiplinan).',
                    'created_at' => $now,
                    'updated_at' => $now,
                ]
            );

            DB::table('multi_rater_assessment_details')->updateOrInsert(
                ['multi_rater_assessment_id' => $mraId, 'performance_criteria_id' => $kerjasama360Id],
                [
                    'score' => $kerjaScore,
                    'comment' => 'Demo 360 (kerjasama).',
                    'created_at' => $now,
                    'updated_at' => $now,
                ]
            );
        }

        // 6) Recalculate stored PerformanceAssessment + details for this group
        /** @var PeriodPerformanceAssessmentService $svc */
        $svc = app(PeriodPerformanceAssessmentService::class);
        $svc->recalculateForGroup((int) $period->id, $unitId, $professionId, $userIds);

        $this->command?->info('Seeded KPI sources + recalculated PerformanceAssessment for Poliklinik Umum / Dokter Umum on active period: ' . ($period->name ?? ('#' . (int) $period->id)) . '.');
    }
}
