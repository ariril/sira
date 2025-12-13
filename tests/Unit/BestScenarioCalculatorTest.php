<?php

namespace Tests\Unit;

use App\Enums\ContributionValidationStatus;
use App\Enums\ReviewStatus;
use App\Models\AdditionalContribution;
use App\Models\AssessmentPeriod;
use App\Models\Attendance;
use App\Models\CriteriaMetric;
use App\Models\PerformanceCriteria;
use App\Models\Review;
use App\Models\ReviewDetail;
use App\Models\Unit;
use App\Models\User;
use App\Services\BestScenarioCalculator;
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
            ['name' => 'Absensi', 'type' => 'benefit', 'data_type' => 'numeric', 'input_method' => 'import', 'aggregation_method' => 'sum', 'is_active' => 1],
            ['name' => 'Kedisiplinan (360)', 'type' => 'benefit', 'data_type' => 'numeric', 'input_method' => '360', 'aggregation_method' => 'avg', 'is_active' => 1],
            ['name' => 'Kontribusi Tambahan', 'type' => 'benefit', 'data_type' => 'numeric', 'input_method' => 'manual', 'aggregation_method' => 'sum', 'is_active' => 1],
            ['name' => 'Jumlah Pasien Ditangani', 'type' => 'benefit', 'data_type' => 'numeric', 'input_method' => 'import', 'aggregation_method' => 'sum', 'is_active' => 1],
            ['name' => 'Rating', 'type' => 'benefit', 'data_type' => 'numeric', 'input_method' => 'public_review', 'aggregation_method' => 'avg', 'is_active' => 1],
        ];
        foreach ($data as $row) {
            PerformanceCriteria::create($row);
        }
    }

    public function test_weights_renormalize_when_some_criteria_missing(): void
    {
        $this->seedCriteria();

        $unit = Unit::create([
            'name' => 'Unit Test',
            'slug' => 'unit-test',
            'code' => 'UT',
            'type' => 'test',
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

        // Absensi: totals 3,2,0
        foreach ([[$u1,3],[$u2,2]] as [$user,$count]) {
            for ($i=0; $i<$count; $i++) {
                Attendance::create([
                    'user_id' => $user->id,
                    'attendance_date' => $period->start_date,
                    'attendance_status' => 'Hadir',
                ]);
            }
        }

        // Kontribusi: user2 has 30 pts, user3 has 35 pts
        foreach ([[$u2,30],[$u3,35]] as [$user,$score]) {
            AdditionalContribution::create([
                'user_id' => $user->id,
                'title' => 'Kontribusi',
                'submission_date' => $period->start_date,
                'validation_status' => ContributionValidationStatus::APPROVED,
                'assessment_period_id' => $period->id,
                'score' => $score,
            ]);
        }

        // Pasien metrics: user1 120, user2 139, user3 157
        $pasienId = PerformanceCriteria::where('name','Jumlah Pasien Ditangani')->value('id');
        foreach ([[$u1,120],[$u2,139],[$u3,157]] as [$user,$val]) {
            CriteriaMetric::create([
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

        $svc = new BestScenarioCalculator();
        $out = $svc->calculateForUnit($unit->id, $period, [$u1->id, $u2->id, $u3->id]);

        // Kedisiplinan has zero data -> dropped. Active criteria = 4 -> weight 25 each.
        $this->assertEquals(['absensi','kontribusi','pasien','rating'], $out['criteria_used']);
        foreach ($out['weights'] as $k => $w) {
            if (in_array($k, $out['criteria_used'])) {
                $this->assertEquals(25.0, round($w,2));
            }
        }

        // Unit total should be > 0 and user rows exist
        $this->assertGreaterThan(0, $out['unit_total']);
        $this->assertArrayHasKey($u1->id, $out['users']);
    }
}
