<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AssessmentPeriod extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'start_date',
        'end_date',
        'cycle',
        'status',
        'is_active',
        'locked_at',
    ];

    protected $casts = [
        'start_date' => 'date',
        'end_date'   => 'date',
        'is_active'  => 'boolean',
        'locked_at'  => 'datetime',
    ];

    /*
    |--------------------------------------------------------------------------
    | RELATIONSHIPS
    |--------------------------------------------------------------------------
    */
    public function performanceAssessments()
    {
        return $this->hasMany(PerformanceAssessment::class, 'assessment_period_id');
    }

    public function remunerations()
    {
        return $this->hasMany(Remuneration::class, 'assessment_period_id');
    }

    public function additionalContributions()
    {
        return $this->hasMany(AdditionalContribution::class, 'assessment_period_id');
    }
}
