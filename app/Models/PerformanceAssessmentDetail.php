<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PerformanceAssessmentDetail extends Model
{
    use HasFactory;

    protected $fillable = [
        'performance_assessment_id',
        'performance_criteria_id',
        'score',
    ];

    protected $casts = [
        'score' => 'decimal:2',
    ];

    /*
    |--------------------------------------------------------------------------
    | RELATIONSHIPS
    |--------------------------------------------------------------------------
    */
    public function performanceAssessment()
    {
        return $this->belongsTo(PerformanceAssessment::class, 'performance_assessment_id');
    }

    public function performanceCriteria()
    {
        return $this->belongsTo(PerformanceCriteria::class, 'performance_criteria_id');
    }
}
