<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use App\Enums\PerformanceCriteriaType;

class UnitPerformanceCriteria extends Model
{
    use HasFactory;

    protected $table = 'unit_performance_criterias';

    protected $fillable = [
        'unit_id',
        'performance_criteria_id',
        'name',
        'type',
        'description',
        'is_active',
    ];

    protected $casts = [
        'type'      => PerformanceCriteriaType::class,
        'is_active' => 'boolean',
    ];

    // Relations
    public function unit()
    {
        return $this->belongsTo(Unit::class);
    }

    public function globalCriteria()
    {
        return $this->belongsTo(PerformanceCriteria::class, 'performance_criteria_id');
    }
}
