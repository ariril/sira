<?php

namespace App\Enums;

enum UnitType: string
{
    case Manajemen   = 'manajemen';
    case AdminRs     = 'admin_rs';
    case Penunjang   = 'penunjang';
    case RawatInap   = 'rawat_inap';
    case IGD         = 'igd';
    case Poliklinik  = 'poliklinik';
    case Lainnya     = 'lainnya';

    public function label(): string
    {
        return match($this) {
            self::Manajemen  => 'Manajemen',
            self::AdminRs    => 'Admin RS',
            self::Penunjang  => 'Penunjang',
            self::RawatInap  => 'Rawat Inap',
            self::IGD        => 'IGD',
            self::Poliklinik => 'Poliklinik',
            self::Lainnya    => 'Lainnya',
        };
    }
}
