<?php

namespace App\Enums;

enum AttendanceStatus: string
{
    case HADIR     = 'Hadir';
    case LIBUR_UMUM = 'Libur Umum';
    case LIBUR_RUTIN = 'Libur Rutin';
    case SAKIT     = 'Sakit';
    case IZIN      = 'Izin';
    case CUTI      = 'Cuti';
    case TERLAMBAT = 'Terlambat';
    case ABSEN     = 'Absen';
}
