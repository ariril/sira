<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Ulasan extends Model
{
    use HasFactory;

    protected $fillable = [
        'kunjungan_id',
        'overall_rating',
        'komentar',
        'nama_pasien',
        'kontak',
        'client_ip',
        'user_agent',
    ];

    public function kunjungan()
    {
        return $this->belongsTo(Kunjungan::class);
    }

    public function items()
    {
        return $this->hasMany(UlasanItem::class);
    }
}
