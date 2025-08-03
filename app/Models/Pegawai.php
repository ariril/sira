<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Pegawai extends Model
{
    use HasFactory;

    protected $fillable = [
        'nip',
        'nama_pegawai',
        'tanggal_mulai_kerja',
        'jenis_kelamin',
        'kewarganegaraan',
        'nomor_identitas',
        'alamat',
        'nomor_telepon',
        'email',
        'pendidikan_terakhir',
        'jabatan',
        'unit_kerja', // Sesuaikan jika ini jadi FK ke tabel unit_kerja
        'password',
        'role',
    ];

    // Definisikan relasi di sini (contoh)
    public function penilaianKinerjas()
    {
        return $this->hasMany(PenilaianKinerja::class, 'id_pegawai', 'id_pegawai');
    }

    public function kehadirans()
    {
        return $this->hasMany(Kehadiran::class, 'id_pegawai', 'id_pegawai');
    }

    public function kontribusiTambahans()
    {
        return $this->hasMany(KontribusiTambahan::class, 'id_pegawai', 'id_pegawai');
    }

    public function remunerasis()
    {
        return $this->hasMany(Remunerasi::class, 'id_pegawai', 'id_pegawai');
    }

    public function jadwalDokters()
    {
        return $this->hasMany(JadwalDokter::class, 'id_pegawai', 'id_pegawai');
    }

    // Relasi untuk ulasan pasien jika id_pegawai_medis mengacu ke pegawai ini
    public function ulasanPasien()
    {
        return $this->hasMany(UlasanPasien::class, 'id_pegawai_medis', 'id_pegawai');
    }

    public function antrianPasien()
    {
        return $this->hasMany(AntrianPasien::class, 'id_dokter_bertugas', 'id_pegawai');
    }
}
