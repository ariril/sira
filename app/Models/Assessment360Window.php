<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Assessment360Window extends Model
{
    use HasFactory;

    // Explicit table name to match migration (Laravel would infer 'assessment360_windows')
    protected $table = 'assessment_360_windows';

    protected $fillable = [
        'assessment_period_id',
        'start_date',
        'end_date',
        'is_active',
        'opened_by',
    ];

    protected $casts = [
        'start_date' => 'date',
        'end_date' => 'date',
        'is_active' => 'boolean',
    ];

    public function period() { return $this->belongsTo(AssessmentPeriod::class, 'assessment_period_id'); }
    public function opener() { return $this->belongsTo(User::class, 'opened_by'); }
}
