<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class UnitRemunerationAllocation extends Model
{
    use HasFactory;

    protected $table = 'unit_profession_remuneration_allocations';

    protected $fillable = [
        'assessment_period_id',
        'unit_id',
        'profession_id',
        'amount',
        'note',
        'published_at',
        'revised_by',
    ];

    protected $casts = [
        'assessment_period_id' => 'integer',
        'unit_id' => 'integer',
        'profession_id' => 'integer',
        'amount' => 'float',
        'published_at' => 'datetime',
        'revised_by' => 'integer',
    ];

    // Relasi
    public function period()
    {
        return $this->belongsTo(AssessmentPeriod::class, 'assessment_period_id');
    }

    public function unit()
    {
        return $this->belongsTo(Unit::class);
    }

    public function profession()
    {
        return $this->belongsTo(Profession::class);
    }

    public function reviser()
    {
        return $this->belongsTo(User::class, 'revised_by');
    }

}
