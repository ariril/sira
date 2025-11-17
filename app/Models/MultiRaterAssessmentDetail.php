<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MultiRaterAssessmentDetail extends Model
{
    use HasFactory;

    protected $fillable = [
        'multi_rater_assessment_id','performance_criteria_id','score','comment'
    ];

    protected $casts = [
        'score' => 'decimal:2',
    ];

    public function header() { return $this->belongsTo(MultiRaterAssessment::class, 'multi_rater_assessment_id'); }
    public function criteria() { return $this->belongsTo(PerformanceCriteria::class, 'performance_criteria_id'); }
}
