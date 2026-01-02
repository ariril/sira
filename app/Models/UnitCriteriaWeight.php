<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Enums\UnitCriteriaWeightStatus;

class UnitCriteriaWeight extends Model
{
    use HasFactory;

    protected $fillable = [
        'unit_id',
        'performance_criteria_id',
        'weight',
        'assessment_period_id',
        'status',
        'policy_doc_path',
        'policy_note',
        'proposed_by',
        'proposed_note',
        'decided_by',
        'decided_at',
        'decided_note',
    ];

    protected $casts = [
        'weight'      => 'decimal:2',
        'status'      => UnitCriteriaWeightStatus::class,
        'policy_note' => 'string',
        'proposed_note' => 'string',
        'decided_at' => 'datetime',
        'decided_note' => 'string',
    ];

    /*
    |--------------------------------------------------------------------------
    | RELATIONSHIPS
    |--------------------------------------------------------------------------
    */
    public function unit()
    {
        return $this->belongsTo(Unit::class);
    }

    public function performanceCriteria()
    {
        return $this->belongsTo(PerformanceCriteria::class);
    }
}
