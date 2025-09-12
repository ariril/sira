<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PeriodePenilaian extends Model
{
    use HasFactory;
    protected $table = 'periode_penilaian';
    protected $casts = [
        'tanggal_mulai' => 'date',
        'tanggal_akhir' => 'date',
        'is_active' => 'boolean',
    ];
    protected $fillable = [
        'nama_periode',
        'tanggal_mulai',
        'tanggal_akhir',
        'siklus_penilaian',
        'status_periode',
    ];

    public function penilaianKinerjas()
    {
        return $this->hasMany(PenilaianKinerja::class, 'id_periode_penilaian', 'id_periode_penilaian');
    }

    public function remunerasis()
    {
        return $this->hasMany(Remunerasi::class, 'id_periode_penilaian', 'id_periode_penilaian');
    }
}
