<?php

namespace Tests\Unit;

use App\Enums\ReviewStatus;
use App\Models\AdditionalTask;
use App\Models\AdditionalTaskClaim;
use App\Models\AssessmentPeriod;
use App\Models\Attendance;
use App\Models\CriteriaMetric;
use App\Models\MetricImportBatch;
use App\Models\PerformanceCriteria;
use App\Models\Review;
use App\Models\ReviewDetail;
use App\Models\Unit;
use App\Models\UnitCriteriaWeight;
use App\Models\User;
use App\Services\CriteriaEngine\PerformanceScoreService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BestScenarioCalculatorTest extends TestCase
{
    use RefreshDatabase;

    private function makeUser(string $email, int $unitId): User
    {
        return User::create([
            'employee_number' => uniqid('emp'),
            'name' => $email,
            'start_date' => now()->toDateString(),
            'gender' => 'Laki-laki',
            'nationality' => 'ID',
            'address' => 'Test',
            'phone' => '0800',
            'email' => $email,
            'last_education' => 'S1',
            'position' => 'Tester',
            'unit_id' => $unitId,
            'profession_id' => null,
            'password' => 'password',
            'last_role' => User::ROLE_PEGAWAI_MEDIS,
        ]);
    }

    private function seedCriteria(): void
    {
        $data = [
            // IMPORTANT: system criteria names must match CriteriaRegistry mapping.
            ['name' => 'Kehadiran (Absensi)', 'source' => 'system', 'type' => 'benefit', 'data_type' => 'numeric', 'input_method' => 'system', 'aggregation_method' => 'sum', 'is_active' => 1],
            ['name' => 'Kedisiplinan (360)', 'source' => 'assessment_360', 'type' => 'benefit', 'data_type' => 'numeric', 'input_method' => '360', 'is_360' => 1, 'aggregation_method' => 'avg', 'is_active' => 1],
            ['name' => 'Tugas Tambahan', 'source' => 'system', 'type' => 'benefit', 'data_type' => 'numeric', 'input_method' => 'system', 'aggregation_method' => 'sum', 'is_active' => 1],
            ['name' => 'Jumlah Pasien Ditangani', 'source' => 'metric_import', 'type' => 'benefit', 'data_type' => 'numeric', 'input_method' => 'import', 'aggregation_method' => 'sum', 'is_active' => 1],
            ['name' => 'Rating', 'source' => 'system', 'type' => 'benefit', 'data_type' => 'numeric', 'input_method' => 'system', 'aggregation_method' => 'avg', 'is_active' => 1],
        ];
        foreach ($data as $row) {
            PerformanceCriteria::create($row);
        }
    }

    public function test_missing_data_criteria_still_included_when_configured_active(): void
    {
        $this->seedCriteria();

        $unit = Unit::create([
            'name' => 'Unit Test',
            'slug' => 'unit-test',
            'code' => 'UT',
            'type' => 'lainnya',
            'is_active' => true,
        ]);

        $period = AssessmentPeriod::create([
            'name' => 'Periode Uji',
            'start_date' => now()->startOfMonth()->toDateString(),
            'end_date' => now()->endOfMonth()->toDateString(),
            'status' => 'active',
        ]);

        $u1 = $this->makeUser('u1@test.local', $unit->id);
        $u2 = $this->makeUser('u2@test.local', $unit->id);
        $u3 = $this->makeUser('u3@test.local', $unit->id);

        // Active criteria comes from configuration (unit_criteria_weights), not from totals.
        $attendanceId = (int) PerformanceCriteria::where('name', 'Kehadiran (Absensi)')->value('id');
        $contributionId = (int) PerformanceCriteria::where('name', 'Tugas Tambahan')->value('id');
        $pasienId = (int) PerformanceCriteria::where('name', 'Jumlah Pasien Ditangani')->value('id');
        $ratingId = (int) PerformanceCriteria::where('name', 'Rating')->value('id');
        $kedisiplinanId = (int) PerformanceCriteria::where('name', 'Kedisiplinan (360)')->value('id');

        foreach ([$attendanceId, $contributionId, $pasienId, $ratingId, $kedisiplinanId] as $cid) {
            UnitCriteriaWeight::create([
                'unit_id' => $unit->id,
                'assessment_period_id' => $period->id,
                'performance_criteria_id' => $cid,
                'weight' => 20,
                'status' => 'active',
            ]);
        }

        // Absensi: totals 3,2,0
        foreach ([[$u1,3],[$u2,2]] as [$user,$count]) {
            for ($i=0; $i<$count; $i++) {
                Attendance::create([
                    'user_id' => $user->id,
                    'attendance_date' => \Carbon\Carbon::parse($period->start_date)->addDays($i)->toDateString(),
                    'attendance_status' => 'Hadir',
                ]);
            }
        }

        // Tugas Tambahan: user2 has 30 pts, user3 has 35 pts
        foreach ([[$u2,30],[$u3,35]] as [$user,$points]) {
            $task = AdditionalTask::create([
                'unit_id' => $unit->id,
                'assessment_period_id' => $period->id,
                'title' => 'Tugas Tambahan',
                'description' => 'Test',
                'start_date' => $period->start_date,
                'due_date' => $period->end_date,
                'points' => $points,
                'status' => 'open',
                'created_by' => null,
            ]);

            AdditionalTaskClaim::create([
                'additional_task_id' => $task->id,
                'user_id' => $user->id,
                'status' => 'approved',
                'claimed_at' => now(),
                'completed_at' => now(),
                'awarded_points' => $points,
            ]);
        }

        $importBatch = MetricImportBatch::create([
            'file_name' => 'test.csv',
            'assessment_period_id' => $period->id,
            'imported_by' => $u1->id,
            'status' => 'processed',
        ]);

        // Pasien metrics: user1 120, user2 139, user3 157
        foreach ([[$u1,120],[$u2,139],[$u3,157]] as [$user,$val]) {
            CriteriaMetric::create([
                'import_batch_id' => $importBatch->id,
                'user_id' => $user->id,
                'assessment_period_id' => $period->id,
                'performance_criteria_id' => $pasienId,
                'value_numeric' => $val,
            ]);
        }

        // Rating: user1=4.7, user2=4.8, user3=5
        $review = Review::create([
            'registration_ref' => 'REG-1',
            'unit_id' => $unit->id,
            'overall_rating' => 5,
            'comment' => 'ok',
            'patient_name' => 'X',
            'contact' => '08',
            'status' => ReviewStatus::APPROVED,
            'decided_at' => $period->start_date,
        ]);
        foreach ([[$u1,4.7],[$u2,4.8],[$u3,5.0]] as [$user,$rating]) {
            ReviewDetail::create([
                'review_id' => $review->id,
                'medical_staff_id' => $user->id,
                'role' => 'dokter',
                'rating' => $rating,
            ]);
        }

        /** @var PerformanceScoreService $svc */
        $svc = app(PerformanceScoreService::class);
        $out = $svc->calculate($unit->id, $period, [$u1->id, $u2->id, $u3->id]);

        $expectedKeys = [
            'attendance',
            'contribution',
            'metric:' . $pasienId,
            'rating',
            '360:' . $kedisiplinanId,
        ];

        // Missing-data criteria (360) must still be included if configured active.
        $this->assertEqualsCanonicalizing($expectedKeys, $out['criteria_used']);

        foreach ($expectedKeys as $key) {
            $this->assertEquals(20.0, round((float) ($out['weights'][$key] ?? 0), 2));
        }

        $this->assertEquals('missing_data', (string) ($out['criteria_meta']['360:' . $kedisiplinanId]['readiness_status'] ?? ''));

        // Unit total should be > 0 due to existing absensi/pasien/rating/contribution
        $this->assertGreaterThan(0, (float) ($out['unit_total'] ?? 0));
        $this->assertArrayHasKey($u1->id, $out['users']);

        // 360 criteria should contribute 0 for all users when there is no data.
        $u1Rows = $out['users'][$u1->id]['criteria'] ?? [];
        $row360 = collect($u1Rows)->firstWhere('key', '360:' . $kedisiplinanId);
        $this->assertNotNull($row360);
        $this->assertEquals(0.0, (float) ($row360['raw'] ?? -1));
    }
}
