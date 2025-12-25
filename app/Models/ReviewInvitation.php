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
        'review_id',
        'token_hash',
        'status',
        'expires_at',
        'used_at',
        'sent_via',
        'sent_at',
    ];

    protected $casts = [
        'expires_at' => 'datetime',
        'used_at' => 'datetime',
        'sent_at' => 'datetime',
    ];

    public function review(): BelongsTo
    {
        return $this->belongsTo(Review::class);
    }

    public function staff(): HasMany
    {
        return $this->hasMany(ReviewInvitationStaff::class, 'invitation_id');
    }
}
