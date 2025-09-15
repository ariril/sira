<?php

namespace App\Enums;

enum MedicalStaffRole: string
{
    case DOKTER   = 'dokter';
    case PERAWAT  = 'perawat';
    case LAB      = 'lab';
    case FARMASI  = 'farmasi';
    case ADMIN    = 'admin';
    case LAINNYA  = 'lainnya';
}
