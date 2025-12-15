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
        'aggregation_method',
        'normalization_basis',
        'custom_target_value',
        'min_sample_size',
        'min_average_value',
        'raw_formula',
        'description',
        'is_active',
        'suggested_weight',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'type'      => PerformanceCriteriaType::class,
        'suggested_weight' => 'decimal:2',
        'custom_target_value' => 'decimal:2',
        'min_average_value' => 'decimal:2',
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

    public function raterWeights()
    {
        return $this->hasMany(RaterTypeWeight::class, 'performance_criteria_id');
    }
}
