<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Remunerasi extends Model
{
    use HasFactory;

    protected $fillable = [
        'id_pegawai',
        'id_periode_penilaian',
        'nilai_remunerasi',
        'tanggal_pembayaran',
        'status_pembayaran',
        'rincian_perhitungan',
    ];

    protected $casts = [
        'rincian_perhitungan' => 'array', // Casting JSON column to array
    ];

    public function pegawai()
    {
        return $this->belongsTo(Pegawai::class, 'id_pegawai', 'id_pegawai');
    }

    public function periodePenilaian()
    {
        return $this->belongsTo(PeriodePenilaian::class, 'id_periode_penilaian', 'id_periode_penilaian');
    }

    // Jika ada relasi one-to-one dari penilaian_kinerja ke remunerasi:
     public function penilaianKinerja()
     {
         return $this->belongsTo(PenilaianKinerja::class, 'id_penilaian', 'id_penilaian');
     }
}
