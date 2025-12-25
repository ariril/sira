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
        'patient_name',
        'phone',
        'no_rm',
        'token',
        'status',
        'expires_at',
    ];

    protected $casts = [
        'expires_at' => 'datetime',
    ];

    public function staff(): HasMany
    {
        return $this->hasMany(ReviewInvitationStaff::class, 'invitation_id');
    }
}
