<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CriteriaRaterRule extends Model
{
    use HasFactory;

    protected $fillable = [
        'performance_criteria_id',
        'assessor_type',
    ];

    public function performanceCriteria()
    {
        return $this->belongsTo(PerformanceCriteria::class);
    }
}
