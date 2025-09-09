<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class KriteriaKinerja extends Model
{
    use HasFactory;
    protected $table = 'kriteria_kinerja';

    protected $fillable = [
        'nama_kriteria',
        'tipe_kriteria',
        'deskripsi_kriteria',
        'aktif',
    ];

    public function bobotKriteriaUnits()
    {
        return $this->hasMany(BobotKriteriaUnit::class, 'id_kriteria', 'id_kriteria');
    }

    public function detailPenilaianKriterias()
    {
        return $this->hasMany(DetailPenilaianKriteria::class, 'id_kriteria', 'id_kriteria');
    }
}
