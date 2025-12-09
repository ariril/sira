<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Enums\ContributionValidationStatus;

class AdditionalContribution extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'title',
        'description',
        'submission_date',
        'evidence_file',
        'attachment_original_name',
        'attachment_mime',
        'attachment_size',
        'validation_status',
        'supervisor_comment',
        'assessment_period_id',
        'task_id',
        'submitted_at',
        'claimed_at',
        'reviewer_id',
        'score',
        'bonus_awarded',
    ];

    protected $casts = [
        'submission_date'   => 'date',
        'validation_status' => ContributionValidationStatus::class,
    ];

    /*
    |--------------------------------------------------------------------------
    | RELATIONSHIPS
    |--------------------------------------------------------------------------
    */
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function assessmentPeriod()
    {
        return $this->belongsTo(AssessmentPeriod::class);
    }

    public function task()
    {
        return $this->belongsTo(AdditionalTask::class, 'task_id');
    }
}
