<?php

namespace App\Enums;

enum QueueStatus: string
{
    case MENUNGGU       = 'Menunggu';
    case SEDANG_DILAYANI = 'Sedang Dilayani';
    case SELESAI        = 'Selesai';
    case BATAL          = 'Batal';
}
