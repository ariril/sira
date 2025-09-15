<?php

namespace App\Enums;

enum PaymentStatus: string
{
    case BERHASIL = 'Berhasil';
    case PENDING  = 'Pending';
    case GAGAL    = 'Gagal';
}
