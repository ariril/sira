<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Enums\MedicalStaffRole;

class MedicalStaffVisit extends Model
{
    use HasFactory;

    protected $table = 'visit_medical_staff';

    protected $fillable = [
        'visit_id',
        'medical_staff_id',
        'role',
        'duration_minutes',
    ];

    protected $casts = [
        'role' => MedicalStaffRole::class,
    ];

    /*
    |--------------------------------------------------------------------------
    | RELATIONSHIPS
    |--------------------------------------------------------------------------
    */
    public function visit()
    {
        return $this->belongsTo(Visit::class);
    }

    public function medicalStaff()
    {
        return $this->belongsTo(User::class, 'medical_staff_id');
    }
}
