<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

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
        'last_role',
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

    public function remunerations(): HasMany
    {
        return $this->hasMany(Remuneration::class);
    }

    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(Role::class);
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
        return $q->whereHas('roles', function ($w) use ($role) {
            $w->where('slug', $role);
        });
    }

    public function scopeRoles($q, array $slugs)
    {
        return $q->whereHas('roles', function ($w) use ($slugs) {
            $w->whereIn('slug', $slugs);
        });
    }

    public function hasRole(string $slug): bool
    {
        return $this->roles->contains(fn($r) => $r->slug === $slug);
    }

    public function hasAnyRole(array $slugs): bool
    {
        foreach ($slugs as $s) {
            if ($this->hasRole($s)) return true;
        }
        return false;
    }

    public function hasAllRoles(array $slugs): bool
    {
        foreach ($slugs as $s) {
            if (!$this->hasRole($s)) return false;
        }
        return true;
    }

    public function listRoleSlugs(): array
    {
        return $this->roles->pluck('slug')->all();
    }

    public function getActiveRoleSlug(): ?string
    {
        $sessionRole = session('active_role');
        if ($sessionRole && $this->hasRole($sessionRole)) {
            return $sessionRole;
        }

        if ($this->last_role && $this->hasRole($this->last_role)) {
            return $this->last_role;
        }

        $priority = [
            self::ROLE_SUPER_ADMIN,
            self::ROLE_ADMINISTRASI,
            self::ROLE_KEPALA_POLIKLINIK,
            self::ROLE_KEPALA_UNIT,
            self::ROLE_PEGAWAI_MEDIS,
        ];
        foreach ($priority as $p) {
            if ($this->hasRole($p)) return $p;
        }
        return optional($this->roles->first())->slug;
    }

    // Backward compatibility: allow $user->role to read active role slug
    public function getRoleAttribute(): ?string
    {
        return $this->getActiveRoleSlug();
    }

    public function isPegawaiMedis(): bool { return $this->getActiveRoleSlug() === self::ROLE_PEGAWAI_MEDIS; }
    public function isAdministrasi(): bool { return $this->getActiveRoleSlug() === self::ROLE_ADMINISTRASI; }
    public function isKepalaUnit(): bool { return $this->getActiveRoleSlug() === self::ROLE_KEPALA_UNIT; }
    public function isKepalaPoliklinik(): bool { return $this->getActiveRoleSlug() === self::ROLE_KEPALA_POLIKLINIK; }
    public function isSuperAdmin(): bool { return $this->getActiveRoleSlug() === self::ROLE_SUPER_ADMIN; }

    public function getRoleLabelAttribute(): string
    {
        return match ($this->getActiveRoleSlug()) {
            self::ROLE_PEGAWAI_MEDIS     => 'Pegawai Medis',
            self::ROLE_KEPALA_UNIT       => 'Kepala Unit',
            self::ROLE_KEPALA_POLIKLINIK => 'Kepala Poliklinik',
            self::ROLE_ADMINISTRASI      => 'Admin RS',
            self::ROLE_SUPER_ADMIN       => 'Super Admin',
            default => ucfirst(str_replace('_',' ', (string)$this->getActiveRoleSlug())),
        };
    }

}
