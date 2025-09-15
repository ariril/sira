<?php

namespace App\Enums;

enum AnnouncementCategory: string
{
    case REMUNERASI = 'remunerasi';
    case KINERJA    = 'kinerja';
    case PANDUAN    = 'panduan';
    case LAINNYA    = 'lainnya';
}
