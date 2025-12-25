<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ReviewInvitationStaff extends Model
{
    use HasFactory;

    protected $table = 'review_invitation_staff';

    protected $fillable = [
        'invitation_id',
        'user_id',
    ];

    public function invitation(): BelongsTo
    {
        return $this->belongsTo(ReviewInvitation::class, 'invitation_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
