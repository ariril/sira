<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Enums\PerformanceCriteriaType;

class PerformanceCriteria extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'type',
        'description',
        'is_active',
        'suggested_weight',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'type'      => PerformanceCriteriaType::class,
        'suggested_weight' => 'decimal:2',
    ];

    /*
    |--------------------------------------------------------------------------
    | RELATIONSHIPS
    |--------------------------------------------------------------------------
    */
    public function unitCriteriaWeights()
    {
        return $this->hasMany(UnitCriteriaWeight::class, 'performance_criteria_id');
    }

    public function assessmentDetails()
    {
        return $this->hasMany(PerformanceAssessmentDetail::class, 'performance_criteria_id');
    }
}
