<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class JadwalDokter extends Model
{
    use HasFactory;
    protected $table = 'jadwal_dokter';
    protected $fillable = [
        'dokter_id',
        'tanggal',
        'jam_mulai',
        'jam_selesai',
        'lokasi_poliklinik',
    ];

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
