<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Enums\UnitType;

class Unit extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'slug',
        'code',
        'type',
        'parent_id',
        'location',
        'phone',
        'email',
        'remuneration_ratio',
        'is_active',
    ];

    protected $casts = [
        'is_active'          => 'boolean',
        'remuneration_ratio' => 'decimal:2',
        'type'               => UnitType::class,
    ];

    /*
    |--------------------------------------------------------------------------
    | RELATIONSHIPS
    |--------------------------------------------------------------------------
    */
    public function parent()
    {
        return $this->belongsTo(Unit::class, 'parent_id');
    }

    public function children()
    {
        return $this->hasMany(Unit::class, 'parent_id');
    }

    public function users()
    {
        return $this->hasMany(User::class);
    }

    public function unitCriteriaWeights()
    {
        return $this->hasMany(UnitCriteriaWeight::class);
    }

    public function doctorSchedules()
    {
        return $this->hasMany(DoctorSchedule::class);
    }

    public function patientQueues()
    {
        return $this->hasMany(PatientQueue::class);
    }

    public function visits()
    {
        return $this->hasMany(Visit::class);
    }

    public function head() { return $this->belongsTo(\App\Models\User::class, 'head_user_id'); }

    public function getTypeKeyAttribute(): ?string
    {
        $t = $this->type;
        return $t instanceof \BackedEnum ? $t->value : $t; // string|null
    }
}
