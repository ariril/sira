<?php

namespace App\Enums;

enum PaymentChannel: string
{
    case VA       = 'VA';
    case QRIS     = 'QRIS';
    case TRANSFER = 'Transfer';
    case TUNAI    = 'Tunai';
}
