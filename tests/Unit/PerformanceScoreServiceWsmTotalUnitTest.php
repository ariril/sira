<?php

namespace Tests\Unit;

use App\Models\AssessmentPeriod;
use App\Models\CriteriaMetric;
use App\Models\MetricImportBatch;
use App\Models\PerformanceCriteria;
use App\Models\Unit;
use App\Models\UnitCriteriaWeight;
use App\Models\User;
use App\Services\PerformanceScore\PerformanceScoreService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PerformanceScoreServiceWsmTotalUnitTest extends TestCase
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

    public function test_inactive_criteria_is_shown_but_excluded_from_wsm(): void
    {
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

        $activeCriteria = PerformanceCriteria::create([
            'name' => 'Jumlah Pasien Ditangani',
            'source' => 'metric_import',
            'type' => 'benefit',
            'data_type' => 'numeric',
            'input_method' => 'import',
            'aggregation_method' => 'sum',
            'normalization_basis' => 'total_unit',
            'is_active' => 1,
            'is_360' => 0,
        ]);

        $inactiveCriteria = PerformanceCriteria::create([
            'name' => 'Jumlah Komplain Pasien',
            'source' => 'metric_import',
            'type' => 'cost',
            'data_type' => 'numeric',
            'input_method' => 'import',
            'aggregation_method' => 'sum',
            'normalization_basis' => 'total_unit',
            'is_active' => 0,
            'is_360' => 0,
        ]);

        UnitCriteriaWeight::create([
            'unit_id' => $unit->id,
            'assessment_period_id' => $period->id,
            'performance_criteria_id' => $activeCriteria->id,
            'weight' => 20,
            'status' => 'active',
        ]);

        // Has weight but criteria is_active=false -> should be excluded from WSM total.
        UnitCriteriaWeight::create([
            'unit_id' => $unit->id,
            'assessment_period_id' => $period->id,
            'performance_criteria_id' => $inactiveCriteria->id,
            'weight' => 80,
            'status' => 'active',
        ]);

        $u1 = $this->makeUser('u1@test.local', $unit->id);
        $u2 = $this->makeUser('u2@test.local', $unit->id);

        $batch = MetricImportBatch::create([
            'file_name' => 'test.csv',
            'assessment_period_id' => $period->id,
            'imported_by' => $u1->id,
            'status' => 'processed',
        ]);

        // Active benefit (total_unit): u1=10, u2=30 => norm: 25, 75
        foreach ([[$u1, 10], [$u2, 30]] as [$user, $val]) {
            CriteriaMetric::create([
                'import_batch_id' => $batch->id,
                'user_id' => $user->id,
                'assessment_period_id' => $period->id,
                'performance_criteria_id' => $activeCriteria->id,
                'value_numeric' => $val,
            ]);
        }

        // Inactive cost data (still displayed but excluded)
        foreach ([[$u1, 1], [$u2, 2]] as [$user, $val]) {
            CriteriaMetric::create([
                'import_batch_id' => $batch->id,
                'user_id' => $user->id,
                'assessment_period_id' => $period->id,
                'performance_criteria_id' => $inactiveCriteria->id,
                'value_numeric' => $val,
            ]);
        }

        /** @var PerformanceScoreService $svc */
        $svc = app(PerformanceScoreService::class);
        $out = $svc->calculate($unit->id, $period, [$u1->id, $u2->id]);

        // Total should be computed only from activeCriteria and uses relative score (0–100).
        // Active benefit (total_unit): u1=10, u2=30 => norm: 25, 75 => relative: 33.33, 100
        $this->assertEqualsWithDelta((25.0 / 75.0) * 100.0, (float) $out['users'][$u1->id]['total_wsm'], 0.0001);
        $this->assertEqualsWithDelta(100.0, (float) $out['users'][$u2->id]['total_wsm'], 0.0001);

        $rows1 = $out['users'][$u1->id]['criteria'] ?? [];
        $activeRow1 = collect($rows1)->firstWhere('criteria_id', (int) $activeCriteria->id);
        $inactiveRow1 = collect($rows1)->firstWhere('criteria_id', (int) $inactiveCriteria->id);

        $this->assertNotNull($activeRow1);
        $this->assertNotNull($inactiveRow1);

        $this->assertTrue((bool) $activeRow1['included_in_wsm']);
        $this->assertFalse((bool) $inactiveRow1['included_in_wsm']);

        // Relative-unit is based on max normalized within unit (u2 is max=75 for active criteria)
        $this->assertEqualsWithDelta((25.0 / 75.0) * 100.0, (float) $activeRow1['nilai_relativ_unit'], 0.01);

        $rows2 = $out['users'][$u2->id]['criteria'] ?? [];
        $activeRow2 = collect($rows2)->firstWhere('criteria_id', (int) $activeCriteria->id);
        $this->assertNotNull($activeRow2);
        $this->assertEqualsWithDelta(100.0, (float) $activeRow2['nilai_relativ_unit'], 0.001);
    }

    public function test_cost_relative_uses_min_normalized_and_top_performer_is_100_when_min_is_zero(): void
    {
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

        $costCriteria = PerformanceCriteria::create([
            'name' => 'Jumlah Komplain Pasien',
            'source' => 'metric_import',
            'type' => 'cost',
            'data_type' => 'numeric',
            'input_method' => 'import',
            'aggregation_method' => 'sum',
            'normalization_basis' => 'total_unit',
            'is_active' => 1,
            'is_360' => 0,
        ]);

        UnitCriteriaWeight::create([
            'unit_id' => $unit->id,
            'assessment_period_id' => $period->id,
            'performance_criteria_id' => $costCriteria->id,
            'weight' => 100,
            'status' => 'active',
        ]);

        $u1 = $this->makeUser('u1@test.local', $unit->id);
        $u2 = $this->makeUser('u2@test.local', $unit->id);
        $u3 = $this->makeUser('u3@test.local', $unit->id);

        $batch = MetricImportBatch::create([
            'file_name' => 'test.csv',
            'assessment_period_id' => $period->id,
            'imported_by' => $u1->id,
            'status' => 'processed',
        ]);

        // COST raw values: u1=0, u2=10, u3=20
        // total_unit normalization: sum=30 => norm: 0, 33.333..., 66.666...
        // relative (COST): min/norm*100 => u1 (min=0, norm=0) treated as top performer => 100; others => 0
        foreach ([[$u1, 0], [$u2, 10], [$u3, 20]] as [$user, $val]) {
            CriteriaMetric::create([
                'import_batch_id' => $batch->id,
                'user_id' => $user->id,
                'assessment_period_id' => $period->id,
                'performance_criteria_id' => $costCriteria->id,
                'value_numeric' => $val,
            ]);
        }

        /** @var PerformanceScoreService $svc */
        $svc = app(PerformanceScoreService::class);
        $out = $svc->calculate($unit->id, $period, [$u1->id, $u2->id, $u3->id]);

        $rowU1 = collect($out['users'][$u1->id]['criteria'] ?? [])->firstWhere('criteria_id', (int) $costCriteria->id);
        $rowU2 = collect($out['users'][$u2->id]['criteria'] ?? [])->firstWhere('criteria_id', (int) $costCriteria->id);
        $rowU3 = collect($out['users'][$u3->id]['criteria'] ?? [])->firstWhere('criteria_id', (int) $costCriteria->id);

        $this->assertNotNull($rowU1);
        $this->assertNotNull($rowU2);
        $this->assertNotNull($rowU3);

        $this->assertEqualsWithDelta(0.0, (float) $rowU1['nilai_normalisasi'], 0.0001);
        $this->assertEqualsWithDelta((10.0 / 30.0) * 100.0, (float) $rowU2['nilai_normalisasi'], 0.0001);
        $this->assertEqualsWithDelta((20.0 / 30.0) * 100.0, (float) $rowU3['nilai_normalisasi'], 0.0001);

        $this->assertEqualsWithDelta(100.0, (float) $rowU1['nilai_relativ_unit'], 0.0001);
        $this->assertEqualsWithDelta(0.0, (float) $rowU2['nilai_relativ_unit'], 0.0001);
        $this->assertEqualsWithDelta(0.0, (float) $rowU3['nilai_relativ_unit'], 0.0001);

        // Weight=100 => total WSM equals relative score.
        $this->assertEqualsWithDelta(100.0, (float) $out['users'][$u1->id]['total_wsm'], 0.0001);
        $this->assertEqualsWithDelta(0.0, (float) $out['users'][$u2->id]['total_wsm'], 0.0001);
        $this->assertEqualsWithDelta(0.0, (float) $out['users'][$u3->id]['total_wsm'], 0.0001);
    }
}
