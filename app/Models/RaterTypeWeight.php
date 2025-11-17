<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class RaterTypeWeight extends Model
{
    use HasFactory;

    protected $fillable = [
        'performance_criteria_id','assessor_type','weight'
    ];

    protected $casts = [
        'weight' => 'decimal:2',
    ];

    public function criteria() { return $this->belongsTo(PerformanceCriteria::class, 'performance_criteria_id'); }
}
