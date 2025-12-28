<?php

namespace App\Models;

use App\Enums\RaterWeightStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class RaterWeight extends Model
{
    use HasFactory;

    protected $table = 'unit_rater_weights';

    protected $fillable = [
        'assessment_period_id',
        'unit_id',
        'performance_criteria_id',
        'assessee_profession_id',
        'assessor_type',
        'assessor_profession_id',
        'assessor_level',
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
        'assessor_level' => 'integer',
    ];

    public function period()
    {
        return $this->belongsTo(AssessmentPeriod::class, 'assessment_period_id');
    }

    public function unit()
    {
        return $this->belongsTo(Unit::class, 'unit_id');
    }

    public function criteria()
    {
        return $this->belongsTo(PerformanceCriteria::class, 'performance_criteria_id');
    }

    public function assesseeProfession()
    {
        return $this->belongsTo(Profession::class, 'assessee_profession_id');
    }

    public function assessorProfession()
    {
        return $this->belongsTo(Profession::class, 'assessor_profession_id');
    }

    public function getAssessorLabelAttribute(): string
    {
        $type = (string) ($this->assessor_type ?? '');
        $professionName = (string) ($this->assessorProfession?->name ?? '');
        $level = $this->assessor_level;

        return match ($type) {
            'self' => 'Diri sendiri',
            'supervisor' => ($level ? ('Atasan L' . $level) : 'Atasan') . ($professionName ? (' - ' . $professionName) : ''),
            'peer' => 'Rekan' . ($professionName ? (' - ' . $professionName) : ''),
            'subordinate' => 'Bawahan' . ($professionName ? (' - ' . $professionName) : ''),
            default => $type ?: '-',
        };
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
