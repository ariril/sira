<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ReviewInvitation extends Model
{
    use HasFactory;

    protected $fillable = [
        'assessment_period_id',
        'registration_ref',
        'unit_id',
        'patient_name',
        'contact',
        'token_hash',
        'status',
        'expires_at',
        'sent_at',
        'opened_at',
        'used_at',
        'client_ip',
        'user_agent',
    ];

    protected $casts = [
        'expires_at' => 'datetime',
        'sent_at' => 'datetime',
        'opened_at' => 'datetime',
        'used_at' => 'datetime',
    ];

    public function staff(): HasMany
    {
        return $this->hasMany(ReviewInvitationStaff::class, 'invitation_id');
    }

    public function unit(): BelongsTo
    {
        return $this->belongsTo(Unit::class);
    }
}
