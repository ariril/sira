<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PerformanceAssessmentSnapshot extends Model
{
    use HasFactory;

    protected $fillable = [
        'assessment_period_id',
        'user_id',
        'unit_id',
        'profession_id',
        'payload',
        'snapshotted_at',
    ];

    protected $casts = [
        'payload' => 'array',
        'snapshotted_at' => 'datetime',
    ];

    public function period()
    {
        return $this->belongsTo(AssessmentPeriod::class, 'assessment_period_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
