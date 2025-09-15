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
    public const ROLE_PEGAWAI_MEDIS = 'pegawai_medis';
    public const ROLE_KEPALA_UNIT   = 'kepala_unit';
    public const ROLE_ADMINISTRASI  = 'administrasi';
    public const ROLE_SUPER_ADMIN   = 'super_admin';

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
        'id_number',
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

    public function doctorSchedules(): HasMany
    {
        return $this->hasMany(DoctorSchedule::class, 'doctor_id');
    }

    public function reviewDetails(): HasMany
    {
        return $this->hasMany(ReviewDetail::class, 'medical_staff_id');
    }

    public function patientQueuesAsDoctor(): HasMany
    {
        return $this->hasMany(PatientQueue::class, 'on_duty_doctor_id');
    }

    /*
    |--------------------------------------------------------------------------
    | Scopes & Helpers
    |--------------------------------------------------------------------------
    */
    public function scopeRole($q, string $role)
    {
        return $q->where('role', $role);
    }

    public function isKepalaUnit(): bool
    {
        return $this->role === self::ROLE_KEPALA_UNIT;
    }
}
