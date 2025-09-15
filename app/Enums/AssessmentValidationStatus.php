<?php

namespace App\Enums;

enum AssessmentValidationStatus: string
{
    case PENDING   = 'Menunggu Validasi';
    case VALIDATED = 'Tervalidasi';
    case REJECTED  = 'Ditolak';
}
