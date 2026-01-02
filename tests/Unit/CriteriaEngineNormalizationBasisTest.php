<?php

namespace Tests\Unit;

use App\Services\CriteriaEngine\Normalizers\AverageUnitNormalizer;
use App\Services\CriteriaEngine\Normalizers\CustomTargetNormalizer;
use App\Services\CriteriaEngine\Normalizers\MaxUnitNormalizer;
use App\Services\CriteriaEngine\Normalizers\TotalUnitNormalizer;
use Tests\TestCase;

class CriteriaEngineNormalizationBasisTest extends TestCase
{
    public function test_total_unit_benefit_sums_to_100(): void
    {
        $raw = [
            1 => 25,
            2 => 50,
            3 => 69,
        ];

        $n = (new TotalUnitNormalizer())->normalize($raw, ['type' => 'benefit']);
        $norm = (array) ($n['normalized'] ?? []);

        $this->assertEqualsWithDelta(17.3611, (float) $norm[1], 0.01);
        $this->assertEqualsWithDelta(100.0, array_sum(array_map('floatval', $norm)), 0.02);
    }

    public function test_total_unit_cost_inverts_so_bigger_raw_smaller_n(): void
    {
        $raw = [
            1 => 10,
            2 => 20,
            3 => 30,
        ];

        $n = (new TotalUnitNormalizer())->normalize($raw, ['type' => 'cost']);
        $norm = (array) ($n['normalized'] ?? []);

        $this->assertGreaterThan((float) $norm[2], (float) $norm[1]);
        $this->assertGreaterThan((float) $norm[3], (float) $norm[2]);
        $this->assertEqualsWithDelta(83.3333, (float) $norm[1], 0.01);
        $this->assertEqualsWithDelta(50.0, (float) $norm[3], 0.01);
    }

    public function test_max_unit_benefit_max_raw_is_100(): void
    {
        $raw = [
            1 => 120,
            2 => 139,
            3 => 157,
        ];

        $n = (new MaxUnitNormalizer())->normalize($raw, ['type' => 'benefit']);
        $norm = (array) ($n['normalized'] ?? []);

        $this->assertEqualsWithDelta(76.4331, (float) $norm[1], 0.01);
        $this->assertEqualsWithDelta(100.0, (float) $norm[3], 0.001);
    }

    public function test_max_unit_cost_raw_zero_is_100(): void
    {
        $raw = [
            1 => 0,
            2 => 10,
        ];

        $n = (new MaxUnitNormalizer())->normalize($raw, ['type' => 'cost']);
        $norm = (array) ($n['normalized'] ?? []);

        $this->assertEqualsWithDelta(100.0, (float) $norm[1], 0.001);
        $this->assertEqualsWithDelta(0.0, (float) $norm[2], 0.001);
    }

    public function test_average_unit_benefit_is_capped_at_100(): void
    {
        // avg = (200+100)/2 = 150
        $raw = [
            1 => 200,
            2 => 100,
        ];

        $n = (new AverageUnitNormalizer())->normalize($raw, ['type' => 'benefit']);
        $norm = (array) ($n['normalized'] ?? []);

        $this->assertEqualsWithDelta(100.0, (float) $norm[1], 0.001);
        $this->assertEqualsWithDelta(66.6667, (float) $norm[2], 0.02);
    }

    public function test_custom_target_benefit_is_capped_at_100(): void
    {
        $raw = [
            1 => 200,
        ];

        $n = (new CustomTargetNormalizer())->normalize($raw, ['type' => 'benefit', 'target' => 100]);
        $norm = (array) ($n['normalized'] ?? []);

        $this->assertEqualsWithDelta(100.0, (float) $norm[1], 0.001);
    }

    public function test_custom_target_cost_inverts_and_is_capped(): void
    {
        $raw = [
            1 => 0,
            2 => 50,
            3 => 200,
        ];

        $n = (new CustomTargetNormalizer())->normalize($raw, ['type' => 'cost', 'target' => 100]);
        $norm = (array) ($n['normalized'] ?? []);

        $this->assertEqualsWithDelta(100.0, (float) $norm[1], 0.001);
        $this->assertEqualsWithDelta(50.0, (float) $norm[2], 0.01);
        $this->assertEqualsWithDelta(0.0, (float) $norm[3], 0.001);
    }
}
