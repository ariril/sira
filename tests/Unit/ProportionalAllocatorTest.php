<?php

namespace Tests\Unit;

use App\Support\ProportionalAllocator;
use PHPUnit\Framework\TestCase;

class ProportionalAllocatorTest extends TestCase
{
    public function testAllocateDoesNotExceedTotalAndSumsExactly(): void
    {
        $total = 1000.00;
        $weights = [
            1 => 77.78,
            2 => 77.77,
            3 => 44.45,
        ];

        $alloc = ProportionalAllocator::allocate($total, $weights);

        $this->assertCount(3, $alloc);
        $this->assertSame(1000.00, round(array_sum($alloc), 2));

        foreach ($alloc as $amt) {
            $this->assertGreaterThanOrEqual(0.0, $amt);
            $this->assertSame($amt, round($amt, 2));
        }
    }

    public function testAllocateAllZeroWeightsDistributesEquallyAndSumsExactly(): void
    {
        $total = 10.00;
        $weights = [
            'a' => 0,
            'b' => 0,
            'c' => 0,
        ];

        $alloc = ProportionalAllocator::allocate($total, $weights);

        $this->assertSame(10.00, round(array_sum($alloc), 2));
        $this->assertSame(3.34, $alloc['a']);
        $this->assertSame(3.33, $alloc['b']);
        $this->assertSame(3.33, $alloc['c']);
    }
}
