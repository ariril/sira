<?php

namespace App\Enums;

enum RemunerationPaymentStatus: string
{
    case UNPAID   = 'Belum Dibayar';
    case PAID     = 'Dibayar';
    case WITHHELD = 'Ditahan';
}
