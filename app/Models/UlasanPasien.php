<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class UlasanPasien extends Model
{
    use HasFactory;

    protected $fillable = [
        'pasien_id',            // was: id_pasien
        'user_id',              // was: id_pegawai_medis  (tenaga medis yang dilayani)
        'tanggal_ulasan',
        'rating_layanan',
        'komentar_saran_kritik',
        'tipe_feedback',        // mis: 'kepuasan','keluhan','saran'
    ];

    protected $casts = [
        'tanggal_ulasan'    => 'date',
        'rating_layanan'    => 'integer',
    ];

    /** Pasien yang memberi ulasan */
    public function pasien()
    {
        return $this->belongsTo(Pasien::class, 'pasien_id');
    }

    /** Tenaga medis (user) yang dilayani/dinilai */
    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    // Jika kamu ingin nama method yang lebih spesifik:
    // public function tenagaMedis()
    // {
    //     return $this->belongsTo(User::class, 'user_id');
    // }
}
