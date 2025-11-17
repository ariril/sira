<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CriteriaMetric extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'assessment_period_id',
        'performance_criteria_id',
        'value_numeric',
        'value_datetime',
        'value_text',
        'source_type',
        'source_table',
        'source_id',
    ];

    protected $casts = [
        'value_numeric' => 'decimal:4',
        'value_datetime' => 'datetime',
    ];

    public function user() { return $this->belongsTo(User::class); }
    public function period() { return $this->belongsTo(AssessmentPeriod::class, 'assessment_period_id'); }
    public function criteria() { return $this->belongsTo(PerformanceCriteria::class, 'performance_criteria_id'); }
}
