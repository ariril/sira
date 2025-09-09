<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Remunerasi extends Model
{
    use HasFactory;
    protected $table = 'remunerasi';
    protected $fillable = [
        'user_id',                 // was: id_pegawai
        'periode_penilaian_id',    // was: id_periode_penilaian
        'penilaian_kinerja_id',    // was: id_penilaian (relasi 1-1 ke penilaian)
        'nilai_remunerasi',
        'tanggal_pembayaran',
        'status_pembayaran',       // draft|diajukan|disetujui|dibayar|ditolak (misal)
        'rincian_perhitungan',     // JSON detail komponen WSM
    ];

    protected $casts = [
        'rincian_perhitungan' => 'array',
        'tanggal_pembayaran'  => 'date',
        'nilai_remunerasi'    => 'decimal:2',
    ];

    /** Pegawai (user) penerima remunerasi */
    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /** Periode penilaian yang menjadi dasar remunerasi */
    public function periodePenilaian()
    {
        return $this->belongsTo(PeriodePenilaian::class, 'periode_penilaian_id');
    }

    /** Penilaian kinerja (one-to-one dari penilaian ke remunerasi) */
    public function penilaianKinerja()
    {
        return $this->belongsTo(PenilaianKinerja::class, 'penilaian_kinerja_id');
    }
}
