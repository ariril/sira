<?php

namespace Tests\Unit;

use App\Support\AttachedRemunerationCalculator;
use PHPUnit\Framework\TestCase;

class AttachedRemunerationCalculatorTest extends TestCase
{
    public function test_attached_distribution_keeps_leftover(): void
    {
        $allocation = 7_500_000.00;
        $scores = [
            1 => 100.00,
            2 => 92.37,
            3 => 98.47,
        ];

        $out = AttachedRemunerationCalculator::calculate($allocation, $scores, 2);

        $this->assertSame(3, $out['headcount']);
        $this->assertSame(2_500_000.00, $out['remuneration_max_per_employee']);

        $this->assertSame(2_500_000.00, $out['amounts'][1]);
        $this->assertSame(2_309_250.00, $out['amounts'][2]);
        $this->assertSame(2_461_750.00, $out['amounts'][3]);

        $this->assertSame(7_271_000.00, $out['sum_amounts']);
        $this->assertSame(229_000.00, $out['leftover_amount']);
    }
}
