<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Enums\RemunerationPaymentStatus;

class Remuneration extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'assessment_period_id',
        'amount',
        'payment_date',
        'payment_status',
        'calculation_details',
        'published_at',
        'calculated_at',
        'revised_by',
    ];

    protected $casts = [
        'amount'              => 'decimal:2',
        'payment_date'        => 'date',
        'published_at'        => 'datetime',
        'calculated_at'       => 'datetime',
        'calculation_details' => 'array',
        'payment_status'      => RemunerationPaymentStatus::class,
    ];

    /*
    |--------------------------------------------------------------------------
    | RELATIONSHIPS
    |--------------------------------------------------------------------------
    */
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function assessmentPeriod()
    {
        return $this->belongsTo(AssessmentPeriod::class);
    }

    public function revisedBy()
    {
        return $this->belongsTo(User::class, 'revised_by');
    }
}
