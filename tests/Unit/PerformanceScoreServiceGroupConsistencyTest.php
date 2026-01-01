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

class PerformanceScoreServiceGroupConsistencyTest extends TestCase
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

    public function test_group_invariants_sum_normalisasi_100_top_relatif_100_and_total_wsm_consistent(): void
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

        $benefit = PerformanceCriteria::create([
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

        $cost = PerformanceCriteria::create([
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
            'performance_criteria_id' => $benefit->id,
            'weight' => 60,
            'status' => 'active',
        ]);

        UnitCriteriaWeight::create([
            'unit_id' => $unit->id,
            'assessment_period_id' => $period->id,
            'performance_criteria_id' => $cost->id,
            'weight' => 40,
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

        // BENEFIT raw values: 10, 30, 60 => sum=100 => normalized: 10, 30, 60 (sum=100)
        foreach ([[$u1, 10], [$u2, 30], [$u3, 60]] as [$user, $val]) {
            CriteriaMetric::create([
                'import_batch_id' => $batch->id,
                'user_id' => $user->id,
                'assessment_period_id' => $period->id,
                'performance_criteria_id' => $benefit->id,
                'value_numeric' => $val,
            ]);
        }

        // COST raw values: 5, 10, 15 => sum=30 => normalized: 16.666.., 33.333.., 50
        foreach ([[$u1, 5], [$u2, 10], [$u3, 15]] as [$user, $val]) {
            CriteriaMetric::create([
                'import_batch_id' => $batch->id,
                'user_id' => $user->id,
                'assessment_period_id' => $period->id,
                'performance_criteria_id' => $cost->id,
                'value_numeric' => $val,
            ]);
        }

        /** @var PerformanceScoreService $svc */
        $svc = app(PerformanceScoreService::class);
        $out = $svc->calculate($unit->id, $period, [$u1->id, $u2->id, $u3->id]);

        // Invariant #1: Sum normalized per criteria = 100 (when peer total > 0).
        foreach ([$benefit->id, $cost->id] as $cid) {
            $sumNorm = 0.0;
            $maxRel = 0.0;
            foreach ([$u1->id, $u2->id, $u3->id] as $uid) {
                $row = collect($out['users'][$uid]['criteria'] ?? [])->firstWhere('criteria_id', (int) $cid);
                $this->assertNotNull($row);
                $sumNorm += (float) ($row['nilai_normalisasi'] ?? 0.0);
                $maxRel = max($maxRel, (float) ($row['nilai_relativ_unit'] ?? 0.0));
            }

            $this->assertEqualsWithDelta(100.0, $sumNorm, 0.0001);

            // Invariant #2: Highest relative = 100 (when max normalized > 0).
            $this->assertEqualsWithDelta(100.0, $maxRel, 0.0001);
        }

        // Invariant #3: Total WSM matches Σ(w×rel)/Σ(w) for active criteria.
        $wBenefit = 60.0;
        $wCost = 40.0;
        foreach ([$u1->id, $u2->id, $u3->id] as $uid) {
            $rows = collect($out['users'][$uid]['criteria'] ?? []);
            $rowB = $rows->firstWhere('criteria_id', (int) $benefit->id);
            $rowC = $rows->firstWhere('criteria_id', (int) $cost->id);

            $this->assertNotNull($rowB);
            $this->assertNotNull($rowC);

            $relB = (float) ($rowB['nilai_relativ_unit'] ?? 0.0);
            $relC = (float) ($rowC['nilai_relativ_unit'] ?? 0.0);

            $expected = (($wBenefit * $relB) + ($wCost * $relC)) / ($wBenefit + $wCost);
            $this->assertEqualsWithDelta($expected, (float) $out['users'][$uid]['total_wsm'], 0.0001);
        }
    }

    public function test_total_unit_denominator_sum_raw_by_criteria_is_exposed_and_correct(): void
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

        $benefit = PerformanceCriteria::create([
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

        $cost = PerformanceCriteria::create([
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
            'performance_criteria_id' => $benefit->id,
            'weight' => 50,
            'status' => 'active',
        ]);

        UnitCriteriaWeight::create([
            'unit_id' => $unit->id,
            'assessment_period_id' => $period->id,
            'performance_criteria_id' => $cost->id,
            'weight' => 50,
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

        // benefit raw: 10, 20 => sum_raw=30
        foreach ([[$u1, 10], [$u2, 20]] as [$user, $val]) {
            CriteriaMetric::create([
                'import_batch_id' => $batch->id,
                'user_id' => $user->id,
                'assessment_period_id' => $period->id,
                'performance_criteria_id' => $benefit->id,
                'value_numeric' => $val,
            ]);
        }

        // cost raw: 1, 4 => sum_raw=5
        foreach ([[$u1, 1], [$u2, 4]] as [$user, $val]) {
            CriteriaMetric::create([
                'import_batch_id' => $batch->id,
                'user_id' => $user->id,
                'assessment_period_id' => $period->id,
                'performance_criteria_id' => $cost->id,
                'value_numeric' => $val,
            ]);
        }

        /** @var PerformanceScoreService $svc */
        $svc = app(PerformanceScoreService::class);
        $out = $svc->calculate($unit->id, $period, [$u1->id, $u2->id]);

        $this->assertArrayHasKey('sum_raw_by_criteria', $out);
        $this->assertEqualsWithDelta(30.0, (float) ($out['sum_raw_by_criteria'][(int) $benefit->id] ?? -1), 0.0001);
        $this->assertEqualsWithDelta(5.0, (float) ($out['sum_raw_by_criteria'][(int) $cost->id] ?? -1), 0.0001);
    }

    public function test_edge_case_peer_total_zero_yields_zero_normalisasi_zero_relatif_and_total_wsm_null_when_missing_data(): void
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

        $benefit = PerformanceCriteria::create([
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

        $cost = PerformanceCriteria::create([
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

        // Active weights exist, but there is no metric data at all => raw defaults to 0 for everyone.
        UnitCriteriaWeight::create([
            'unit_id' => $unit->id,
            'assessment_period_id' => $period->id,
            'performance_criteria_id' => $benefit->id,
            'weight' => 50,
            'status' => 'active',
        ]);

        UnitCriteriaWeight::create([
            'unit_id' => $unit->id,
            'assessment_period_id' => $period->id,
            'performance_criteria_id' => $cost->id,
            'weight' => 50,
            'status' => 'active',
        ]);

        $u1 = $this->makeUser('u1@test.local', $unit->id);
        $u2 = $this->makeUser('u2@test.local', $unit->id);

        /** @var PerformanceScoreService $svc */
        $svc = app(PerformanceScoreService::class);
        $out = $svc->calculate($unit->id, $period, [$u1->id, $u2->id]);

        // Peer totals (denominators) must be 0.
        $this->assertEqualsWithDelta(0.0, (float) ($out['sum_raw_by_criteria'][(int) $benefit->id] ?? -1), 0.0001);
        $this->assertEqualsWithDelta(0.0, (float) ($out['sum_raw_by_criteria'][(int) $cost->id] ?? -1), 0.0001);

        // For each criteria: all normalized are 0 => sum normalized is 0; max relative is 0.
        foreach ([$benefit->id, $cost->id] as $cid) {
            $sumNorm = 0.0;
            $maxRel = 0.0;
            foreach ([$u1->id, $u2->id] as $uid) {
                $row = collect($out['users'][$uid]['criteria'] ?? [])->firstWhere('criteria_id', (int) $cid);
                $this->assertNotNull($row);
                $sumNorm += (float) ($row['nilai_normalisasi'] ?? 0.0);
                $maxRel = max($maxRel, (float) ($row['nilai_relativ_unit'] ?? 0.0));
            }
            $this->assertEqualsWithDelta(0.0, $sumNorm, 0.0001);
            $this->assertEqualsWithDelta(0.0, $maxRel, 0.0001);
        }

        // Because metric_import readiness is missing_data, these criteria are excluded from WSM.
        $this->assertEqualsWithDelta(0.0, (float) ($out['users'][$u1->id]['sum_weight'] ?? -1), 0.0001);
        $this->assertEqualsWithDelta(0.0, (float) ($out['users'][$u2->id]['sum_weight'] ?? -1), 0.0001);
        $this->assertNull($out['users'][$u1->id]['total_wsm'] ?? null);
        $this->assertNull($out['users'][$u2->id]['total_wsm'] ?? null);
    }
}
