<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Visit extends Model
{
    use HasFactory;

    protected $fillable = [
        'ticket_code',
        'unit_id',
        'visit_date',
    ];

    protected $casts = [
        'visit_date' => 'datetime',
    ];

    /*
    |--------------------------------------------------------------------------
    | RELATIONSHIPS
    |--------------------------------------------------------------------------
    */
    public function unit()
    {
        return $this->belongsTo(Unit::class);
    }

    public function review()
    {
        return $this->hasOne(Review::class);
    }

    public function patientQueues()
    {
        return $this->hasMany(PatientQueue::class);
    }

    public function medicalStaff()
    {
        return $this->hasMany(MedicalStaffVisit::class);
    }
}
