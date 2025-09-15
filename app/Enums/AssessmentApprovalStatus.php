<?php

namespace App\Enums;

enum AssessmentApprovalStatus: string
{
    case PENDING  = 'pending';
    case APPROVED = 'approved';
    case REJECTED = 'rejected';
}
