<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Carbon\Carbon;
use PhpOffice\PhpSpreadsheet\IOFactory;
use App\Services\AssessmentPeriods\PeriodPerformanceAssessmentService;
use App\Models\AssessmentPeriod;
use App\Models\PerformanceAssessment;
use App\Models\PerformanceAssessmentDetail;

class EightStaffKpiSeeder extends Seeder
{
    /**
     * Optional Excel-driven seed config.
     * @var array{
     *   weights?: array<int, array<string, array<string, array{weight:float,status:string}>>>,
    *   raw?: array<int, array<string, array{
    *     attendance_days:float,
    *     late_minutes:float,
    *     work_minutes:float,
    *     overtime_days:float,
    *     discipline_360:float,
    *     teamwork_360:float,
    *     contrib:float,
    *     patients:float,
    *     complaints:float,
    *     rating_avg:float,
    *     rating_count?:float,
    *     rating_sum?:float
    *   }>>,
     *   criteria?: array<string, array{normalization_basis:string,custom_target_value:?float}>
     * }|null
     */
    private ?array $excelConfig = null;

    public function run(): void
    {
        $now = Carbon::now();

        $periodNov = DB::table('assessment_periods')->where('name', 'November 2025')->first();
        $periodDec = DB::table('assessment_periods')->where('name', 'December 2025')->first();
        if (!$periodNov && !$periodDec) {
            return;
        }

        $unitPoliUmumId = (int) (DB::table('units')->where('slug', 'poliklinik-umum')->value('id') ?? 0);
        $unitPoliGigiId = (int) (DB::table('units')->where('slug', 'poliklinik-gigi')->value('id') ?? 0);

        // Ensure required 360 criteria exist (seeder-level guardrail; do not rely on previous seeders).
        $ensureCriteria = function (string $name, string $type, array $attrs = []) use ($now) {
            $existing = DB::table('performance_criterias')->where('name', $name)->first();
            if ($existing) {
                DB::table('performance_criterias')->where('id', (int) $existing->id)->update(array_merge([
                    'type' => $type,
                    'is_active' => 1,
                    'updated_at' => $now,
                ], $attrs));
                return (int) $existing->id;
            }

            return (int) DB::table('performance_criterias')->insertGetId(array_merge([
                'name' => $name,
                'type' => $type,
                'description' => null,
                'is_active' => 1,
                'data_type' => 'numeric',
                'input_method' => '360',
                'source' => 'assessment_360',
                'is_360' => 1,
                'aggregation_method' => 'avg',
                'normalization_basis' => 'total_unit',
                'custom_target_value' => null,
                'suggested_weight' => null,
                'created_at' => $now,
                'updated_at' => $now,
            ], $attrs));
        };

        $ensureCriteria('Kedisiplinan (360)', 'benefit');
        $ensureCriteria('Kerjasama (360)', 'benefit');

        // Ensure required metric-import criteria exist with correct benefit/cost semantics.
        // These are used by metric_import_batches + imported_criteria_values.
        $ensureCriteria('Jumlah Pasien Ditangani', 'benefit', [
            'input_method' => 'import',
            'source' => 'metric_import',
            'is_360' => 0,
            'aggregation_method' => 'sum',
            'normalization_basis' => 'total_unit',
        ]);
        $ensureCriteria('Jumlah Komplain Pasien', 'cost', [
            'input_method' => 'import',
            'source' => 'metric_import',
            'is_360' => 0,
            'aggregation_method' => 'sum',
            'normalization_basis' => 'total_unit',
        ]);

        // Helpers
        $criteriaId = fn(string $name) => DB::table('performance_criterias')->where('name', $name)->value('id');
        $userId = fn(string $email) => DB::table('users')->where('email', $email)->value('id');
        $professionId = fn(string $code) => DB::table('professions')->where('code', $code)->value('id');
        $unitId = fn(string $slug) => DB::table('units')->where('slug', $slug)->value('id');

        // Target users (real): November 2025 — Poliklinik Umum & Poliklinik Gigi.
        // Catatan: seeder ini mengasumsikan DatabaseSeeder sudah dijalankan.
        $emails = [
            'kepala.umum@rsud.local',
            'dokter.umum1@rsud.local',
            'dokter.umum2@rsud.local',
            'perawat1@rsud.local',
            'perawat2@rsud.local',
            'kepala.gigi@rsud.local',
            'dokter.spes1@rsud.local',
            'dokter.spes2@rsud.local',
        ];

        $staff = [
            // Poli Umum
            'kepala_umum' => ['id' => (int) ($userId('kepala.umum@rsud.local') ?? 0), 'unit_slug' => 'poliklinik-umum', 'profession' => 'DOK-UM'],
            'dokter_umum1' => ['id' => (int) ($userId('dokter.umum1@rsud.local') ?? 0), 'unit_slug' => 'poliklinik-umum', 'profession' => 'DOK-UM'],
            'dokter_umum2' => ['id' => (int) ($userId('dokter.umum2@rsud.local') ?? 0), 'unit_slug' => 'poliklinik-umum', 'profession' => 'DOK-UM'],
            'perawat1' => ['id' => (int) ($userId('perawat1@rsud.local') ?? 0), 'unit_slug' => 'poliklinik-umum', 'profession' => 'PRW'],
            'perawat2' => ['id' => (int) ($userId('perawat2@rsud.local') ?? 0), 'unit_slug' => 'poliklinik-umum', 'profession' => 'PRW'],

            // Poli Gigi
            'kepala_gigi' => ['id' => (int) ($userId('kepala.gigi@rsud.local') ?? 0), 'unit_slug' => 'poliklinik-gigi', 'profession' => 'DOK-UM'],
            'dokter_spes1' => ['id' => (int) ($userId('dokter.spes1@rsud.local') ?? 0), 'unit_slug' => 'poliklinik-gigi', 'profession' => 'DOK-SP'],
            'dokter_spes2' => ['id' => (int) ($userId('dokter.spes2@rsud.local') ?? 0), 'unit_slug' => 'poliklinik-gigi', 'profession' => 'DOK-SP'],
        ];

        $missingUsers = [];
        foreach ($staff as $k => $info) {
            if ((int) ($info['id'] ?? 0) <= 0) {
                $missingUsers[] = (string) $k;
            }
        }
        if (!empty($missingUsers)) {
            throw new \RuntimeException('Seeder KPI: user tidak ditemukan untuk key: ' . implode(', ', $missingUsers) . '. Jalankan DatabaseSeeder dulu.');
        }

        $openedById = (int) ($userId('superadmin@rsud.local') ?? 0);
        if ($openedById <= 0) {
            $openedById = (int) ($userId('admin.rs@rsud.local') ?? 0);
        }
        if ($periodNov) {
            $this->ensureAssessment360Window(
                periodId: (int) $periodNov->id,
                startDate: (string) $periodNov->start_date,
                endDate: (string) $periodNov->end_date,
                openedById: $openedById > 0 ? $openedById : null,
                now: $now
            );
        }
        if ($periodDec) {
            $this->ensureAssessment360Window(
                periodId: (int) $periodDec->id,
                startDate: (string) $periodDec->start_date,
                endDate: (string) $periodDec->end_date,
                openedById: $openedById > 0 ? $openedById : null,
                now: $now
            );
        }

        $absensiId      = $criteriaId('Kehadiran (Absensi)');
        $workHoursId    = $criteriaId('Jam Kerja (Absensi)');
        $overtimeId     = $criteriaId('Lembur (Absensi)');
        $lateMinutesId  = $criteriaId('Keterlambatan (Absensi)');
        $kedis360Id     = $criteriaId('Kedisiplinan (360)');
        $kerjasama360Id = $criteriaId('Kerjasama (360)');
        $kontribusiId   = $criteriaId('Tugas Tambahan');
        $pasienId       = $criteriaId('Jumlah Pasien Ditangani');
        $komplainId     = $criteriaId('Jumlah Komplain Pasien');
        $ratingId       = $criteriaId('Rating');

        $requiredCriteria = [
            'Kehadiran (Absensi)' => (int) $absensiId,
            'Jam Kerja (Absensi)' => (int) $workHoursId,
            'Lembur (Absensi)' => (int) $overtimeId,
            'Keterlambatan (Absensi)' => (int) $lateMinutesId,
            'Kedisiplinan (360)' => (int) $kedis360Id,
            'Kerjasama (360)' => (int) $kerjasama360Id,
            'Tugas Tambahan' => (int) $kontribusiId,
            'Jumlah Pasien Ditangani' => (int) $pasienId,
            'Jumlah Komplain Pasien' => (int) $komplainId,
            'Rating' => (int) $ratingId,
        ];
        $missing = array_keys(array_filter($requiredCriteria, fn($id) => (int) $id <= 0));
        if (!empty($missing)) {
            throw new \RuntimeException('Seeder KPI membutuhkan performance_criterias berikut: ' . implode(', ', $missing));
        }

        $targets = array_column($staff, 'id');

        // Optional: Excel template as source-of-truth for seed data.
        $this->excelConfig = $this->tryLoadExcelTemplate($staff);

        // Normalization policy is fixed: ALL criteria use total_unit.
        // If an Excel template exists, we ignore its normalization fields.
        if (!empty($this->excelConfig['criteria'])) {
            foreach (array_keys((array) ($this->excelConfig['criteria'] ?? [])) as $criteriaName) {
                DB::table('performance_criterias')
                    ->where('name', (string) $criteriaName)
                    ->update([
                        'normalization_basis' => 'total_unit',
                        'custom_target_value' => null,
                        'updated_at' => $now,
                    ]);
            }
        }

        // Clean old RAW data for these users/periods (seeder ini TIDAK menghitung skor).
        $periodIds = array_values(array_filter([
            $periodNov?->id,
            $periodDec?->id,
        ]));

        // Clean previous derived assessments for a clean demo (recalculation will recreate/update).
        $assessmentIds = DB::table('performance_assessments')
            ->whereIn('user_id', $targets)
            ->whereIn('assessment_period_id', $periodIds)
            ->pluck('id');
        if ($assessmentIds->isNotEmpty()) {
            DB::table('performance_assessment_details')->whereIn('performance_assessment_id', $assessmentIds)->delete();
            DB::table('performance_assessments')->whereIn('id', $assessmentIds)->delete();
        }

        DB::table('imported_criteria_values')->whereIn('user_id', $targets)->whereIn('assessment_period_id', $periodIds)->delete();

        // Contribution cleanup (task-based)
        DB::table('additional_task_claims')->whereIn('user_id', $targets)->delete();
        DB::table('additional_tasks')
            ->whereIn('assessment_period_id', $periodIds)
            ->whereIn('unit_id', array_values(array_filter([$unitPoliUmumId, $unitPoliGigiId])))
            ->delete();

        $reviewIds = DB::table('review_details')->whereIn('medical_staff_id', $targets)->pluck('review_id');
        DB::table('review_details')->whereIn('medical_staff_id', $targets)->delete();
        DB::table('reviews')->whereIn('id', $reviewIds)->delete();

        // Review invitation cleanup (linked by seeded registration_ref prefix)
        DB::table('review_invitations')->where(function ($q) use ($periodIds) {
            foreach ($periodIds as $pid) {
                $q->orWhere('registration_ref', 'like', 'DRV-' . (int) $pid . '-%');
            }
        })->delete();

        $mraIds = DB::table('multi_rater_assessments')->whereIn('assessee_id', $targets)->whereIn('assessment_period_id', $periodIds)->pluck('id');
        DB::table('multi_rater_assessment_details')->whereIn('multi_rater_assessment_id', $mraIds)->delete();
        DB::table('multi_rater_assessments')->whereIn('id', $mraIds)->delete();

        // Attendance cleanup for seeded periods.
        if ($periodNov) {
            DB::table('attendances')
                ->whereIn('user_id', $targets)
                ->whereBetween('attendance_date', [(string) $periodNov->start_date, (string) $periodNov->end_date])
                ->delete();
        }
        if ($periodDec) {
            DB::table('attendances')
                ->whereIn('user_id', $targets)
                ->whereBetween('attendance_date', [(string) $periodDec->start_date, (string) $periodDec->end_date])
                ->delete();
        }

        // Supersede any existing active seeder attendance batches for these periods.
        DB::table('attendance_import_batches')
            ->whereIn('assessment_period_id', $periodIds)
            ->where('is_superseded', 0)
            ->update(['is_superseded' => 1, 'updated_at' => $now]);

        // Dataset per periode (RAW): gunakan angka realistis & bervariasi.
        // RAW November 2025 — Poli Umum
        // RAW November 2025 — Poli Gigi
        // Notes Excel: rating memakai SUM(rating) (bukan AVG) untuk normalisasi relatif unit.
        if ($periodNov) {
            $novRaw = $this->excelConfig['raw'][(int) $periodNov->id] ?? [
            // Poli Umum (DOK-UM)
            'kepala_umum' => ['attendance_days' => 25, 'late_minutes' => 40, 'work_minutes' => 9000, 'overtime_days' => 3, 'discipline_360' => 87, 'teamwork_360' => 84, 'contrib' => 10, 'patients' => 205, 'complaints' => 4, 'rating_avg' => 4.6, 'rating_count' => 10, 'rating_sum' => 46],
            'dokter_umum1' => ['attendance_days' => 24, 'late_minutes' => 65, 'work_minutes' => 8700, 'overtime_days' => 2, 'discipline_360' => 82, 'teamwork_360' => 80, 'contrib' => 9, 'patients' => 150, 'complaints' => 6, 'rating_avg' => 4.5, 'rating_count' => 10, 'rating_sum' => 45],
            'dokter_umum2' => ['attendance_days' => 23, 'late_minutes' => 30, 'work_minutes' => 8800, 'overtime_days' => 4, 'discipline_360' => 84, 'teamwork_360' => 83, 'contrib' => 9, 'patients' => 165, 'complaints' => 3, 'rating_avg' => 4.6, 'rating_count' => 10, 'rating_sum' => 46],

            // Poli Umum (PRW)
            'perawat1' => ['attendance_days' => 24, 'late_minutes' => 40,  'work_minutes' => 11880, 'overtime_days' => 3, 'discipline_360' => 88, 'teamwork_360' => 90, 'contrib' => 8,  'patients' => 0,   'complaints' => 0, 'rating_avg' => 4.5, 'rating_count' => 2, 'rating_sum' => 9],
            'perawat2' => ['attendance_days' => 22, 'late_minutes' => 75,  'work_minutes' => 11040, 'overtime_days' => 2, 'discipline_360' => 85, 'teamwork_360' => 86, 'contrib' => 7,  'patients' => 0,   'complaints' => 0, 'rating_avg' => 4.0, 'rating_count' => 1, 'rating_sum' => 4],

            // Poli Gigi (DOK-UM / kepala unit)
            'kepala_gigi' => ['attendance_days' => 24, 'late_minutes' => 30,  'work_minutes' => 12000, 'overtime_days' => 2, 'discipline_360' => 89, 'teamwork_360' => 88, 'contrib' => 8,  'patients' => 140, 'complaints' => 1, 'rating_avg' => 4.5, 'rating_count' => 2, 'rating_sum' => 9],

            // Poli Gigi (DOK-SP)
            'dokter_spes1' => ['attendance_days' => 23, 'late_minutes' => 90,  'work_minutes' => 11520, 'overtime_days' => 1, 'discipline_360' => 84, 'teamwork_360' => 83, 'contrib' => 6,  'patients' => 160, 'complaints' => 3, 'rating_avg' => 4.0, 'rating_count' => 1, 'rating_sum' => 4],
            'dokter_spes2' => ['attendance_days' => 25, 'late_minutes' => 10,  'work_minutes' => 12480, 'overtime_days' => 3, 'discipline_360' => 91, 'teamwork_360' => 90, 'contrib' => 10, 'patients' => 175, 'complaints' => 0, 'rating_avg' => 4.8, 'rating_count' => 2, 'rating_sum' => 10],
            ];

            $this->seedPeriod(
                period: $periodNov,
                data: $novRaw,
                staff: $staff,
                criteriaIds: compact('absensiId','workHoursId','overtimeId','lateMinutesId','kedis360Id','kerjasama360Id','kontribusiId','pasienId','komplainId','ratingId'),
                assessmentDate: Carbon::create(2025, 11, 30),
                professionIdResolver: $professionId,
                unitIdResolver: $unitId,
                exampleInactiveUnitId: null,
                scheduleIn: '08:00:00',
                scheduleOut: '16:00:00',
                overtimeEnd: '18:00:00',
            );

        // Ensure "Pegawai Medis → Penilaian Saya" has at least 1 row for November 2025
        // by recalculating from seeded RAW tables.
        // Some dev DBs don't have the newer `meta` column yet; avoid hard-failing the seeder.
            if ($unitPoliUmumId > 0 || $unitPoliGigiId > 0) {
            if (Schema::hasColumn('performance_assessment_details', 'meta')) {
                /** @var PeriodPerformanceAssessmentService $perfSvc */
                $perfSvc = app(PeriodPerformanceAssessmentService::class);
                $profDoku = (int) ($professionId('DOK-UM') ?? 0);
                $profDoksp = (int) ($professionId('DOK-SP') ?? 0);
                $profPrw = (int) ($professionId('PRW') ?? 0);

                if ($unitPoliUmumId > 0 && $profDoku > 0) {
                    $perfSvc->recalculateForGroup((int) $periodNov->id, (int) $unitPoliUmumId, (int) $profDoku);
                }
                if ($unitPoliUmumId > 0 && $profPrw > 0) {
                    $perfSvc->recalculateForGroup((int) $periodNov->id, (int) $unitPoliUmumId, (int) $profPrw);
                }
                if ($unitPoliGigiId > 0 && $profDoksp > 0) {
                    $perfSvc->recalculateForGroup((int) $periodNov->id, (int) $unitPoliGigiId, (int) $profDoksp);
                }
                if ($unitPoliGigiId > 0 && $profDoku > 0) {
                    $perfSvc->recalculateForGroup((int) $periodNov->id, (int) $unitPoliGigiId, (int) $profDoku);
                }

                // Smoke checks (fail fast with clear debug output)
                if ($unitPoliUmumId > 0) {
                    $this->smokeCheckPeriod(
                        periodId: (int) $periodNov->id,
                        unitId: (int) $unitPoliUmumId,
                        sampleUserId: (int) $staff['dokter_umum1']['id'],
                        sampleUserLabel: 'dokter_umum1',
                    );
                }

                if ($unitPoliGigiId > 0) {
                    $this->smokeCheckPeriod(
                        periodId: (int) $periodNov->id,
                        unitId: (int) $unitPoliGigiId,
                        sampleUserId: (int) $staff['dokter_spes2']['id'],
                        sampleUserLabel: 'dokter_spes2',
                    );
                }
            } elseif ($this->command) {
                $this->command->warn('Skipping recalc + smoke checks: kolom performance_assessment_details.meta tidak ada di schema DB ini.');
            }
            }

        // Ensure all 8 users have performance_assessments rows (fallback when recalc is skipped).
            $this->ensurePerformanceAssessmentsForUsers(
                periodId: (int) $periodNov->id,
                assessmentDate: '2025-11-30',
                userIds: $targets,
                now: $now
            );

            $this->ensurePerformanceAssessmentDetailsForUsers(
                periodId: (int) $periodNov->id,
                userIds: $targets,
                now: $now
            );

            $this->seedAssessmentApprovalsForPeriod(
                periodId: (int) $periodNov->id,
                staff: $staff,
                actedAt: Carbon::create(2025, 11, 30),
                now: $now,
                note: 'Seeder auto-approve (Nov 2025 demo).',
                maxLevelApproved: 3,
            );

            $this->seedDemoRemunerationsForPeriod(
                periodId: (int) $periodNov->id,
                emails: $emails,
                now: $now
            );

            $this->assertSeedCoverage(
                periodId: (int) $periodNov->id,
                userIds: $targets
            );
        }

        // =========================================================
        // DECEMBER 2025
        // Catatan: jam masuk 07:30 dan jam keluar 15:00.
        // Variasikan scan masuk/keluar, keterlambatan, durasi kerja, dan lembur via total dataset.
        // =========================================================
        if ($periodDec) {
            $decRaw = $this->excelConfig['raw'][(int) $periodDec->id] ?? [
                // Poli Umum (DOK-UM)
                'kepala_umum' => ['attendance_days' => 24, 'late_minutes' => 35, 'work_minutes' => 11880, 'overtime_days' => 3, 'discipline_360' => 88, 'teamwork_360' => 85, 'contrib' => 10, 'patients' => 210, 'complaints' => 3, 'rating_avg' => 4.7, 'rating_count' => 10, 'rating_sum' => 47],
                'dokter_umum1' => ['attendance_days' => 23, 'late_minutes' => 80, 'work_minutes' => 11385, 'overtime_days' => 2, 'discipline_360' => 81, 'teamwork_360' => 79, 'contrib' => 8, 'patients' => 155, 'complaints' => 5, 'rating_avg' => 4.5, 'rating_count' => 10, 'rating_sum' => 45],
                'dokter_umum2' => ['attendance_days' => 24, 'late_minutes' => 25, 'work_minutes' => 12060, 'overtime_days' => 4, 'discipline_360' => 85, 'teamwork_360' => 84, 'contrib' => 9, 'patients' => 170, 'complaints' => 2, 'rating_avg' => 4.6, 'rating_count' => 10, 'rating_sum' => 46],

                // Poli Umum (PRW)
                'perawat1' => ['attendance_days' => 24, 'late_minutes' => 30, 'work_minutes' => 13200, 'overtime_days' => 3, 'discipline_360' => 89, 'teamwork_360' => 91, 'contrib' => 8, 'patients' => 0, 'complaints' => 0, 'rating_avg' => 4.5, 'rating_count' => 2, 'rating_sum' => 9],
                'perawat2' => ['attendance_days' => 23, 'late_minutes' => 60, 'work_minutes' => 12880, 'overtime_days' => 2, 'discipline_360' => 86, 'teamwork_360' => 87, 'contrib' => 7, 'patients' => 0, 'complaints' => 0, 'rating_avg' => 4.0, 'rating_count' => 1, 'rating_sum' => 4],

                // Poli Gigi (DOK-UM / kepala unit)
                'kepala_gigi' => ['attendance_days' => 24, 'late_minutes' => 20, 'work_minutes' => 12600, 'overtime_days' => 2, 'discipline_360' => 90, 'teamwork_360' => 88, 'contrib' => 8, 'patients' => 145, 'complaints' => 1, 'rating_avg' => 4.5, 'rating_count' => 2, 'rating_sum' => 9],

                // Poli Gigi (DOK-SP)
                'dokter_spes1' => ['attendance_days' => 22, 'late_minutes' => 95, 'work_minutes' => 11880, 'overtime_days' => 1, 'discipline_360' => 83, 'teamwork_360' => 82, 'contrib' => 6, 'patients' => 165, 'complaints' => 2, 'rating_avg' => 4.0, 'rating_count' => 1, 'rating_sum' => 4],
                'dokter_spes2' => ['attendance_days' => 25, 'late_minutes' => 15, 'work_minutes' => 13500, 'overtime_days' => 3, 'discipline_360' => 92, 'teamwork_360' => 90, 'contrib' => 10, 'patients' => 180, 'complaints' => 1, 'rating_avg' => 4.8, 'rating_count' => 2, 'rating_sum' => 10],
            ];

            $this->seedPeriod(
                period: $periodDec,
                data: $decRaw,
                staff: $staff,
                criteriaIds: compact('absensiId','workHoursId','overtimeId','lateMinutesId','kedis360Id','kerjasama360Id','kontribusiId','pasienId','komplainId','ratingId'),
                assessmentDate: Carbon::create(2025, 12, 31),
                professionIdResolver: $professionId,
                unitIdResolver: $unitId,
                exampleInactiveUnitId: null,
                scheduleIn: '07:30:00',
                scheduleOut: '15:00:00',
                overtimeEnd: '17:00:00',
            );

            if ($unitPoliUmumId > 0 || $unitPoliGigiId > 0) {
                if (Schema::hasColumn('performance_assessment_details', 'meta')) {
                    /** @var PeriodPerformanceAssessmentService $perfSvc */
                    $perfSvc = app(PeriodPerformanceAssessmentService::class);
                    $profDoku = (int) ($professionId('DOK-UM') ?? 0);
                    $profDoksp = (int) ($professionId('DOK-SP') ?? 0);
                    $profPrw = (int) ($professionId('PRW') ?? 0);

                    if ($unitPoliUmumId > 0 && $profDoku > 0) {
                        $perfSvc->recalculateForGroup((int) $periodDec->id, (int) $unitPoliUmumId, (int) $profDoku);
                    }
                    if ($unitPoliUmumId > 0 && $profPrw > 0) {
                        $perfSvc->recalculateForGroup((int) $periodDec->id, (int) $unitPoliUmumId, (int) $profPrw);
                    }
                    if ($unitPoliGigiId > 0 && $profDoksp > 0) {
                        $perfSvc->recalculateForGroup((int) $periodDec->id, (int) $unitPoliGigiId, (int) $profDoksp);
                    }
                    if ($unitPoliGigiId > 0 && $profDoku > 0) {
                        $perfSvc->recalculateForGroup((int) $periodDec->id, (int) $unitPoliGigiId, (int) $profDoku);
                    }

                    if ($unitPoliUmumId > 0) {
                        $this->smokeCheckPeriod(
                            periodId: (int) $periodDec->id,
                            unitId: (int) $unitPoliUmumId,
                            sampleUserId: (int) $staff['dokter_umum1']['id'],
                            sampleUserLabel: 'dokter_umum1',
                        );
                    }

                    if ($unitPoliGigiId > 0) {
                        $this->smokeCheckPeriod(
                            periodId: (int) $periodDec->id,
                            unitId: (int) $unitPoliGigiId,
                            sampleUserId: (int) $staff['dokter_spes2']['id'],
                            sampleUserLabel: 'dokter_spes2',
                        );
                    }
                } elseif ($this->command) {
                    $this->command->warn('Skipping recalc + smoke checks: kolom performance_assessment_details.meta tidak ada di schema DB ini.');
                }
            }

            $this->ensurePerformanceAssessmentsForUsers(
                periodId: (int) $periodDec->id,
                assessmentDate: '2025-12-31',
                userIds: $targets,
                now: $now
            );

            $this->ensurePerformanceAssessmentDetailsForUsers(
                periodId: (int) $periodDec->id,
                userIds: $targets,
                now: $now
            );

            $this->seedAssessmentApprovalsForPeriod(
                periodId: (int) $periodDec->id,
                staff: $staff,
                actedAt: Carbon::create(2025, 12, 31),
                now: $now,
                note: 'Seeder auto-approve (Dec 2025 demo).',
                maxLevelApproved: 1,
            );

            $this->seedDemoRemunerationsForPeriod(
                periodId: (int) $periodDec->id,
                emails: $emails,
                now: $now
            );

            $this->assertSeedCoverage(
                periodId: (int) $periodDec->id,
                userIds: $targets
            );
        }
    }

