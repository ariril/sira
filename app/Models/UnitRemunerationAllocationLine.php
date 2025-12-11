<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class UnitRemunerationAllocationLine extends Model
{
    use HasFactory;

    protected $fillable = [
        'unit_remuneration_allocation_id',
        'profession_id',
        'amount',
        'percent',
    ];

    public function allocation()
    {
        return $this->belongsTo(UnitRemunerationAllocation::class, 'unit_remuneration_allocation_id');
    }

    public function profession()
    {
        return $this->belongsTo(Profession::class);
    }
}
