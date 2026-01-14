<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Enums\AssessmentApprovalStatus;

class AssessmentApproval extends Model
{
    use HasFactory;

    protected $fillable = [
        'performance_assessment_id',
        'approver_id',
        'level',
        'attempt',
        'status',
        'note',
        'acted_at',
        'invalidated_at',
        'invalidated_by_id',
        'invalidated_reason',
    ];

    protected $casts = [
        'acted_at' => 'datetime',
        'invalidated_at' => 'datetime',
        'status'   => AssessmentApprovalStatus::class,
    ];

    /*
    |--------------------------------------------------------------------------
    | RELATIONSHIPS
    |--------------------------------------------------------------------------
    */
    public function performanceAssessment()
    {
        return $this->belongsTo(PerformanceAssessment::class);
    }

    public function approver()
    {
        return $this->belongsTo(User::class, 'approver_id');
    }
}
