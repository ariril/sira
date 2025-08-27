<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Kehadiran extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'tanggal_hadir',
        'jam_masuk',
        'jam_keluar',
        'status_kehadiran',
        'catatan_lembur',
    ];

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
