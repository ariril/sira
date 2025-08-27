<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class KontribusiTambahan extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',                // ganti dari id_pegawai → user_id
        'judul_kontribusi',
        'deskripsi_kontribusi',
        'tanggal_pengajuan',
        'file_bukti',
        'status_validasi',
        'komentar_supervisor',
        'atasan_supervisor_id',   // opsional: kalau ada supervisor yang validasi
    ];

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    // Jika ada supervisor (optional)
    public function supervisor()
    {
        return $this->belongsTo(User::class, 'atasan_supervisor_id');
    }
}
