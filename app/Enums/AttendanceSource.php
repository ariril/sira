<?php

namespace App\Enums;

enum AttendanceSource: string
{
    case MANUAL    = 'manual';
    case IMPORT    = 'import';
    case INTEGRASI = 'integrasi';
}
