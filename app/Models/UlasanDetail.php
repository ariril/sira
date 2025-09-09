<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class UlasanDetail extends Model
{
    use HasFactory;

    protected $table = 'ulasan_items';

    protected $fillable = [
        'ulasan_id',
        'tenaga_medis_id',
        'peran',       // 'dokter' | 'perawat' | 'lainnya'
        'rating',      // 1..5
        'komentar',
    ];

    public function ulasan()
    {
        return $this->belongsTo(Ulasan::class);
    }

    public function tenagaMedis()
    {
        return $this->belongsTo(User::class, 'tenaga_medis_id');
    }
}
