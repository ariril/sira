<?php

namespace App\Enums;

enum AttendanceStatus: string
{
    case HADIR     = 'Hadir';
    case SAKIT     = 'Sakit';
    case IZIN      = 'Izin';
    case CUTI      = 'Cuti';
    case TERLAMBAT = 'Terlambat';
    case ABSEN     = 'Absen';
}
