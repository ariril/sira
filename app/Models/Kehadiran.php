<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Kehadiran extends Model
{
    use HasFactory;

    protected $fillable = [
        'id_pegawai',
        'tanggal_hadir',
        'jam_masuk',
        'jam_keluar',
        'status_kehadiran',
        'catatan_lembur',
    ];

    public function pegawai()
    {
        return $this->belongsTo(Pegawai::class, 'id_pegawai', 'id_pegawai');
    }
}
