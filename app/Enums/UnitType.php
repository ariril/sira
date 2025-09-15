<?php

namespace App\Enums;

enum UnitType: string
{
    case MANAJEMEN     = 'manajemen';
    case ADMINISTRASI  = 'administrasi';
    case PENUNJANG     = 'penunjang';
    case RAWAT_INAP    = 'rawat_inap';
    case IGD           = 'igd';
    case POLIKLINIK    = 'poliklinik';
    case LAINNYA       = 'lainnya';
}
