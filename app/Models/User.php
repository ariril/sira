<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class User extends Authenticatable
{
    use HasFactory, Notifiable;

    /*
    |--------------------------------------------------------------------------
    | Role constants
    |--------------------------------------------------------------------------
    */
    public const ROLE_PEGAWAI_MEDIS     = 'pegawai_medis';
    public const ROLE_KEPALA_UNIT       = 'kepala_unit';
    public const ROLE_KEPALA_POLIKLINIK = 'kepala_poliklinik';
    public const ROLE_ADMINISTRASI      = 'admin_rs';
    public const ROLE_SUPER_ADMIN       = 'super_admin';

    /*
    |--------------------------------------------------------------------------
    | Fillable
    |--------------------------------------------------------------------------
    */
    protected $fillable = [
        'employee_number',
        'name',
        'start_date',
        'gender',
        'nationality',
        'address',
        'phone',
        'email',
        'last_education',
        'position',
        'unit_id',
        'profession_id',
        'password',
        'role',
    ];
    // NOTE: kolom 'id_number' tidak ada di dump -> dihapus dari $fillable agar tidak error insert

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected $casts = [
        'start_date'        => 'date',
        'email_verified_at' => 'datetime',
        'password'          => 'hashed',
    ];

    /*
    |--------------------------------------------------------------------------
    | Relationships
    |--------------------------------------------------------------------------
    */
    public function profession(): BelongsTo
    {
        return $this->belongsTo(Profession::class);
    }

    public function unit(): BelongsTo
    {
        return $this->belongsTo(Unit::class);
    }

    public function performanceAssessments(): HasMany
    {
        return $this->hasMany(PerformanceAssessment::class);
    }

    public function attendances(): HasMany
    {
        return $this->hasMany(Attendance::class);
    }

    public function additionalContributions(): HasMany
    {
        return $this->hasMany(AdditionalContribution::class);
    }

    public function remunerations(): HasMany
    {
        return $this->hasMany(Remuneration::class);
    }

    // Relasi approval (sebagai approver) — berguna untuk dashboard admin_rs/kepala*
    public function assessmentApprovals(): HasMany
    {
        return $this->hasMany(AssessmentApproval::class, 'approver_id');
    }

    // RELASI YANG DIHAPUS karena tabel/arah datanya sudah tidak ada di scope TA:
    // - doctorSchedules()         -> DoctorSchedule dihapus
    // - reviewDetails()           -> review_details sekarang per-profession, bukan per-user
    // - patientQueuesAsDoctor()   -> patient_queues dihapus

    /*
    |--------------------------------------------------------------------------
    | Scopes & Helpers
    |--------------------------------------------------------------------------
    */
    public function scopeRole($q, string $role)
    {
        return $q->where('role', $role);
    }

    public function isPegawaiMedis(): bool { return $this->role === self::ROLE_PEGAWAI_MEDIS; }
    public function isAdministrasi(): bool { return $this->role === self::ROLE_ADMINISTRASI; } // sudah ada
    public function isKepalaUnit(): bool { return $this->role === self::ROLE_KEPALA_UNIT; }   // sudah ada
    public function isKepalaPoliklinik(): bool { return $this->role === self::ROLE_KEPALA_POLIKLINIK; } // sudah ada
    public function isSuperAdmin(): bool { return $this->role === self::ROLE_SUPER_ADMIN; }   // sudah ada

    public function getRoleLabelAttribute(): string
    {
        return match ($this->role) {
            self::ROLE_PEGAWAI_MEDIS     => 'Pegawai Medis',
            self::ROLE_KEPALA_UNIT       => 'Kepala Unit',
            self::ROLE_KEPALA_POLIKLINIK => 'Kepala Poliklinik',
            self::ROLE_ADMINISTRASI      => 'Admin RS',
            self::ROLE_SUPER_ADMIN       => 'Super Admin',
            default => ucfirst(str_replace('_',' ', (string)$this->role)),
        };
    }

}
