<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PenilaianKinerja extends Model
{
    use HasFactory;

    protected $fillable = [
        'id_pegawai',
        'id_periode_penilaian',
        'tanggal_penilaian',
        'skor_total_wsm',
        'status_validasi',
        'komentar_atasan',
    ];

    public function pegawai()
    {
        return $this->belongsTo(Pegawai::class, 'id_pegawai', 'id_pegawai');
    }

    public function periodePenilaian()
    {
        return $this->belongsTo(PeriodePenilaian::class, 'id_periode_penilaian', 'id_periode_penilaian');
    }

    public function detailPenilaianKriterias()
    {
        return $this->hasMany(DetailPenilaianKriteria::class, 'id_penilaian', 'id_penilaian');
    }

    public function remunerasi()
    {
        return $this->hasOne(Remunerasi::class, 'id_penilaian', 'id_penilaian'); // Jika ada relasi one-to-one
    }
}
