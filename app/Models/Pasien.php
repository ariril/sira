<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Pasien extends Model
{
    use HasFactory;

    protected $fillable = [
        'nama_pasien',
        'nomor_rekam_medis',
        'tanggal_kunjungan_terakhir',
    ];

    public function ulasanPasiens()
    {
        return $this->hasMany(UlasanPasien::class, 'id_pasien', 'id_pasien');
    }

    public function antrianPasiens()
    {
        return $this->hasMany(AntrianPasien::class, 'id_pasien', 'id_pasien');
    }

    public function transaksiPembayarans()
    {
        return $this->hasMany(TransaksiPembayaran::class, 'id_pasien', 'id_pasien');
    }
}
