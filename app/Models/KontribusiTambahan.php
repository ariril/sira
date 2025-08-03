<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class KontribusiTambahan extends Model
{
    use HasFactory;

    protected $fillable = [
        'id_pegawai',
        'judul_kontribusi',
        'deskripsi_kontribusi',
        'tanggal_pengajuan',
        'file_bukti',
        'status_validasi',
        'komentar_supervisor',
    ];

    public function pegawai()
    {
        return $this->belongsTo(Pegawai::class, 'id_pegawai', 'id_pegawai');
    }
}
