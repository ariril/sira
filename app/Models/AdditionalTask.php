<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class AdditionalTask extends Model
{
    use HasFactory;

    protected $table = 'additional_tasks';

    protected $fillable = [
        'unit_id',
        'assessment_period_id',
        'title',
        'description',
        'policy_doc_path',
        'start_date',
        'due_date',
        'bonus_amount',
        'points',
        'max_claims',
        'status',
        'created_by',
    ];

    // Relasi
    public function unit()
    {
        return $this->belongsTo(Unit::class);
    }

    public function period()
    {
        return $this->belongsTo(AssessmentPeriod::class, 'assessment_period_id');
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function contributions()
    {
        return $this->hasMany(AdditionalContribution::class, 'task_id');
    }
}
