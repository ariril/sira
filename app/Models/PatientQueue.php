<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Enums\QueueStatus;

class PatientQueue extends Model
{
    use HasFactory;

    protected $fillable = [
        'queue_date',
        'queue_number',
        'queue_status',
        'queued_at',
        'service_started_at',
        'service_finished_at',
        'on_duty_doctor_id',
        'unit_id',
        'patient_ref',
        'visit_id',
    ];

    protected $casts = [
        'queue_date'          => 'date',
        'queued_at'           => 'datetime:H:i',
        'service_started_at'  => 'datetime:H:i',
        'service_finished_at' => 'datetime:H:i',
        'queue_status'        => QueueStatus::class,
    ];

    /*
    |--------------------------------------------------------------------------
    | RELATIONSHIPS
    |--------------------------------------------------------------------------
    */
    public function onDutyDoctor()
    {
        return $this->belongsTo(User::class, 'on_duty_doctor_id');
    }

    public function unit()
    {
        return $this->belongsTo(Unit::class);
    }

    public function visit()
    {
        return $this->belongsTo(Visit::class);
    }
}
