<?php

namespace App\Enums;

enum MedicalStaffReviewRole: string
{
    case DOKTER  = 'dokter';
    case PERAWAT = 'perawat';
    case LAINNYA = 'lainnya';
}
