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
    ];

    protected $casts = [
        'weight'      => 'decimal:2',
        'status'      => UnitCriteriaWeightStatus::class,
        'policy_note' => 'string',
        'unit_head_note' => 'string',
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