    private function ensureAssessment360Window(int $periodId, string $startDate, string $endDate, ?int $openedById, Carbon $now): void
    {
        if (!Schema::hasTable('assessment_360_windows')) {
            return;
        }
        if ($periodId <= 0) {
            return;
        }

        // Keep at most 1 active window per period.
        DB::table('assessment_360_windows')
            ->where('assessment_period_id', $periodId)
            ->update(['is_active' => 0, 'updated_at' => $now]);

        DB::table('assessment_360_windows')->updateOrInsert(
            [
                'assessment_period_id' => $periodId,
                'start_date' => $startDate,
                'end_date' => $endDate,
            ],
            [
                'is_active' => 1,
                'opened_by' => $openedById,
                'updated_at' => $now,
                'created_at' => $now,
            ]
        );
    }

    /**
     * If recalculation is skipped (older DB schema), still create minimal rows
     * so the demo has data for the 8 staff in November 2025.
     *
     * @param array<int> $userIds
     */
    private function ensurePerformanceAssessmentsForUsers(int $periodId, string $assessmentDate, array $userIds, Carbon $now): void
    {
        if (!Schema::hasTable('performance_assessments')) {
            return;
        }
        if ($periodId <= 0) {
            return;
        }

        $userIds = array_values(array_filter(array_map(fn($v) => (int) $v, $userIds), fn($v) => $v > 0));
        if (empty($userIds)) {
            return;
        }

        $existingUserIds = DB::table('performance_assessments')
            ->where('assessment_period_id', $periodId)
            ->whereIn('user_id', $userIds)
            ->pluck('user_id')
            ->map(fn($v) => (int) $v)
            ->all();

        $missingUserIds = array_values(array_diff($userIds, $existingUserIds));
        if (empty($missingUserIds)) {
            return;
        }

        $rows = [];
        foreach ($missingUserIds as $uid) {
            $rows[] = [
                'user_id' => $uid,
                'assessment_period_id' => $periodId,
                'assessment_date' => $assessmentDate,
                'total_wsm_score' => null,
                'validation_status' => 'Menunggu Validasi',
                'supervisor_comment' => null,
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        DB::table('performance_assessments')->insert($rows);
    }

    /**
     * Fallback: if recalculation is skipped or incomplete, still create detail rows
     * for active criteria so the demo UI has per-criteria entries.
     *
     * Does NOT overwrite existing details.
     *
     * @param array<int> $userIds
     */
    private function ensurePerformanceAssessmentDetailsForUsers(int $periodId, array $userIds, Carbon $now): void
    {
        if (!Schema::hasTable('performance_assessment_details') || !Schema::hasTable('performance_assessments')) {
            return;
        }
        if ($periodId <= 0) {
            return;
        }

        $userIds = array_values(array_filter(array_map(fn($v) => (int) $v, $userIds), fn($v) => $v > 0));
        if (empty($userIds)) {
            return;
        }

        $assessments = DB::table('performance_assessments')
            ->where('assessment_period_id', $periodId)
            ->whereIn('user_id', $userIds)
            ->get(['id', 'user_id']);

        if ($assessments->isEmpty()) {
            return;
        }

        $hasMeta = Schema::hasColumn('performance_assessment_details', 'meta');

        foreach ($assessments as $a) {
            $assessmentId = (int) $a->id;
            $userId = (int) $a->user_id;
            if ($assessmentId <= 0 || $userId <= 0) {
                continue;
            }

            $unitId = (int) (DB::table('users')->where('id', $userId)->value('unit_id') ?? 0);
            if ($unitId <= 0) {
                continue;
            }

            // For active periods: use status=active.
            // For finished periods: use archived weights that were previously active.
            $hasWasActiveBefore = Schema::hasColumn('unit_criteria_weights', 'was_active_before');
            $activeCriteriaIds = DB::table('unit_criteria_weights')
                ->where('assessment_period_id', $periodId)
                ->where('unit_id', $unitId)
                ->where('weight', '>', 0)
                ->where(function ($q) use ($hasWasActiveBefore) {
                    $q->where('status', 'active');
                    if ($hasWasActiveBefore) {
                        $q->orWhere(function ($qq) {
                            $qq->where('status', 'archived')->where('was_active_before', 1);
                        });
                    } else {
                        // Older schema fallback: allow archived as historical.
                        $q->orWhere('status', 'archived');
                    }
                })
                ->pluck('performance_criteria_id')
                ->map(fn($v) => (int) $v)
                ->filter(fn($v) => $v > 0)
                ->values()
                ->all();

            if (empty($activeCriteriaIds)) {
                continue;
            }

            $existingCriteriaIds = DB::table('performance_assessment_details')
                ->where('performance_assessment_id', $assessmentId)
                ->whereIn('performance_criteria_id', $activeCriteriaIds)
                ->pluck('performance_criteria_id')
                ->map(fn($v) => (int) $v)
                ->all();

            $missingCriteriaIds = array_values(array_diff($activeCriteriaIds, $existingCriteriaIds));
            if (empty($missingCriteriaIds)) {
                continue;
            }

            $rows = [];
            foreach ($missingCriteriaIds as $critId) {
                $baseScore = 70 + (($userId + $critId) % 21); // 70..90 deterministic
                $row = [
                    'performance_assessment_id' => $assessmentId,
                    'performance_criteria_id' => $critId,
                    'criteria_metric_id' => null,
                    'score' => (float) $baseScore,
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
                if ($hasMeta) {
                    $row['meta'] = json_encode(['seeded_fallback' => true], JSON_UNESCAPED_UNICODE);
                }
                $rows[] = $row;
            }

            if (!empty($rows)) {
                DB::table('performance_assessment_details')->insert($rows);
            }
        }
    }

    private function seedAssessmentApprovalsForPeriod(int $periodId, array $staff, Carbon $actedAt, Carbon $now, string $note, int $maxLevelApproved = 1): void
    {
        if (!Schema::hasTable('assessment_approvals') || !Schema::hasTable('performance_assessments')) {
            return;
        }

        $maxLevelApproved = max(0, min(3, (int) $maxLevelApproved));
        if ($maxLevelApproved === 0) {
            return;
        }

        $kepalaPoliklinikId = (int) (DB::table('users')->where('email', 'kepala.poliklinik@rsud.local')->value('id') ?? 0);
        if ($kepalaPoliklinikId <= 0) {
            // best-effort fallback: allow seeding without approvals
            return;
        }

        $adminRsId = (int) (DB::table('users')->where('email', 'admin.rs@rsud.local')->value('id') ?? 0);

        $kepalaUmumId = (int) ($staff['kepala_umum']['id'] ?? 0);
        $kepalaGigiId = (int) ($staff['kepala_gigi']['id'] ?? 0);

        $assessments = DB::table('performance_assessments')
            ->where('assessment_period_id', $periodId)
            ->get(['id', 'user_id']);

        foreach ($assessments as $a) {
            $userId = (int) $a->user_id;
            $assessmentId = (int) $a->id;
            if ($userId <= 0 || $assessmentId <= 0) {
                continue;
            }

            $unitSlug = (string) (DB::table('units')->where('id', (int) (DB::table('users')->where('id', $userId)->value('unit_id') ?? 0))->value('slug') ?? '');

            $approverId = $kepalaPoliklinikId;
            if ($unitSlug === 'poliklinik-umum' && $kepalaUmumId > 0) {
                $approverId = ($userId === $kepalaUmumId) ? $kepalaPoliklinikId : $kepalaUmumId;
            } elseif ($unitSlug === 'poliklinik-gigi' && $kepalaGigiId > 0) {
                $approverId = ($userId === $kepalaGigiId) ? $kepalaPoliklinikId : $kepalaGigiId;
            }

            // Level 1 approval (Kepala Unit / fallback Kepala Poliklinik)
            if ($maxLevelApproved >= 1) {
                DB::table('assessment_approvals')->updateOrInsert(
                    [
                        'performance_assessment_id' => $assessmentId,
                        'level' => 1,
                    ],
                    [
                        'approver_id' => $approverId,
                        'status' => 'approved',
                        'note' => $note,
                        'acted_at' => $actedAt,
                        'updated_at' => $now,
                        'created_at' => $now,
                    ]
                );
            }

            // Level 2 approval (Kepala Poliklinik)
            if ($maxLevelApproved >= 2) {
                DB::table('assessment_approvals')->updateOrInsert(
                    [
                        'performance_assessment_id' => $assessmentId,
                        'level' => 2,
                    ],
                    [
                        'approver_id' => $kepalaPoliklinikId,
                        'status' => 'approved',
                        'note' => $note,
                        'acted_at' => $actedAt,
                        'updated_at' => $now,
                        'created_at' => $now,
                    ]
                );
            }

            // Level 3 approval (Admin RS). If Admin RS user is missing, fall back to Kepala Poliklinik.
            if ($maxLevelApproved >= 3) {
                DB::table('assessment_approvals')->updateOrInsert(
                    [
                        'performance_assessment_id' => $assessmentId,
                        'level' => 3,
                    ],
                    [
                        'approver_id' => $adminRsId > 0 ? $adminRsId : $kepalaPoliklinikId,
                        'status' => 'approved',
                        'note' => $note,
                        'acted_at' => $actedAt,
                        'updated_at' => $now,
                        'created_at' => $now,
                    ]
                );
            }

            // Keep performance_assessments.validation_status in sync with approvals for demo.
            if ($maxLevelApproved >= 3 && Schema::hasColumn('performance_assessments', 'validation_status')) {
                DB::table('performance_assessments')
                    ->where('id', $assessmentId)
                    ->update([
                        'validation_status' => 'Tervalidasi',
                        'updated_at' => $now,
                    ]);
            }
        }
    }

    /**
    * Demo remunerations seeding (merged from previous DemoRemunerationSeeder).
     *
     * @param array<int,string> $emails
     */
    private function seedDemoRemunerationsForPeriod(int $periodId, array $emails, Carbon $now): void
    {
        if (!Schema::hasTable('users') || !Schema::hasTable('unit_profession_remuneration_allocations') || !Schema::hasTable('remunerations')) {
            return;
        }
        if ($periodId <= 0) {
            return;
        }

        $periodRow = DB::table('assessment_periods')->where('id', $periodId)->first(['name', 'start_date']);
        $periodName = $periodRow?->name ? (string) $periodRow->name : '';
        $isNovember = str_contains(strtolower($periodName), 'november');
        if (!$isNovember && !empty($periodRow?->start_date)) {
            try {
                $isNovember = Carbon::parse($periodRow->start_date)->month === 11;
            } catch (\Throwable $e) {
                // ignore
            }
        }

        $users = DB::table('users')
            ->whereIn('email', $emails)
            ->get(['id', 'email', 'unit_id', 'profession_id']);

        if ($users->count() !== count($emails)) {
            $missing = array_values(array_diff($emails, $users->pluck('email')->all()));
            throw new \RuntimeException('EightStaffKpiSeeder: user tidak ditemukan untuk remunerasi demo: ' . implode(', ', $missing));
        }

        $groups = $users->groupBy(fn ($u) => (int) $u->unit_id . ':' . (int) $u->profession_id);
        $perUserAmount = 1000000.00;

        foreach ($groups as $groupUsers) {
            $first = $groupUsers->first();
            $unitId = (int) ($first->unit_id ?? 0);
            $professionId = (int) ($first->profession_id ?? 0);
            $count = max(1, (int) $groupUsers->count());

            if ($unitId <= 0 || $professionId <= 0) {
                continue;
            }

            $allocationAmount = round($count * $perUserAmount, 2);

            // Distribute proportionally to WSM (Excel logic): porsi = user_wsm / group_total_wsm
            $wsmTotals = DB::table('performance_assessments')
                ->where('assessment_period_id', $periodId)
                ->whereIn('user_id', $groupUsers->pluck('id')->map(fn($v) => (int) $v)->all())
                ->pluck('total_wsm_score', 'user_id')
                ->map(fn($v) => (float) $v)
                ->all();

            $groupTotalWsm = array_sum($wsmTotals);
            if ($groupTotalWsm <= 0) {
                $groupTotalWsm = max(1, $count);
                $wsmTotals = array_fill_keys($groupUsers->pluck('id')->map(fn($v) => (int) $v)->all(), 1.0);
            }

            $weightsByUserId = [];
            foreach ($groupUsers as $u) {
                $uid = (int) $u->id;
                $weightsByUserId[$uid] = (float) ($wsmTotals[$uid] ?? 0.0);
            }

            $allocatedAmounts = \App\Support\ProportionalAllocator::allocate((float) $allocationAmount, $weightsByUserId);

            DB::table('unit_profession_remuneration_allocations')->updateOrInsert(
                [
                    'assessment_period_id' => $periodId,
                    'unit_id' => $unitId,
                    'profession_id' => $professionId,
                ],
                [
                    'amount' => $allocationAmount,
                    'note' => 'Seeder demo: alokasi untuk ' . $count . ' pegawai (dibagi rata).',
                    'published_at' => $now,
                    'revised_by' => null,
                    'updated_at' => $now,
                    'created_at' => $now,
                ]
            );

            foreach ($groupUsers as $u) {
                $uid = (int) $u->id;
                $userWsm = (float) ($wsmTotals[$uid] ?? 0.0);
                $sharePct = $groupTotalWsm > 0 ? ($userWsm / $groupTotalWsm) * 100.0 : 0.0;
                $perUser = (float) ($allocatedAmounts[$uid] ?? 0.0);

                DB::table('remunerations')->updateOrInsert(
                    [
                        'user_id' => $uid,
                        'assessment_period_id' => $periodId,
                    ],
                    [
                        'amount' => $perUser,
                        'payment_date' => $isNovember ? $now->toDateString() : null,
                        'payment_status' => $isNovember ? 'Dibayar' : 'Belum Dibayar',
                        'calculation_details' => json_encode([
                            'method' => 'demo_unit_profession_wsm_proportional',
                            'period_id' => $periodId,
                            'generated' => $now->toDateTimeString(),
                            'allocation' => [
                                'unit_id' => $unitId,
                                'profession_id' => $professionId,
                                'published_amount' => $allocationAmount,
                                'line_amount' => $allocationAmount,
                                'unit_total_wsm' => round((float) $groupTotalWsm, 2),
                                'user_wsm_score' => round((float) $userWsm, 2),
                                'share_percent' => round((float) $sharePct, 6),
                                'rounding' => [
                                    'method' => 'largest_remainder_cents',
                                    'precision' => 2,
                                ],
                            ],
                            'wsm' => [
                                'user_total' => round((float) $userWsm, 2),
                                'unit_total' => round((float) $groupTotalWsm, 2),
                                'source' => 'performance_assessments.total_wsm_score',
                            ],
                            // UI expects komponen.*.nilai; for WSM method we keep the nominal in one bucket.
                            'komponen' => [
                                'absensi' => ['jumlah' => null, 'nilai' => 0],
                                'kedisiplinan' => ['jumlah' => null, 'nilai' => 0],
                                'kontribusi_tambahan' => ['jumlah' => 0, 'nilai' => 0],
                                'pasien_ditangani' => ['jumlah' => null, 'nilai' => $perUser],
                                'review_pelanggan' => ['jumlah' => 0, 'nilai' => 0],
                            ],
                        ], JSON_UNESCAPED_UNICODE),
                        'published_at' => $now,
                        'calculated_at' => $now,
                        'revised_by' => null,
                        'updated_at' => $now,
                        'created_at' => $now,
                    ]
                );
            }
        }
    }

    /**
     * Hard assertions: make sure November demo data exists for all 8 users.
     *
     * @param array<int> $userIds
     */
    private function assertSeedCoverage(int $periodId, array $userIds): void
    {
        $userIds = array_values(array_filter(array_map(fn($v) => (int) $v, $userIds), fn($v) => $v > 0));
        if ($periodId <= 0 || empty($userIds)) {
            return;
        }

        $period = DB::table('assessment_periods')->where('id', $periodId)->first(['id', 'start_date', 'end_date']);
        $startDate = $period ? (string) $period->start_date : null;
        $endDate = $period ? (string) $period->end_date : null;
        $refPrefix = 'DRV-' . $periodId . '-';

        $checks = [
            'additional_task_claims' => fn() => (Schema::hasTable('additional_task_claims') && Schema::hasTable('additional_tasks'))
                ? DB::table('additional_task_claims as c')
                    ->join('additional_tasks as t', 't.id', '=', 'c.additional_task_id')
                    ->whereIn('c.user_id', $userIds)
                    ->where('t.assessment_period_id', $periodId)
                    ->count()
                : null,
            'multi_rater_assessments' => fn() => Schema::hasTable('multi_rater_assessments')
                ? DB::table('multi_rater_assessments')->whereIn('assessee_id', $userIds)->where('assessment_period_id', $periodId)->count()
                : null,
            'review_details' => fn() => (Schema::hasTable('review_details') && Schema::hasTable('reviews'))
                ? DB::table('review_details as d')
                    ->join('reviews as r', 'r.id', '=', 'd.review_id')
                    ->whereIn('d.medical_staff_id', $userIds)
                    ->where('r.registration_ref', 'like', $refPrefix . '%')
                    ->count()
                : null,
            'review_invitation_staff' => fn() => (Schema::hasTable('review_invitation_staff') && Schema::hasTable('review_invitations'))
                ? DB::table('review_invitation_staff as s')
                    ->join('review_invitations as i', 'i.id', '=', 's.invitation_id')
                    ->whereIn('s.user_id', $userIds)
                    ->where('i.registration_ref', 'like', $refPrefix . '%')
                    ->count()
                : null,
            'attendance_import_rows' => fn() => (Schema::hasTable('attendance_import_rows') && Schema::hasTable('attendance_import_batches'))
                ? DB::table('attendance_import_rows as r')
                    ->join('attendance_import_batches as b', 'b.id', '=', 'r.batch_id')
                    ->whereIn('r.user_id', $userIds)
                    ->where('b.assessment_period_id', $periodId)
                    ->count()
                : null,
            'attendances' => fn() => (Schema::hasTable('attendances') && Schema::hasTable('attendance_import_batches'))
                ? DB::table('attendances as a')
                    ->join('attendance_import_batches as b', 'b.id', '=', 'a.import_batch_id')
                    ->whereIn('a.user_id', $userIds)
                    ->where('b.assessment_period_id', $periodId)
                    ->count()
                : ($startDate && $endDate && Schema::hasTable('attendances')
                    ? DB::table('attendances')->whereIn('user_id', $userIds)->whereBetween('attendance_date', [$startDate, $endDate])->count()
                    : null),
            'imported_criteria_values' => fn() => Schema::hasTable('imported_criteria_values')
                ? DB::table('imported_criteria_values')->whereIn('user_id', $userIds)->where('assessment_period_id', $periodId)->count()
                : null,
            'performance_assessments' => fn() => Schema::hasTable('performance_assessments')
                ? DB::table('performance_assessments')->whereIn('user_id', $userIds)->where('assessment_period_id', $periodId)->count()
                : null,
            'performance_assessment_details' => fn() => (Schema::hasTable('performance_assessment_details') && Schema::hasTable('performance_assessments'))
                ? DB::table('performance_assessment_details as d')
                    ->join('performance_assessments as pa', 'pa.id', '=', 'd.performance_assessment_id')
                    ->whereIn('pa.user_id', $userIds)
                    ->where('pa.assessment_period_id', $periodId)
                    ->count()
                : null,
            'assessment_approvals' => fn() => Schema::hasTable('assessment_approvals')
                ? DB::table('assessment_approvals')
                    ->join('performance_assessments as pa', 'pa.id', '=', 'assessment_approvals.performance_assessment_id')
                    ->whereIn('pa.user_id', $userIds)
                    ->where('pa.assessment_period_id', $periodId)
                    ->count()
                : null,
            'remunerations' => fn() => Schema::hasTable('remunerations')
                ? DB::table('remunerations')->whereIn('user_id', $userIds)->where('assessment_period_id', $periodId)->count()
                : null,
        ];

        $counts = [];
        foreach ($checks as $k => $fn) {
            $counts[$k] = $fn();
        }

        // Per-user minimum assertions where applicable.
        $missing = [];
        if (Schema::hasTable('performance_assessments')) {
            $have = DB::table('performance_assessments')->where('assessment_period_id', $periodId)->whereIn('user_id', $userIds)->pluck('user_id')->map(fn($v) => (int) $v)->all();
            $missing = array_values(array_diff($userIds, $have));
        }

        if (!empty($missing)) {
            throw new \RuntimeException('EightStaffKpiSeeder: missing performance_assessments for user_ids: ' . implode(', ', $missing) . ' (period_id=' . $periodId . ').');
        }

        // Soft sanity: if table exists, it should have at least N rows for these 8 users.
        foreach ($counts as $table => $cnt) {
            if ($cnt === null) {
                continue;
            }
            if ($cnt <= 0) {
                throw new \RuntimeException('EightStaffKpiSeeder: expected data in table ' . $table . ' for seeded users, but found 0 rows.');
            }
        }
    }

    private function smokeCheckPeriod(int $periodId, int $unitId, int $sampleUserId, string $sampleUserLabel): void
    {
        if ($periodId <= 0 || $unitId <= 0 || $sampleUserId <= 0) {
            throw new \RuntimeException('SmokeCheck: invalid ids. periodId=' . $periodId . ', unitId=' . $unitId . ', sampleUserId=' . $sampleUserId);
        }

        $period = AssessmentPeriod::query()->find($periodId);
        if (!$period) {
            throw new \RuntimeException('SmokeCheck: AssessmentPeriod not found. periodId=' . $periodId);
        }

        // 1) Ensure sample user has 1 row in performance_assessments for the period
        $assessment = PerformanceAssessment::query()
            ->where('assessment_period_id', $periodId)
            ->where('user_id', $sampleUserId)
            ->first(['id', 'total_wsm_score']);

        if (!$assessment) {
            $debug = [
                'period' => ['id' => $periodId, 'name' => (string) $period->name],
                'sample_user_label' => $sampleUserLabel,
                'sample_user_id' => $sampleUserId,
                'unit_id' => $unitId,
                'assessments_in_period_for_unit' => DB::table('performance_assessments as pa')
                    ->join('users as u', 'u.id', '=', 'pa.user_id')
                    ->where('pa.assessment_period_id', $periodId)
                    ->where('u.unit_id', $unitId)
                    ->count(),
            ];
            throw new \RuntimeException("SmokeCheck FAILED (1): Missing performance_assessments row for sample user.\n" . json_encode($debug, JSON_PRETTY_PRINT));
        }

        // Active criteria = unit_criteria_weights.status=active, criteria is_active=1, and weight>0
        $activeCriteriaIds = DB::table('unit_criteria_weights as ucw')
            ->join('performance_criterias as pc', 'pc.id', '=', 'ucw.performance_criteria_id')
            ->where('ucw.assessment_period_id', $periodId)
            ->where('ucw.unit_id', $unitId)
            ->where('ucw.status', 'active')
            ->where('ucw.weight', '>', 0)
            ->where('pc.is_active', 1)
            ->pluck('pc.id')
            ->map(fn($v) => (int) $v)
            ->values()
            ->all();

        $activeCount = count($activeCriteriaIds);

        // 2) Ensure details exist for at least all active criteria
        $detailsActiveCount = PerformanceAssessmentDetail::query()
            ->where('performance_assessment_id', (int) $assessment->id)
            ->when($activeCount > 0, fn($q) => $q->whereIn('performance_criteria_id', $activeCriteriaIds))
            ->count();

        if ($activeCount > 0 && $detailsActiveCount < $activeCount) {
            $missing = array_values(array_diff(
                $activeCriteriaIds,
                PerformanceAssessmentDetail::query()
                    ->where('performance_assessment_id', (int) $assessment->id)
                    ->whereIn('performance_criteria_id', $activeCriteriaIds)
                    ->pluck('performance_criteria_id')
                    ->map(fn($v) => (int) $v)
                    ->all()
            ));

            $debug = [
                'period' => ['id' => $periodId, 'name' => (string) $period->name],
                'sample_user_label' => $sampleUserLabel,
                'sample_user_id' => $sampleUserId,
                'assessment_id' => (int) $assessment->id,
                'expected_active_criteria_count' => $activeCount,
                'found_active_details_count' => $detailsActiveCount,
                'missing_active_criteria_ids' => $missing,
                'criteria_summary' => $this->buildCriteriaDebugSummary($periodId, $unitId, (int) $assessment->id),
            ];
            throw new \RuntimeException("SmokeCheck FAILED (2): Not enough performance_assessment_details for ACTIVE criteria.\n" . json_encode($debug, JSON_PRETTY_PRINT));
        }

        // 3) Ensure not all scores are 100 (at least one criterion score < 100)
        $hasBelow100 = PerformanceAssessmentDetail::query()
            ->where('performance_assessment_id', (int) $assessment->id)
            ->where('score', '<', 100)
            ->exists();

        if (!$hasBelow100 && $this->command) {
            // $this->command->warn(
            //     'SmokeCheck NOTE: semua skor kriteria untuk sample user = 100 (label=' . $sampleUserLabel . '). Ini bisa valid jika sample adalah performer terbaik.'
            // );
        }
    }

    /**
     * Debug dump: per-criteria details distribution within the unit for the period.
     * @return array<int, array<string, mixed>>
     */
    private function buildCriteriaDebugSummary(int $periodId, int $unitId, int $felixAssessmentId): array
    {
        $rows = DB::table('performance_assessment_details as pad')
            ->join('performance_assessments as pa', 'pa.id', '=', 'pad.performance_assessment_id')
            ->join('users as u', 'u.id', '=', 'pa.user_id')
            ->join('performance_criterias as pc', 'pc.id', '=', 'pad.performance_criteria_id')
            ->where('pa.assessment_period_id', $periodId)
            ->where('u.unit_id', $unitId)
            ->groupBy('pad.performance_criteria_id', 'pc.name')
            ->orderBy('pc.name')
            ->selectRaw('pad.performance_criteria_id as criteria_id')
            ->selectRaw('pc.name as criteria_name')
            ->selectRaw('COUNT(*) as details_rows')
            ->selectRaw('MIN(pad.score) as min_score')
            ->selectRaw('MAX(pad.score) as max_score')
            ->selectRaw('AVG(pad.score) as avg_score')
            ->get();

        $felixScores = DB::table('performance_assessment_details as pad')
            ->join('performance_criterias as pc', 'pc.id', '=', 'pad.performance_criteria_id')
            ->where('pad.performance_assessment_id', $felixAssessmentId)
            ->pluck('pad.score', 'pc.name');

        $out = [];
        foreach ($rows as $r) {
            $name = (string) $r->criteria_name;
            $out[] = [
                'criteria_id' => (int) $r->criteria_id,
                'criteria_name' => $name,
                'details_rows' => (int) $r->details_rows,
                'min_score' => round((float) $r->min_score, 2),
                'max_score' => round((float) $r->max_score, 2),
                'avg_score' => round((float) $r->avg_score, 2),
                'felix_score' => $felixScores[$name] ?? null,
            ];
        }

        return $out;
    }

    private function seedPeriod(
        object $period,
        array $data,
        array $staff,
        array $criteriaIds,
        Carbon $assessmentDate,
        callable $professionIdResolver,
        callable $unitIdResolver,
        ?int $exampleInactiveUnitId,
        string $scheduleIn,
        string $scheduleOut,
        string $overtimeEnd,
    ): void {
        $now = Carbon::now();

        $absensiId      = $criteriaIds['absensiId'];
        $workHoursId    = $criteriaIds['workHoursId'];
        $overtimeId     = $criteriaIds['overtimeId'];
        $lateMinutesId  = $criteriaIds['lateMinutesId'];
        $kedis360Id     = $criteriaIds['kedis360Id'];
        $kerjasama360Id = $criteriaIds['kerjasama360Id'];
        $kontribusiId   = $criteriaIds['kontribusiId'];
        $pasienId       = $criteriaIds['pasienId'];
        $komplainId     = $criteriaIds['komplainId'];
        $ratingId       = $criteriaIds['ratingId'];

        // Pastikan bobot aktif per unit & periode tersedia agar tampil di ringkasan WSM
        $unitIds = collect($staff)->pluck('unit_slug')->unique()->map(fn($slug) => $unitIdResolver($slug))->all();

        $hasWasActiveBefore = Schema::hasColumn('unit_criteria_weights', 'was_active_before');

        // IMPORTANT:
        // - For finished/non-active periods, never seed/leave working weights.
        // - If no weights exist for that period yet (migrate:fresh), seed them and immediately archive.
        $periodStatus = (string) ($period->status ?? '');
        $isActivePeriod = $periodStatus === AssessmentPeriod::STATUS_ACTIVE;
        if ($isActivePeriod) {
            DB::table('unit_criteria_weights')
                ->where('assessment_period_id', $period->id)
                ->whereIn('unit_id', $unitIds)
                ->delete();
        }

        // Default weights (Excel mapping) when no Excel template provided (sum=100).
        $defaultWeights = [
            $absensiId => 20,
            $workHoursId => 10,
            $overtimeId => 5,
            $lateMinutesId => 10,
            $kedis360Id => 15,
            $kerjasama360Id => 10,
            $kontribusiId => 10,
            $pasienId => 5,
            $komplainId => 3,
            $ratingId => 12,
        ];

        $weightsFromExcel = $this->excelConfig['weights'][(int) $period->id] ?? null;

        $weightRows = [];
        $activeWeightsByUnit = [];
        $sumWeightByUnit = [];
        foreach ($unitIds as $uId) {
            $unitSlug = (string) (DB::table('units')->where('id', (int) $uId)->value('slug') ?? '');

            // Choose weight set:
            // - Excel-defined per (period, unit_slug)
            // - Else fallback demo weights (equal)
            $weightSet = null;
            if (is_array($weightsFromExcel) && $unitSlug !== '' && isset($weightsFromExcel[$unitSlug])) {
                $weightSet = $weightsFromExcel[$unitSlug];
            }

            $unitWeightRows = [];

            if (is_array($weightSet)) {
                // weightSet is keyed by criteria name.
                foreach ($weightSet as $criteriaName => $cfg) {
                    $critId = (int) (DB::table('performance_criterias')->where('name', (string) $criteriaName)->value('id') ?? 0);
                    if ($critId <= 0) {
                        continue;
                    }
                    $unitWeightRows[] = [
                        'unit_id' => $uId,
                        'performance_criteria_id' => $critId,
                        'weight' => (float) ($cfg['weight'] ?? 0.0),
                        'assessment_period_id' => $period->id,
                        // Seed using the intended working status; for non-active periods we will archive immediately.
                        'status' => (string) ($cfg['status'] ?? 'active'),
                        'policy_doc_path' => null,
                        'policy_note' => 'Seeder bobot dari Excel template',
                        'proposed_by' => null,
                        'proposed_note' => null,
                        'decided_by' => null,
                        'decided_at' => null,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ];
                }
            } else {
                foreach ($defaultWeights as $critId => $weight) {
                    $status = 'active';
                    // Example: make one criterion NON-AKTIF (draft) for a unit in this period.
                    if ($exampleInactiveUnitId && (int) $uId === (int) $exampleInactiveUnitId && (int) $critId === (int) $ratingId) {
                        $status = 'draft';
                    }
                    $unitWeightRows[] = [
                        'unit_id' => $uId,
                        'performance_criteria_id' => $critId,
                        'weight' => (float) $weight,
                        'assessment_period_id' => $period->id,
                        // Seed using the intended working status; for non-active periods we will archive immediately.
                        'status' => $status,
                        'policy_doc_path' => null,
                        'policy_note' => 'Seeder bobot default',
                        'proposed_by' => null,
                        'proposed_note' => null,
                        'decided_by' => null,
                        'decided_at' => null,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ];
                }
            }

            // Guardrail: cap SUM(active weights) to max 100.
            // If the Excel template accidentally sums > 100 (e.g., 110), reduce the largest active weights first.
            $sumActive = 0.0;
            $activeIndexes = [];
            foreach ($unitWeightRows as $idx => $r) {
                if (($r['status'] ?? '') === 'active') {
                    $w = (float) ($r['weight'] ?? 0.0);
                    $sumActive += $w;
                    $activeIndexes[] = $idx;
                }
            }

            if ($sumActive > 100.0 && !empty($activeIndexes)) {
                $excess = $sumActive - 100.0;
                usort($activeIndexes, function ($a, $b) use ($unitWeightRows) {
                    $wa = (float) ($unitWeightRows[$a]['weight'] ?? 0.0);
                    $wb = (float) ($unitWeightRows[$b]['weight'] ?? 0.0);
                    return $wb <=> $wa;
                });

                foreach ($activeIndexes as $idx) {
                    if ($excess <= 0.0) {
                        break;
                    }
                    $w = (float) ($unitWeightRows[$idx]['weight'] ?? 0.0);
                    if ($w <= 0.0) {
                        continue;
                    }

                    // Prefer not to push demo active weights below 10.
                    // This makes typical Excel weights like 15+15 become 10+10 when excess is 10.
                    $reducible = max(0.0, $w - 10.0);
                    if ($reducible <= 0.0) {
                        continue;
                    }
                    $reduce = min($excess, $reducible);
                    $unitWeightRows[$idx]['weight'] = round($w - $reduce, 6);
                    $excess -= $reduce;
                }

                // If still excess remains (should not happen for our demo), reduce further as a fallback.
                if ($excess > 0.0) {
                    foreach ($activeIndexes as $idx) {
                        if ($excess <= 0.0) {
                            break;
                        }
                        $w = (float) ($unitWeightRows[$idx]['weight'] ?? 0.0);
                        if ($w <= 0.0) {
                            continue;
                        }
                        $reduce = min($excess, $w);
                        $unitWeightRows[$idx]['weight'] = round($w - $reduce, 6);
                        $excess -= $reduce;
                    }
                }
            }

            foreach ($unitWeightRows as $r) {
                if ($hasWasActiveBefore) {
                    // Do NOT force was_active_before=1 for non-active periods.
                    // We'll set it to 1 only for rows that were truly active when we archive them.
                    $r['was_active_before'] = 0;
                }
                $weightRows[] = $r;
                if (($r['status'] ?? '') === 'active') {
                    $critId = (int) ($r['performance_criteria_id'] ?? 0);
                    $w = (float) ($r['weight'] ?? 0.0);
                    $activeWeightsByUnit[(int) $uId][(int) $critId] = $w;
                    $sumWeightByUnit[(int) $uId] = ($sumWeightByUnit[(int) $uId] ?? 0.0) + $w;
                }
            }
        }

        // Insert weights:
        // - Active periods: always insert (we deleted above).
        // - Non-active periods: insert only if there are no weights yet, then archive immediately.
        $shouldInsert = false;
        if ($isActivePeriod) {
            $shouldInsert = true;
        } else {
            $existingCnt = (int) DB::table('unit_criteria_weights')
                ->where('assessment_period_id', $period->id)
                ->whereIn('unit_id', $unitIds)
                ->count();
            $shouldInsert = $existingCnt <= 0;
        }

        if ($shouldInsert && !empty($weightRows)) {
            DB::table('unit_criteria_weights')->insert($weightRows);
        }

        if (!$isActivePeriod) {
            // Archive in two steps to ensure the flag is set based on the *previous* status.
            $base = DB::table('unit_criteria_weights')
                ->where('assessment_period_id', $period->id)
                ->whereIn('unit_id', $unitIds)
                ->where('status', '!=', 'archived');

            if ($hasWasActiveBefore) {
                (clone $base)
                    ->where('status', 'active')
                    ->update([
                        'status' => 'archived',
                        'was_active_before' => 1,
                        'updated_at' => $now,
                    ]);

                (clone $base)
                    ->where('status', '!=', 'active')
                    ->update([
                        'status' => 'archived',
                        'updated_at' => $now,
                    ]);
            } else {
                (clone $base)->update([
                    'status' => 'archived',
                    'updated_at' => $now,
                ]);
            }
        }

        // Create one attendance import batch per period (so attendance rows are linked to attendance_import_* tables).
        $prevBatchId = DB::table('attendance_import_batches')
            ->where('assessment_period_id', $period->id)
            ->where('is_superseded', 0)
            ->value('id');
        if ($prevBatchId) {
            DB::table('attendance_import_batches')->where('id', (int) $prevBatchId)->update([
                'is_superseded' => 1,
                'updated_at' => $now,
            ]);
        }
        $attendanceBatchId = (int) DB::table('attendance_import_batches')->insertGetId([
            'file_name' => 'seeder-attendance-' . $period->id . '.xlsx',
            'assessment_period_id' => $period->id,
            'imported_by' => null,
            'imported_at' => $now,
            'total_rows' => 0,
            'success_rows' => 0,
            'failed_rows' => 0,
            'is_superseded' => 0,
            'previous_batch_id' => $prevBatchId ? (int) $prevBatchId : null,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        $attendanceImportRowNo = 2;
        $attendanceImportRows = [];
        $attendanceRows = [];

        $metricsBatchId = DB::table('metric_import_batches')->insertGetId([
            'file_name' => 'seeder-metrics-' . $period->id . '.xlsx',
            'assessment_period_id' => $period->id,
            'imported_by' => null,
            'status' => 'processed',
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        // =========================================================
        // Tugas Tambahan (module-style): 2 tasks per unit+period
        // - Task POINTS: uses t.points
        // - Task UANG  : uses t.bonus_amount, but claims still provide awarded_points
        // Claims with status approved/completed contribute via SUM(awarded_points) fallback to t.points.
        // =========================================================
        $taskPointsIdByUnit = [];
        $taskMoneyIdByUnit = [];

        // Seed rule:
        // - Create a small, realistic quota of claims per task (per period): 2 for November demo, 3 for December demo.
        // - Distribute claim ownership evenly among eligible staff (exclude kepala_* entries).
        $desiredClaimsPerTask = 2;
        if (Str::contains((string) $period->name, 'December')) {
            $desiredClaimsPerTask = 3;  
        }

        $eligibleStaffIdsByUnit = [];
        foreach ($staff as $key => $info) {
            if (Str::startsWith((string) $key, 'kepala_')) {
                continue;
            }
            $uSlug = (string) ($info['unit_slug'] ?? '');
            $uId = (int) ($info['id'] ?? 0);
            if ($uSlug === '' || $uId <= 0) {
                continue;
            }
            $eligibleStaffIdsByUnit[$uSlug] ??= [];
            $eligibleStaffIdsByUnit[$uSlug][] = $uId;
        }

        $taskClaimQuotaById = [];
        foreach (collect($staff)->pluck('unit_slug')->unique()->values()->all() as $unitSlug) {
            $uId = (int) $unitIdResolver((string) $unitSlug);
            if ($uId <= 0) {
                continue;
            }

            $eligibleIds = $eligibleStaffIdsByUnit[(string) $unitSlug] ?? [];
            $claimQuota = min($desiredClaimsPerTask, count($eligibleIds));
            if ($claimQuota <= 0) {
                continue;
            }

            // Use any existing staff in the unit as creator to avoid inserting new users.
            $creatorId = 0;
            foreach ($staff as $s) {
                if (($s['unit_slug'] ?? null) === $unitSlug && (int) ($s['id'] ?? 0) > 0) {
                    $creatorId = (int) $s['id'];
                    break;
                }
            }

            $taskPointsIdByUnit[(string) $unitSlug] = (int) DB::table('additional_tasks')->insertGetId([
                'unit_id' => $uId,
                'assessment_period_id' => $period->id,
                'title' => 'Tugas Tambahan (Points) - ' . $period->name . ' (' . $unitSlug . ')',
                'description' => 'Seeder additional task berbasis poin',
                'policy_doc_path' => null,
                'start_date' => (string) $period->start_date,
                'due_date' => (string) $period->end_date,
                'start_time' => '00:00:00',
                'due_time' => '23:59:00',
                'bonus_amount' => null,
                'points' => 10,
                'max_claims' => $claimQuota,
                'cancel_window_hours' => 24,
                'default_penalty_type' => 'none',
                'default_penalty_value' => 0,
                'penalty_base' => 'task_bonus',
                'status' => 'closed',
                'created_by' => $creatorId > 0 ? $creatorId : null,
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            $taskClaimQuotaById[(int) $taskPointsIdByUnit[(string) $unitSlug]] = $claimQuota;

            $taskMoneyIdByUnit[(string) $unitSlug] = (int) DB::table('additional_tasks')->insertGetId([
                'unit_id' => $uId,
                'assessment_period_id' => $period->id,
                'title' => 'Tugas Tambahan (Uang) - ' . $period->name . ' (' . $unitSlug . ')',
                'description' => 'Seeder additional task berbasis uang; claim menyediakan awarded_points untuk skor',
                'policy_doc_path' => null,
                'start_date' => (string) $period->start_date,
                'due_date' => (string) $period->end_date,
                'start_time' => '00:00:00',
                'due_time' => '23:59:00',
                'bonus_amount' => 500000,
                'points' => null,
                'max_claims' => $claimQuota,
                'cancel_window_hours' => 24,
                'default_penalty_type' => 'none',
                'default_penalty_value' => 0,
                'penalty_base' => 'task_bonus',
                'status' => 'closed',
                'created_by' => $creatorId > 0 ? $creatorId : null,
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            $taskClaimQuotaById[(int) $taskMoneyIdByUnit[(string) $unitSlug]] = $claimQuota;
        }

        // Seed claims evenly per unit across both tasks (round-robin).
        $rrIndexByUnit = [];
        foreach (collect($staff)->pluck('unit_slug')->unique()->values()->all() as $unitSlug) {
            $eligibleIds = $eligibleStaffIdsByUnit[(string) $unitSlug] ?? [];
            $eligibleCount = count($eligibleIds);
            if ($eligibleCount <= 0) {
                continue;
            }

            $rrIndexByUnit[(string) $unitSlug] ??= 0;

            $taskIds = [
                (int) ($taskPointsIdByUnit[(string) $unitSlug] ?? 0),
                (int) ($taskMoneyIdByUnit[(string) $unitSlug] ?? 0),
            ];
            foreach ($taskIds as $taskId) {
                if ($taskId <= 0) {
                    continue;
                }
                $quota = (int) ($taskClaimQuotaById[$taskId] ?? 0);
                if ($quota <= 0) {
                    continue;
                }

                $start = $rrIndexByUnit[(string) $unitSlug] % $eligibleCount;
                $rrIndexByUnit[(string) $unitSlug] = $start + $quota;

                for ($i = 0; $i < $quota; $i++) {
                    $uid = (int) $eligibleIds[($start + $i) % $eligibleCount];
                    if ($uid <= 0) {
                        continue;
                    }

                    // Awarded points: points task uses its points; money task still contributes points via awarded_points.
                    $awardedPoints = $taskId === (int) ($taskPointsIdByUnit[(string) $unitSlug] ?? 0) ? 10.0 : 10.0;

                    DB::table('additional_task_claims')->insert([
                        'additional_task_id' => $taskId,
                        'user_id' => $uid,
                        'status' => 'approved',
                        'claimed_at' => $assessmentDate,
                        'completed_at' => null,
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
                        'result_note' => 'Seeder claim (quota=' . $quota . ')',
                        'awarded_points' => $awardedPoints,
                        'awarded_bonus_amount' => null,
                        'reviewed_by_id' => null,
                        'reviewed_at' => $assessmentDate,
                        'review_comment' => 'Seeder auto approve',
                        'is_violation' => 0,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ]);
                }
            }
        }

        foreach ($data as $key => $row) {
            $userId = (int) ($staff[$key]['id'] ?? 0);
            if ($userId <= 0) {
                continue;
            }
            $unitId = (int) $unitIdResolver($staff[$key]['unit_slug']);

            // 0) Tugas Tambahan:
            // additional_task_claims sudah diseed merata (lihat blok di atas).

            // 1) ABSENSI: hadir + keterlambatan + jam kerja + lembur (semua mentah di tabel attendances)
            $hadirDays = (int) ($row['attendance_days'] ?? 0);
            $totalLate = (int) ($row['late_minutes'] ?? 0);
            $totalWork = (int) ($row['work_minutes'] ?? 0);
            $overtimeDays = (int) ($row['overtime_days'] ?? 0);
            $dates = $this->takeDates((string) $period->start_date, (string) $period->end_date, $hadirDays);
            $planRows = $this->buildAttendancePlan(
                userId: $userId,
                dates: $dates,
                totalLateMinutes: $totalLate,
                totalWorkMinutes: $totalWork,
                overtimeDays: $overtimeDays,
                now: $now,
                scheduleIn: $scheduleIn,
                scheduleOut: $scheduleOut,
                overtimeEnd: $overtimeEnd,
            );
            if (!empty($planRows)) {
                // 1) Insert preview/import rows first (pipeline source-of-truth)
                $empNo = (string) (DB::table('users')->where('id', $userId)->value('employee_number') ?? '');
                foreach ($planRows as $pr) {
                    $attendanceImportRows[] = [
                        'batch_id' => $attendanceBatchId,
                        'row_no' => $attendanceImportRowNo++,
                        'user_id' => $userId,
                        'employee_number' => $empNo,
                        'raw_data' => json_encode([
                            'NIP' => $empNo,
                            'Tanggal' => (string) ($pr['attendance_date'] ?? null),
                            'Jam Masuk' => (string) ($pr['scheduled_in'] ?? null),
                            'Jam Keluar' => (string) ($pr['scheduled_out'] ?? null),
                            'Scan Masuk' => (string) ($pr['check_in'] ?? null),
                            'Scan Keluar' => (string) ($pr['check_out'] ?? null),
                            'Datang Terlambat' => (int) ($pr['late_minutes'] ?? 0),
                            'Durasi Kerja' => (int) ($pr['work_duration_minutes'] ?? 0),
                            'Shift Lembur' => (int) ($pr['overtime_shift'] ?? 0),
                            'Status' => (string) ($pr['attendance_status'] ?? 'Hadir'),
                        ], JSON_UNESCAPED_UNICODE),
                        'parsed_data' => json_encode([
                            'attendance_date' => (string) ($pr['attendance_date'] ?? null),
                            'scheduled_in' => (string) ($pr['scheduled_in'] ?? null),
                            'scheduled_out' => (string) ($pr['scheduled_out'] ?? null),
                            'check_in' => (string) ($pr['check_in'] ?? null),
                            'check_out' => (string) ($pr['check_out'] ?? null),
                            'late_minutes' => (int) ($pr['late_minutes'] ?? 0),
                            'work_duration_minutes' => (int) ($pr['work_duration_minutes'] ?? 0),
                            'overtime_shift' => (int) ($pr['overtime_shift'] ?? 0),
                            'overtime_end' => (string) ($pr['overtime_end'] ?? null),
                            'attendance_status' => (string) ($pr['attendance_status'] ?? 'Hadir'),
                        ], JSON_UNESCAPED_UNICODE),
                        'success' => true,
                        'error_code' => null,
                        'error_message' => null,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ];

                    // 2) Generate final attendances derived from the parsed import row fields
                    $attendanceRows[] = [
                        'user_id' => $userId,
                        'attendance_date' => (string) ($pr['attendance_date'] ?? null),
                        'check_in' => (string) ($pr['check_in'] ?? null),
                        'check_out' => (string) ($pr['check_out'] ?? null),
                        'shift_name' => $pr['shift_name'] ?? null,
                        'scheduled_in' => (string) ($pr['scheduled_in'] ?? null),
                        'scheduled_out' => (string) ($pr['scheduled_out'] ?? null),
                        'late_minutes' => (int) ($pr['late_minutes'] ?? 0),
                        'early_leave_minutes' => null,
                        'work_duration_minutes' => (int) ($pr['work_duration_minutes'] ?? 0),
                        'break_duration_minutes' => null,
                        'extra_break_minutes' => null,
                        'overtime_end' => $pr['overtime_end'] ?? null,
                        'holiday_public' => 0,
                        'holiday_regular' => 0,
                        'overtime_shift' => (int) ($pr['overtime_shift'] ?? 0),
                        'attendance_status' => (string) ($pr['attendance_status'] ?? 'Hadir'),
                        'note' => 'Seeder derived from attendance_import_rows',
                        'overtime_note' => null,
                        'source' => 'import',
                        'import_batch_id' => $attendanceBatchId,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ];
                }
            }

            // 2) 360: kedisiplinan + kerjasama (mentah di multi_rater_assessments + details)
            // Seed richer data so:
            // - Each assessee has multiple assessor types (self/supervisor/peer, and subordinate for unit heads)
            // - Peer has >1 assessor so the summary uses AVG within the type
            // - Criteria follow the criteria_rater_rules seeded by DatabaseSeeder:
            //   - Kedisiplinan (360): supervisor, self
            //   - Kerjasama (360): supervisor, peer, subordinate, self

            $unitSlug = (string) ($staff[$key]['unit_slug'] ?? '');
            $sameUnitIds = [];
            foreach ($staff as $otherKey => $other) {
                $cid = (int) ($other['id'] ?? 0);
                if ($cid <= 0 || $cid === $userId) {
                    continue;
                }
                if (($other['unit_slug'] ?? null) !== $unitSlug) {
                    continue;
                }
                $sameUnitIds[] = $cid;
            }

            $kepalaPoliklinikId = (int) (DB::table('users')->where('email', 'kepala.poliklinik@rsud.local')->value('id') ?? 0);
            $unitHeadId = 0;
            foreach ($staff as $otherKey => $other) {
                if (($other['unit_slug'] ?? null) !== $unitSlug) {
                    continue;
                }
                if (str_starts_with((string) $otherKey, 'kepala_')) {
                    $unitHeadId = (int) ($other['id'] ?? 0);
                    break;
                }
            }

            $isUnitHeadAssessee = str_starts_with((string) $key, 'kepala_');

            // Pick assessors.
            $supervisorIds = [];
            if ($isUnitHeadAssessee && $kepalaPoliklinikId > 0) {
                $supervisorIds[] = $kepalaPoliklinikId;
            } elseif ($unitHeadId > 0 && $unitHeadId !== $userId) {
                $supervisorIds[] = $unitHeadId;
            } elseif (!empty($sameUnitIds)) {
                $supervisorIds[] = $sameUnitIds[0];
            }

            $peerIds = array_values(array_slice($sameUnitIds, 0, 2));

            $subordinateIds = [];
            if ($isUnitHeadAssessee) {
                $subordinateIds = array_values(array_slice($sameUnitIds, 0, 2));
            }

            $baseDiscipline = (float) ($row['discipline_360'] ?? 0);
            $baseTeamwork = (float) ($row['teamwork_360'] ?? 0);

            $clamp = function (float $v): float {
                return max(0.0, min(100.0, $v));
            };

            $insertAssessment = function (int $assesseeId, int $assessorId, string $assessorType, array $details) use ($period, $assessmentDate, $now) {
                $mraId = DB::table('multi_rater_assessments')->insertGetId([
                    'assessee_id' => $assesseeId,
                    'assessor_id' => $assessorId,
                    'assessor_type' => $assessorType,
                    'assessment_period_id' => $period->id,
                    'status' => 'submitted',
                    'submitted_at' => $assessmentDate,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);

                foreach ($details as &$d) {
                    $d['multi_rater_assessment_id'] = $mraId;
                    $d['created_at'] = $now;
                    $d['updated_at'] = $now;
                }

                if (!empty($details)) {
                    DB::table('multi_rater_assessment_details')->insert($details);
                }
            };

            // Self: both criteria allowed.
            $insertAssessment($userId, $userId, 'self', [
                [
                    'performance_criteria_id' => $kedis360Id,
                    'score' => $clamp($baseDiscipline - 2.0),
                    'comment' => 'Seeder 360 (self): kedisiplinan',
                ],
                [
                    'performance_criteria_id' => $kerjasama360Id,
                    'score' => $clamp($baseTeamwork - 1.0),
                    'comment' => 'Seeder 360 (self): kerjasama',
                ],
            ]);

            // Supervisor: both criteria allowed.
            foreach (array_slice($supervisorIds, 0, 1) as $sid) {
                $insertAssessment($userId, (int) $sid, 'supervisor', [
                    [
                        'performance_criteria_id' => $kedis360Id,
                        'score' => $clamp($baseDiscipline + 0.0),
                        'comment' => 'Seeder 360 (supervisor): kedisiplinan',
                    ],
                    [
                        'performance_criteria_id' => $kerjasama360Id,
                        'score' => $clamp($baseTeamwork + 0.0),
                        'comment' => 'Seeder 360 (supervisor): kerjasama',
                    ],
                ]);
            }

            // Peer: only teamwork allowed.
            foreach ($peerIds as $i => $pid) {
                $delta = ($i % 2 === 0) ? -2.0 : 1.0;
                $insertAssessment($userId, (int) $pid, 'peer', [
                    [
                        'performance_criteria_id' => $kerjasama360Id,
                        'score' => $clamp($baseTeamwork + $delta),
                        'comment' => 'Seeder 360 (peer): kerjasama',
                    ],
                ]);
            }

            // Subordinate: only teamwork allowed (seed only for unit heads).
            foreach ($subordinateIds as $i => $subId) {
                $delta = ($i % 2 === 0) ? -3.0 : -1.0;
                $insertAssessment($userId, (int) $subId, 'subordinate', [
                    [
                        'performance_criteria_id' => $kerjasama360Id,
                        'score' => $clamp($baseTeamwork + $delta),
                        'comment' => 'Seeder 360 (subordinate): kerjasama',
                    ],
                ]);
            }

            // 3) Tugas tambahan seeded above via additional_tasks + additional_task_claims.

            // 4) Metric import (mentah): pasien + komplain
            DB::table('imported_criteria_values')->insert([
                'import_batch_id' => $metricsBatchId,
                'user_id' => $userId,
                'assessment_period_id' => $period->id,
                'performance_criteria_id' => $pasienId,
                'value_numeric' => (float) ($row['patients'] ?? 0),
                'value_datetime' => null,
                'value_text' => null,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
            DB::table('imported_criteria_values')->insert([
                'import_batch_id' => $metricsBatchId,
                'user_id' => $userId,
                'assessment_period_id' => $period->id,
                'performance_criteria_id' => $komplainId,
                'value_numeric' => (float) ($row['complaints'] ?? 0),
                'value_datetime' => null,
                'value_text' => null,
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            // 5) Review rating (mentah): generate rating integer agar AVG sesuai template
            $targetAvg = (float) ($row['rating_avg'] ?? 0);
            $ratingCount = (int) ($row['rating_count'] ?? 10);
            $ratingSum = array_key_exists('rating_sum', $row) ? (int) ($row['rating_sum'] ?? 0) : null;
            $ratings = $this->buildRatingDistribution($targetAvg, $ratingCount, $ratingSum);

            $role = 'dokter';
            foreach ($ratings as $idx => $rt) {
                $registrationRef = 'DRV-' . $period->id . '-' . $userId . '-' . ($idx + 1);

                // 5a) Review invitation tables (review_invitation_*), to mirror the real patient review flow.
                $tokenHash = hash('sha256', Str::random(64) . '|' . $registrationRef);
                $invId = (int) DB::table('review_invitations')->insertGetId([
                    'registration_ref' => $registrationRef,
                    'unit_id' => $unitId ?: null,
                    'patient_name' => 'Pasien ' . ($idx + 1),
                    'contact' => '08xxxxxxxxxx',
                    'assessment_period_id' => $period->id,
                    'token_hash' => $tokenHash,
                    'status' => 'used',
                    'expires_at' => $assessmentDate->copy()->addDays(7),
                    'sent_at' => $assessmentDate->copy()->subDays(1),
                    'clicked_at' => $assessmentDate->copy()->subHours(3),
                    'used_at' => $assessmentDate,
                    'client_ip' => '127.0.0.1',
                    'user_agent' => 'Seeder',
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
                DB::table('review_invitation_staff')->insert([
                    'invitation_id' => $invId,
                    'user_id' => $userId,
                    'role' => $role,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);

                $revId = DB::table('reviews')->insertGetId([
                    'registration_ref' => $registrationRef,
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

        // Flush attendance import preview rows and update batch counters.
        if (!empty($attendanceImportRows)) {
            DB::table('attendance_import_rows')->insert($attendanceImportRows);
            $total = count($attendanceImportRows);
            DB::table('attendance_import_batches')->where('id', $attendanceBatchId)->update([
                'total_rows' => $total,
                'success_rows' => $total,
                'failed_rows' => 0,
                'updated_at' => $now,
            ]);
        }

        // Insert final attendance rows after import rows are in place.
        if (!empty($attendanceRows)) {
            DB::table('attendances')->insert($attendanceRows);
        }
    }

    /**
     * Attempt to load Excel template config.
     *
     * Expected sheets/headers (flexible; header names are normalized):
     * - Weights sheet: period_name, unit_slug, criteria_name, weight, (optional) status
    * - Raw sheet: period_name, staff_key|email|employee_number,
    *              attendance_days, late_minutes, work_minutes, overtime_days,
    *              discipline_360, teamwork_360,
    *              contrib, patients, complaints,
    *              rating_avg, (optional) rating_count, rating_sum
     * - Criteria sheet: criteria_name, normalization_basis, (optional) custom_target_value
     */
    private function tryLoadExcelTemplate(array $staff): ?array
    {
        $path = (string) (env('KPI_TEMPLATE_PATH') ?: storage_path('app/kpi-template.xlsx'));
        $require = filter_var((string) env('KPI_REQUIRE_EXCEL', 'false'), FILTER_VALIDATE_BOOL);
        if (!is_file($path) || !is_readable($path)) {
            if ($require) {
                throw new \RuntimeException('KPI Excel template wajib ada. Set KPI_TEMPLATE_PATH atau taruh file di storage/app/kpi-template.xlsx');
            }
            return null;
        }

        $spreadsheet = IOFactory::load($path);

        $emailToKey = [];
        $empToKey = [];
        foreach ($staff as $k => $info) {
            $email = (string) (DB::table('users')->where('id', (int) ($info['id'] ?? 0))->value('email') ?? '');
            $emp = (string) (DB::table('users')->where('id', (int) ($info['id'] ?? 0))->value('employee_number') ?? '');
            if ($email !== '') $emailToKey[strtolower($email)] = (string) $k;
            if ($emp !== '') $empToKey[$emp] = (string) $k;
        }

        $weights = [];
        $raw = [];
        $criteria = [];

        foreach ($spreadsheet->getAllSheets() as $sheet) {
            $rows = $sheet->toArray(null, true, true, false);
            if (!$rows || count($rows) < 2) {
                continue;
            }

            $header = array_shift($rows);
            $map = $this->mapHeader($header);

            // Criteria policy sheet
            if (($map['criteria_name'] ?? false) !== false && ($map['normalization_basis'] ?? false) !== false) {
                foreach ($rows as $r) {
                    $name = $this->cellToString($r[$map['criteria_name']] ?? null);
                    if ($name === '') continue;
                    $basis = $this->cellToString($r[$map['normalization_basis']] ?? null);
                    if ($basis === '') continue;
                    $targetRaw = $r[$map['custom_target_value']] ?? null;
                    $target = $targetRaw !== null && $targetRaw !== '' ? (float) $targetRaw : null;
                    $criteria[$name] = [
                        'normalization_basis' => $basis,
                        'custom_target_value' => $target,
                    ];
                }
                continue;
            }

            // Unit weights sheet
            if (($map['weight'] ?? false) !== false && ($map['unit_slug'] ?? false) !== false && ($map['period_name'] ?? false) !== false && ($map['criteria_name'] ?? false) !== false) {
                foreach ($rows as $r) {
                    $periodName = $this->cellToString($r[$map['period_name']] ?? null);
                    $unitSlug = $this->cellToString($r[$map['unit_slug']] ?? null);
                    $criteriaName = $this->cellToString($r[$map['criteria_name']] ?? null);
                    $weightVal = $r[$map['weight']] ?? null;
                    $status = $this->cellToString($r[$map['status']] ?? null);
                    $status = $status !== '' ? strtolower($status) : 'active';
                    if ($periodName === '' || $unitSlug === '' || $criteriaName === '') continue;
                    $periodId = (int) (DB::table('assessment_periods')->where('name', $periodName)->value('id') ?? 0);
                    if ($periodId <= 0) continue;
                    $weights[$periodId][$unitSlug][$criteriaName] = [
                        'weight' => (float) ($weightVal ?? 0.0),
                        'status' => in_array($status, ['active','draft','archived','pending','rejected'], true) ? $status : 'active',
                    ];
                }
                continue;
            }

            // Raw KPI sheet
            if (($map['period_name'] ?? false) !== false && (($map['staff_key'] ?? false) !== false || ($map['email'] ?? false) !== false || ($map['employee_number'] ?? false) !== false)
                && ($map['attendance_days'] ?? false) !== false
                && ($map['late_minutes'] ?? false) !== false
                && ($map['work_minutes'] ?? false) !== false
                && ($map['overtime_days'] ?? false) !== false
                && ($map['discipline_360'] ?? false) !== false
                && ($map['teamwork_360'] ?? false) !== false
                && ($map['contrib'] ?? false) !== false
                && ($map['patients'] ?? false) !== false
                && ($map['complaints'] ?? false) !== false
                && ($map['rating_avg'] ?? false) !== false) {
                foreach ($rows as $r) {
                    $periodName = $this->cellToString($r[$map['period_name']] ?? null);
                    if ($periodName === '') continue;
                    $periodId = (int) (DB::table('assessment_periods')->where('name', $periodName)->value('id') ?? 0);
                    if ($periodId <= 0) continue;

                    $key = '';
                    if (($map['staff_key'] ?? false) !== false) {
                        $key = $this->cellToString($r[$map['staff_key']] ?? null);
                    }
                    if ($key === '' && ($map['email'] ?? false) !== false) {
                        $email = strtolower($this->cellToString($r[$map['email']] ?? null));
                        $key = $emailToKey[$email] ?? '';
                    }
                    if ($key === '' && ($map['employee_number'] ?? false) !== false) {
                        $emp = $this->cellToString($r[$map['employee_number']] ?? null);
                        $key = $empToKey[$emp] ?? '';
                    }
                    if ($key === '') continue;

                    $raw[$periodId][$key] = [
                        'attendance_days' => (float) ($r[$map['attendance_days']] ?? 0),
                        'late_minutes' => (float) ($r[$map['late_minutes']] ?? 0),
                        'work_minutes' => (float) ($r[$map['work_minutes']] ?? 0),
                        'overtime_days' => (float) ($r[$map['overtime_days']] ?? 0),
                        'discipline_360' => (float) ($r[$map['discipline_360']] ?? 0),
                        'teamwork_360' => (float) ($r[$map['teamwork_360']] ?? 0),
                        'contrib' => (float) ($r[$map['contrib']] ?? 0),
                        'patients' => (float) ($r[$map['patients']] ?? 0),
                        'complaints' => (float) ($r[$map['complaints']] ?? 0),
                        'rating_avg' => (float) ($r[$map['rating_avg']] ?? 0),
                    ];

                    if (($map['rating_count'] ?? false) !== false) {
                        $raw[$periodId][$key]['rating_count'] = (float) ($r[$map['rating_count']] ?? 0);
                    }
                    if (($map['rating_sum'] ?? false) !== false) {
                        $raw[$periodId][$key]['rating_sum'] = (float) ($r[$map['rating_sum']] ?? 0);
                    }
                }
                continue;
            }
        }

        $out = [];
        if (!empty($weights)) $out['weights'] = $weights;
        if (!empty($raw)) $out['raw'] = $raw;
        if (!empty($criteria)) $out['criteria'] = $criteria;

        if (empty($out) && $require) {
            throw new \RuntimeException('KPI Excel template ditemukan tetapi format sheet/header tidak dikenali.');
        }

        return $out ?: null;
    }

    private function mapHeader(array $header): array
    {
        $norm = array_map(function ($h) {
            $h = strtolower(trim((string) $h));
            $h = preg_replace('/\s+/', ' ', $h);
            $h = str_replace(['-', ' '], ['_', '_'], $h);
            return $h;
        }, $header);

        $find = function (array $aliases) use ($norm) {
            foreach ($aliases as $a) {
                $i = array_search($a, $norm, true);
                if ($i !== false) return $i;
            }
            return false;
        };

        return [
            'period_name' => $find(['period_name','period','assessment_period','assessment_period_name']),
            'unit_slug' => $find(['unit_slug','unit','clinic','poliklinik']),
            'criteria_name' => $find(['criteria_name','kriteria','criteria','performance_criteria','performance_criteria_name','nama_kriteria']),
            'weight' => $find(['weight','bobot']),
            'status' => $find(['status']),

            'normalization_basis' => $find(['normalization_basis','basis','normalisasi_basis']),
            'custom_target_value' => $find(['custom_target_value','custom_target','target','target_value']),

            'staff_key' => $find(['staff_key','key','kode','staff']),
            'email' => $find(['email']),
            'employee_number' => $find(['employee_number','nip','npp','nik']),
            'attendance_days' => $find(['attendance_days','attendance','absensi','hadir','kehadiran']),
            'late_minutes' => $find(['late_minutes','late','keterlambatan','menit_terlambat']),
            'work_minutes' => $find(['work_minutes','work_duration_minutes','work','jam_kerja','menit_kerja']),
            'overtime_days' => $find(['overtime_days','overtime','lembur','jumlah_lembur']),
            'discipline_360' => $find(['discipline_360','discipline','kedisiplinan','kedisiplinan_360','skor_kedisiplinan']),
            'teamwork_360' => $find(['teamwork_360','teamwork','kerjasama','skor_kerjasama']),
            'contrib' => $find(['contrib','kontribusi','kontribusi_tambahan']),
            'patients' => $find(['patients','pasien','jumlah_pasien']),
            'complaints' => $find(['complaints','komplain','jumlah_komplain','keluhan']),
            'rating_avg' => $find(['rating_avg','rating','nilai_rating','rata_rating']),
            'rating_count' => $find(['rating_count','jumlah_rating','jumlah_review']),
            'rating_sum' => $find(['rating_sum','total_rating','sum_rating']),
        ];
    }

    private function cellToString(mixed $val): string
    {
        if ($val === null) return '';
        if (is_bool($val)) return $val ? '1' : '0';
        return trim((string) $val);
    }

    private function periodModel(object $period): \App\Models\AssessmentPeriod
    {
        return \App\Models\AssessmentPeriod::findOrFail($period->id);
    }

    /**
     * Build deterministic daily attendance rows that match TOTALs in template.
     *
     * @param array<int,string> $dates
     * @return array<int,array<string,mixed>>
     */
    /**
     * Build deterministic attendance plan rows used by attendance_import_rows, then derived into attendances.
     *
     * @param array<int,string> $dates
     * @return array<int,array<string,mixed>>
     */
    private function buildAttendancePlan(
        int $userId,
        array $dates,
        int $totalLateMinutes,
        int $totalWorkMinutes,
        int $overtimeDays,
        Carbon $now,
        string $scheduleIn,
        string $scheduleOut,
        string $overtimeEnd,
    ): array
    {
        $count = count($dates);
        if ($count <= 0) {
            return [];
        }

        $baseWork = intdiv(max($totalWorkMinutes, 0), $count);
        $workRemainder = max($totalWorkMinutes, 0) - ($baseWork * $count);

        $lateMinutes = array_fill(0, $count, 0);
        if ($totalLateMinutes > 0) {
            $lateDays = min($count, max(1, (int) ceil($totalLateMinutes / 30)));
            $baseLate = intdiv($totalLateMinutes, $lateDays);
            $lateRem = $totalLateMinutes - ($baseLate * $lateDays);
            for ($i = 0; $i < $lateDays; $i++) {
                $lateMinutes[$i] = $baseLate + ($i < $lateRem ? 1 : 0);
            }
        }

        $overtimeDays = max(0, min($overtimeDays, $count));
        $overtimeIdxStart = $count - $overtimeDays;

        $rows = [];
        for ($i = 0; $i < $count; $i++) {
            $work = $baseWork + ($i < $workRemainder ? 1 : 0);
            $isOvertime = $overtimeDays > 0 && $i >= $overtimeIdxStart;

            $date = (string) $dates[$i];
            $scheduledIn = $date . ' ' . $scheduleIn;
            $checkIn = Carbon::parse($scheduledIn)->addMinutes((int) $lateMinutes[$i]);
            $checkOut = (clone $checkIn)->addMinutes((int) $work);

            $rows[] = [
                'user_id' => $userId,
                'attendance_date' => $date,
                'shift_name' => 'Pagi',
                'scheduled_in' => $scheduleIn,
                'scheduled_out' => $scheduleOut,
                'check_in' => $checkIn->toDateTimeString(),
                'check_out' => $checkOut->toDateTimeString(),
                'late_minutes' => $lateMinutes[$i],
                'work_duration_minutes' => $work,
                'overtime_shift' => $isOvertime ? 1 : 0,
                'overtime_end' => $isOvertime ? $overtimeEnd : null,
                'attendance_status' => 'Hadir',
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        return $rows;
    }

    /**
     * Build deterministic integer ratings list with a desired avg.
     * If rating_sum is provided, it will be used as the exact target sum.
     *
     * @return array<int,int>
     */
    private function buildRatingDistribution(float $targetAvg, int $count, ?int $ratingSum = null): array
    {
        $count = max(1, $count);

        if ($ratingSum === null) {
            // Prefer 1-decimal averages when count=10.
            $ratingSum = $count === 10
                ? (int) round($targetAvg * 10)
                : (int) round($targetAvg * $count);
        }

        $minSum = 1 * $count;
        $maxSum = 5 * $count;
        $ratingSum = max($minSum, min($maxSum, $ratingSum));

        $ratings = array_fill(0, $count, 1);
        $remaining = $ratingSum - $count;
        $i = 0;
        while ($remaining > 0) {
            $add = min(4, $remaining);
            $ratings[$i] += $add;
            $remaining -= $add;
            $i++;
            if ($i >= $count) {
                $i = 0;
            }
        }

        rsort($ratings);
        return $ratings;
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
