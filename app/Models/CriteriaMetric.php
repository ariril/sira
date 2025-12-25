<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CriteriaMetric extends Model
{
    use HasFactory;

    protected $table = 'imported_criteria_values';

    protected $fillable = [
        'import_batch_id',
        'user_id',
        'assessment_period_id',
        'performance_criteria_id',
        'value_numeric',
        'value_datetime',
        'value_text',
    ];

    protected $casts = [
        'value_numeric' => 'decimal:4',
        'value_datetime' => 'datetime',
    ];

    public function user() { return $this->belongsTo(User::class); }
    public function period() { return $this->belongsTo(AssessmentPeriod::class, 'assessment_period_id'); }
    public function criteria() { return $this->belongsTo(PerformanceCriteria::class, 'performance_criteria_id'); }
    public function importBatch() { return $this->belongsTo(MetricImportBatch::class, 'import_batch_id'); }
}
