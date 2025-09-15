<?php

namespace App\Enums;

enum ContributionValidationStatus: string
{
    case PENDING  = 'Menunggu Persetujuan';
    case APPROVED = 'Disetujui';
    case REJECTED = 'Ditolak';
}
