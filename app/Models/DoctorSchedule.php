<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DoctorSchedule extends Model
{
    use HasFactory;

    protected $fillable = [
        'doctor_id',
        'schedule_date',
        'start_time',
        'end_time',
        'unit_id',
    ];

    protected $casts = [
        'schedule_date' => 'date',
        'start_time'    => 'datetime:H:i',
        'end_time'      => 'datetime:H:i',
    ];

    /*
    |--------------------------------------------------------------------------
    | RELATIONSHIPS
    |--------------------------------------------------------------------------
    */
    public function doctor()
    {
        return $this->belongsTo(User::class, 'doctor_id');
    }

    public function unit()
    {
        return $this->belongsTo(Unit::class, 'unit_id');
    }
}
