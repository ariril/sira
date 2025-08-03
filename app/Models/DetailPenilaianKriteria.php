<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DetailPenilaianKriteria extends Model
{
    use HasFactory;

    protected $fillable = [
        'id_penilaian',
        'id_kriteria',
        'nilai',
    ];

    public function penilaianKinerja()
    {
        return $this->belongsTo(PenilaianKinerja::class, 'id_penilaian', 'id_penilaian');
    }

    public function kriteriaKinerja()
    {
        return $this->belongsTo(KriteriaKinerja::class, 'id_kriteria', 'id_kriteria');
    }
}
