<?php

namespace Tests\Unit;

use App\Support\AttachedRemunerationCalculator;
use App\Support\ProportionalAllocator;
use PHPUnit\Framework\TestCase;

class WsmRelativeVsValueTest extends TestCase
{
    public function test_relative_changes_when_new_top_peer_added_but_value_stays(): void
    {
        // Scenario: custom_target-like normalization where a user's normalized value is independent
        // of who else exists in the group (basis is fixed). Only RELATIVE depends on group max.
        $normalized = [
            1 => 80.0, // user A
            2 => 40.0, // user B
        ];

        // RELATIVE for benefit: relative = (normalized / max_normalized_in_scope) * 100.
        $max1 = max($normalized);
        $relative1 = [
            1 => ($normalized[1] / $max1) * 100.0, // 100
            2 => ($normalized[2] / $max1) * 100.0, // 50
        ];

        // Add a new user C with higher normalized, existing users' RELATIVE drops.
        $normalizedWithNew = $normalized + [3 => 100.0];
        $max2 = max($normalizedWithNew);
        $relative2 = [
            1 => ($normalizedWithNew[1] / $max2) * 100.0, // 80
            2 => ($normalizedWithNew[2] / $max2) * 100.0, // 40
            3 => ($normalizedWithNew[3] / $max2) * 100.0, // 100
        ];

        // VALUE payout% must stay equal to normalized (for single-criterion weighted average).
        $this->assertSame(80.0, $normalized[1]);
        $this->assertSame(80.0, $normalizedWithNew[1]);

        // RELATIVE changes for existing user 1 when a new max appears.
        $this->assertSame(100.0, round($relative1[1], 6));
        $this->assertSame(80.0, round($relative2[1], 6));

        // TOTAL_UNIT (proportional) uses RELATIVE: allocation shares should change.
        $alloc = 100.0;
        $totalUnitBefore = ProportionalAllocator::allocate($alloc, [1 => $relative1[1], 2 => $relative1[2]]);
        $totalUnitAfter = ProportionalAllocator::allocate($alloc, [1 => $relative2[1], 2 => $relative2[2], 3 => $relative2[3]]);
        $this->assertNotEquals($totalUnitBefore[1] ?? null, $totalUnitAfter[1] ?? null);

        // ATTACHED payout% uses VALUE: with allocation 300 and headcount 2, remunMax=150.
        // User 1 payout%=80 => amount=150*(80/100)=120.
        $attached = AttachedRemunerationCalculator::calculate(300.0, [1 => $normalized[1], 2 => $normalized[2]], 2);
        $this->assertSame(120.0, $attached['amounts'][1] ?? null);
    }
}
