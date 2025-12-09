<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use App\Models\PerformanceCriteria;

class MultiRaterScore extends Model
{
    use HasFactory;

    protected $fillable = [
        'period_id',
        'rater_user_id',
        'target_user_id',
        'performance_criteria_id',
        'score',
    ];

    public function target()
    {
        return $this->belongsTo(User::class, 'target_user_id');
    }

    public function criteria()
    {
        return $this->belongsTo(PerformanceCriteria::class, 'performance_criteria_id');
    }
}
