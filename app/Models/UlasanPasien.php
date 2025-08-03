<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class UlasanPasien extends Model
{
    use HasFactory;

    protected $fillable = [
        'id_pasien',
        'id_pegawai_medis',
        'tanggal_ulasan',
        'rating_layanan',
        'komentar_saran_kritik',
        'tipe_feedback',
    ];

    public function pasien()
    {
        return $this->belongsTo(Pasien::class, 'id_pasien', 'id_pasien');
    }

    public function pegawaiMedis()
    {
        return $this->belongsTo(Pegawai::class, 'id_pegawai_medis', 'id_pegawai');
    }
}
