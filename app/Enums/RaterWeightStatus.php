<?php

namespace App\Enums;

enum RaterWeightStatus: string
{
    case DRAFT = 'draft';
    case PENDING = 'pending';
    case ACTIVE = 'active';
    case REJECTED = 'rejected';
    case ARCHIVED = 'archived';
}
