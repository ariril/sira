<?php

namespace App\Models;

use App\Enums\RaterWeightStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class RaterWeight extends Model
{
    use HasFactory;

    protected $fillable = [
        'assessment_period_id',
        'assessee_profession_id',
        'assessor_type',
        'weight',
        'status',
        'proposed_by',
        'decided_by',
        'decided_at',
    ];

    protected $casts = [
        'weight' => 'decimal:2',
        'status' => RaterWeightStatus::class,
        'decided_at' => 'datetime',
    ];

    public function period()
    {
        return $this->belongsTo(AssessmentPeriod::class, 'assessment_period_id');
    }

    public function assesseeProfession()
    {
        return $this->belongsTo(Profession::class, 'assessee_profession_id');
    }

    public function proposedBy()
    {
        return $this->belongsTo(User::class, 'proposed_by');
    }

    public function decidedBy()
    {
        return $this->belongsTo(User::class, 'decided_by');
    }
}
