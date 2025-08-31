<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
// use Laravel\Sanctum\HasApiTokens; // aktifkan kalau pakai Sanctum
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class User extends Authenticatable
{
    use HasFactory, Notifiable; // , HasApiTokens;

    /** Role constants (opsional, biar rapi) */
    public const ROLE_PEGAWAI_MEDIS = 'pegawai_medis';
    public const ROLE_KEPALA_UNIT = 'kepala_unit';
    public const ROLE_ADMINISTRASI = 'administrasi';
    public const ROLE_SUPER_ADMIN = 'super_admin';

    /** Mass assignable fields */
    protected $fillable = [
        'nama', 'email', 'password',
        'nip', 'tanggal_mulai_kerja', 'jenis_kelamin', 'kewarganegaraan',
        'nomor_identitas', 'alamat', 'nomor_telepon', 'pendidikan_terakhir',
        'jabatan', 'unit_kerja_id', 'role', 'profesi_id',
    ];

    /** Hidden attrs */
    protected $hidden = ['password', 'remember_token'];

    /** Casts */
    protected function casts(): array
    {
        return [
            'email_verified_at'  => 'datetime',
            'tanggal_mulai_kerja'=> 'date',
            'password'           => 'hashed',
        ];
    }

    /* =======================
       Relasi
       ======================= */
    public function profesi(): BelongsTo
    {
        return $this->belongsTo(Profesi::class);
    }

    public function unitKerja(): BelongsTo
    {
        return $this->belongsTo(UnitKerja::class);
    }

    public function penilaianKinerjas(): HasMany
    {
        return $this->hasMany(PenilaianKinerja::class, 'user_id');
    }

    public function kehadirans(): HasMany
    {
        return $this->hasMany(Kehadiran::class, 'user_id');
    }

    public function kontribusiTambahans(): HasMany
    {
        return $this->hasMany(KontribusiTambahan::class, 'user_id');
    }

    public function remunerasis(): HasMany
    {
        return $this->hasMany(Remunerasi::class, 'user_id');
    }

    public function jadwalDokters(): HasMany
    {
        return $this->hasMany(JadwalDokter::class, 'user_id');
    }

    /** Ulasan pasien yang (opsional) terkait user/tenaga medis ini */
    public function ulasanPasiens(): HasMany
    {
        return $this->hasMany(UlasanPasien::class, 'user_id');
    }

    /** Antrian di mana user ini menjadi dokter bertugas (jika ada) */
    public function antrianSebagaiDokter(): HasMany
    {
        return $this->hasMany(AntrianPasien::class, 'dokter_bertugas_user_id');
    }

    /* =======================
       Scope
       ======================= */
    public function scopeRole($q, string $role)
    {
        return $q->where('role', $role);
    }

    public function scopeProfesi($q, string $kode)
    {
        return $q->whereHas('profesi', fn($p) => $p->where('kode', $kode));
    }

    public function isKepalaUnit(): bool
    {
        return $this->role === self::ROLE_KEPALA_UNIT;
    }
}
