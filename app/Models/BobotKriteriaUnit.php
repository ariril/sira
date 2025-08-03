<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class BobotKriteriaUnit extends Model
{
    use HasFactory;

    protected $fillable = [
        'id_unit',
        'id_kriteria',
        'bobot',
    ];

    public function unitKerja()
    {
        return $this->belongsTo(UnitKerja::class, 'id_unit', 'id_unit');
    }

    public function kriteriaKinerja()
    {
        return $this->belongsTo(KriteriaKinerja::class, 'id_kriteria', 'id_kriteria');
    }
}
