<?php

namespace App\Enums;

enum AdditionalTaskClaimStatus: string
{
    case SUBMITTED = 'submitted';
    case APPROVED  = 'approved';
    case REJECTED  = 'rejected';

    public function isTerminal(): bool
    {
        return in_array($this, [self::APPROVED, self::REJECTED]);
    }
}
