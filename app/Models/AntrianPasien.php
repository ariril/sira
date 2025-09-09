<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AntrianPasien extends Model
{
    use HasFactory;
    protected $table = 'antrian_pasien';
    protected $fillable = [
        'id_pasien',
        'tanggal_antri',
        'nomor_antrian',
        'status_antrian',
        'waktu_masuk_antrian',
        'waktu_mulai_dilayani',
        'waktu_selesai_dilayani',
        'id_dokter_bertugas',
    ];

    public function pasien()
    {
        return $this->belongsTo(Pasien::class, 'id_pasien', 'id_pasien');
    }

    public function dokterBertugas()
    {
        return $this->belongsTo(Pegawai::class, 'id_dokter_bertugas', 'id_pegawai');
    }
}
