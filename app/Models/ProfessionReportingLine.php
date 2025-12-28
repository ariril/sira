<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProfessionReportingLine extends Model
{
    use HasFactory;

    protected $fillable = [
        'assessee_profession_id',
        'assessor_profession_id',
        'relation_type',
        'level',
        'is_required',
        'is_active',
    ];

    protected $casts = [
        'level' => 'integer',
        'is_required' => 'boolean',
        'is_active' => 'boolean',
    ];

    public function assesseeProfession(): BelongsTo
    {
        return $this->belongsTo(Profession::class, 'assessee_profession_id');
    }

    public function assessorProfession(): BelongsTo
    {
        return $this->belongsTo(Profession::class, 'assessor_profession_id');
    }
}
