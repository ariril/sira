<?php

namespace App\Enums;

enum LogbookStatus: string
{
    case DRAFT     = 'draf';
    case SUBMITTED = 'diajukan';
    case APPROVED  = 'disetujui';
    case REJECTED  = 'ditolak';
}
