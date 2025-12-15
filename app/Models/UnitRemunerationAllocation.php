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
