<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class UnitKerja extends Model
{
    use HasFactory;
    protected $table = 'unit_kerja';

    protected $fillable = [
        'nama_unit',
        'proporsi_remunerasi_unit',
    ];

    public function bobotKriteriaUnits()
    {
        return $this->hasMany(BobotKriteriaUnit::class, 'id_unit', 'id_unit');
    }

    // Jika Anda memutuskan untuk menjadikan unit_kerja di Pegawai sebagai FK:
    // public function pegawais()
    // {
    //     return $this->hasMany(Pegawai::class, 'id_unit', 'id_unit');
    // }
}
