<?php

namespace App\Enums;

enum ReviewStatus: string
{
    case PENDING  = 'pending';
    case APPROVED = 'approved';
    case REJECTED = 'rejected';

    public function label(): string
    {
        return match ($this) {
            self::PENDING  => 'Pending',
            self::APPROVED => 'Approved',
            self::REJECTED => 'Rejected',
        };
    }

    public function badgeClasses(): string
    {
        return match ($this) {
            self::PENDING  => 'bg-amber-100 text-amber-700',
            self::APPROVED => 'bg-emerald-100 text-emerald-700',
            self::REJECTED => 'bg-rose-100 text-rose-700',
        };
    }
}
