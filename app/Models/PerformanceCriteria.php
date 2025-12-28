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
        'data_type',
        'input_method',
        'source',
        'is_360',
        'aggregation_method',
        'normalization_basis',
        'custom_target_value',
        'description',
        'is_active',
        'suggested_weight',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'is_360' => 'boolean',
        'type'      => PerformanceCriteriaType::class,
        'source' => 'string',
        'suggested_weight' => 'decimal:2',
        'custom_target_value' => 'decimal:2',
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

    public function metrics()
    {
        return $this->hasMany(CriteriaMetric::class, 'performance_criteria_id');
    }

}
