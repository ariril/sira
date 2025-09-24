<?php

namespace App\Enums;

enum UnitCriteriaWeightStatus: string
{
    case DRAFT   = 'draft';
    case PENDING = 'pending';
    case ACTIVE  = 'active';
    case REJECTED = 'rejected';
}
