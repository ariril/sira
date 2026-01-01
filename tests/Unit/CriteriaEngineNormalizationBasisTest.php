<?php

namespace Tests\Unit;

use App\Models\AssessmentPeriod;
use App\Models\CriteriaMetric;
use App\Models\MetricImportBatch;
use App\Models\PerformanceCriteria;
use App\Models\Unit;
use App\Models\UnitCriteriaWeight;
use App\Models\User;
use App\Services\CriteriaEngine\PerformanceScoreService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CriteriaEngineNormalizationBasisTest extends TestCase
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

    private function seedMetricCriteria(string $basis, ?float $target = null): int
    {
        $c = PerformanceCriteria::create([
            'name' => 'Jumlah Pasien Ditangani',
            'source' => 'metric_import',
            'type' => 'benefit',
            'data_type' => 'numeric',
            'input_method' => 'import',
            'aggregation_method' => 'sum',
            'normalization_basis' => $basis,
            'custom_target_value' => $target,
            'is_active' => 1,
            'is_360' => 0,
        ]);

        return (int) $c->id;
    }

    private function seedMetricCostCriteria(string $basis, ?float $target = null): int
    {
        $c = PerformanceCriteria::create([
            'name' => 'Jumlah Komplain Pasien',
            'source' => 'metric_import',
            'type' => 'cost',
            'data_type' => 'numeric',
            'input_method' => 'import',
            'aggregation_method' => 'sum',
            'normalization_basis' => $basis,
            'custom_target_value' => $target,
            'is_active' => 1,
            'is_360' => 0,
        ]);

        return (int) $c->id;
    }

    public function test_max_unit_normalization_for_benefit(): void
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

        $criteriaId = $this->seedMetricCriteria('max_unit');

        UnitCriteriaWeight::create([
            'unit_id' => $unit->id,
            'assessment_period_id' => $period->id,
            'performance_criteria_id' => $criteriaId,
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

        foreach ([[$u1, 120], [$u2, 139], [$u3, 157]] as [$user, $val]) {
            CriteriaMetric::create([
                'import_batch_id' => $batch->id,
                'user_id' => $user->id,
                'assessment_period_id' => $period->id,
                'performance_criteria_id' => $criteriaId,
                'value_numeric' => $val,
            ]);
        }

        /** @var PerformanceScoreService $svc */
        $svc = app(PerformanceScoreService::class);
        $out = $svc->calculate($unit->id, $period, [$u1->id, $u2->id, $u3->id]);

        $key = 'metric:' . $criteriaId;
        $rows1 = $out['users'][$u1->id]['criteria'] ?? [];
        $row1 = collect($rows1)->firstWhere('key', $key);
        $this->assertNotNull($row1);

        // max = 157 => 120/157*100 = 76.433...
        $this->assertEqualsWithDelta(76.433, (float) $row1['normalized'], 0.01);

        $rows3 = $out['users'][$u3->id]['criteria'] ?? [];
        $row3 = collect($rows3)->firstWhere('key', $key);
        $this->assertEqualsWithDelta(100.0, (float) $row3['normalized'], 0.001);
    }

    public function test_custom_target_normalization_for_benefit(): void
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

        $criteriaId = $this->seedMetricCriteria('custom_target', 200);

        UnitCriteriaWeight::create([
            'unit_id' => $unit->id,
            'assessment_period_id' => $period->id,
            'performance_criteria_id' => $criteriaId,
            'weight' => 100,
            'status' => 'active',
        ]);

        $u1 = $this->makeUser('u1@test.local', $unit->id);

        $batch = MetricImportBatch::create([
            'file_name' => 'test.csv',
            'assessment_period_id' => $period->id,
            'imported_by' => $u1->id,
            'status' => 'processed',
        ]);

        CriteriaMetric::create([
            'import_batch_id' => $batch->id,
            'user_id' => $u1->id,
            'assessment_period_id' => $period->id,
            'performance_criteria_id' => $criteriaId,
            'value_numeric' => 157,
        ]);

        /** @var PerformanceScoreService $svc */
        $svc = app(PerformanceScoreService::class);
        $out = $svc->calculate($unit->id, $period, [$u1->id]);

        $key = 'metric:' . $criteriaId;
        $rows = $out['users'][$u1->id]['criteria'] ?? [];
        $row = collect($rows)->firstWhere('key', $key);
        $this->assertNotNull($row);

        // 157/200*100 = 78.5
        $this->assertEqualsWithDelta(78.5, (float) $row['normalized'], 0.001);
    }

    public function test_average_unit_normalization_for_cost(): void
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

        $criteriaId = $this->seedMetricCostCriteria('average_unit');

        UnitCriteriaWeight::create([
            'unit_id' => $unit->id,
            'assessment_period_id' => $period->id,
            'performance_criteria_id' => $criteriaId,
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

        // avg = (0 + 10 + 50000) / 3 = 16670
        // Rumus baku: (nilai individu / avg) * 100 (cost/benefit sama untuk normalisasi)
        foreach ([[$u1, 0], [$u2, 10], [$u3, 50000]] as [$user, $val]) {
            CriteriaMetric::create([
                'import_batch_id' => $batch->id,
                'user_id' => $user->id,
                'assessment_period_id' => $period->id,
                'performance_criteria_id' => $criteriaId,
                'value_numeric' => $val,
            ]);
        }

        /** @var PerformanceScoreService $svc */
        $svc = app(PerformanceScoreService::class);
        $out = $svc->calculate($unit->id, $period, [$u1->id, $u2->id, $u3->id]);

        $key = 'metric:' . $criteriaId;
        $row1 = collect($out['users'][$u1->id]['criteria'] ?? [])->firstWhere('key', $key);
        $row2 = collect($out['users'][$u2->id]['criteria'] ?? [])->firstWhere('key', $key);
        $row3 = collect($out['users'][$u3->id]['criteria'] ?? [])->firstWhere('key', $key);

        $this->assertNotNull($row1);
        $this->assertNotNull($row2);
        $this->assertNotNull($row3);

        $this->assertEqualsWithDelta(0.0, (float) $row1['normalized'], 0.001);
        $this->assertEqualsWithDelta((10 / 16670) * 100.0, (float) $row2['normalized'], 0.0001);
        $this->assertEqualsWithDelta((50000 / 16670) * 100.0, (float) $row3['normalized'], 0.01);
    }

    public function test_custom_target_normalization_for_cost(): void
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

        $criteriaId = $this->seedMetricCostCriteria('custom_target', 100);

        UnitCriteriaWeight::create([
            'unit_id' => $unit->id,
            'assessment_period_id' => $period->id,
            'performance_criteria_id' => $criteriaId,
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

        // Rumus baku: (nilai individu / target) * 100 (cost/benefit sama untuk normalisasi)
        foreach ([[$u1, 0], [$u2, 50], [$u3, 200]] as [$user, $val]) {
            CriteriaMetric::create([
                'import_batch_id' => $batch->id,
                'user_id' => $user->id,
                'assessment_period_id' => $period->id,
                'performance_criteria_id' => $criteriaId,
                'value_numeric' => $val,
            ]);
        }

        /** @var PerformanceScoreService $svc */
        $svc = app(PerformanceScoreService::class);
        $out = $svc->calculate($unit->id, $period, [$u1->id, $u2->id, $u3->id]);

        $key = 'metric:' . $criteriaId;
        $row1 = collect($out['users'][$u1->id]['criteria'] ?? [])->firstWhere('key', $key);
        $row2 = collect($out['users'][$u2->id]['criteria'] ?? [])->firstWhere('key', $key);
        $row3 = collect($out['users'][$u3->id]['criteria'] ?? [])->firstWhere('key', $key);

        $this->assertEqualsWithDelta(0.0, (float) $row1['normalized'], 0.001);
        $this->assertEqualsWithDelta(50.0, (float) $row2['normalized'], 0.001);
        $this->assertEqualsWithDelta(200.0, (float) $row3['normalized'], 0.001);
    }
}
