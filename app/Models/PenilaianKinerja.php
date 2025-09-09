<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PenilaianKinerja extends Model
{
    use HasFactory;
    protected $table = 'penilaian_kinerja';

    protected $fillable = [
        'user_id',                // ganti dari id_pegawai → user_id
        'periode_penilaian_id',   // ganti dari id_periode_penilaian → periode_penilaian_id
        'tanggal_penilaian',
        'skor_total_wsm',
        'status_validasi',
        'komentar_atasan',
    ];

    /** Relasi ke user (pegawai yang dinilai) */
    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /** Relasi ke periode penilaian */
    public function periodePenilaian()
    {
        return $this->belongsTo(PeriodePenilaian::class, 'periode_penilaian_id');
    }

    /** Detail setiap kriteria penilaian */
    public function detailPenilaianKriterias()
    {
        return $this->hasMany(DetailPenilaianKriteria::class, 'penilaian_kinerja_id');
    }

    /** Relasi ke remunerasi hasil penilaian */
    public function remunerasi()
    {
        return $this->hasOne(Remunerasi::class, 'penilaian_kinerja_id');
    }
}
