<?php

namespace Tests\Unit;

use App\Support\AttachedRemunerationCalculator;
use App\Support\ProportionalAllocator;
use PHPUnit\Framework\TestCase;

class RemunerationDistributionStage15Test extends TestCase
{
    public function test_attached_uses_value_and_keeps_leftover_total_unit_consumes_allocation(): void
    {
        $allocation = 7_500_000.00;

        // Excel v5 - ATTACHED payout% must be based on VALUE (normalized 0..100).
        $wsmValue = [
            1 => 100.00,
            2 => 92.37,
            3 => 98.47,
        ];

        $attached = AttachedRemunerationCalculator::calculate($allocation, $wsmValue, 2);

        $this->assertSame(3, $attached['headcount']);
        $this->assertSame(2_500_000.00, $attached['remuneration_max_per_employee']);

        $this->assertSame(2_500_000.00, $attached['amounts'][1]);
        $this->assertSame(2_309_250.00, $attached['amounts'][2]);
        $this->assertSame(2_461_750.00, $attached['amounts'][3]);

        $this->assertSame(7_271_000.00, $attached['sum_amounts']);
        $this->assertSame(229_000.00, $attached['leftover_amount']);

        // TOTAL_UNIT uses RELATIVE weights and must exhaust allocation (no leftover).
        // For this minimal test, we reuse the same numeric weights to validate "sum == allocation".
        $weightsRelative = $wsmValue;
        $allocated = ProportionalAllocator::allocate($allocation, $weightsRelative);

        $this->assertCount(3, $allocated);
        $this->assertSame($allocation, array_sum($allocated));
    }
}
