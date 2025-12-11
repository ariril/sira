<?php

namespace App\Models;

use App\Enums\ReviewStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Review extends Model
{
    use HasFactory;

    protected $fillable = [
        'registration_ref',
        'unit_id',
        'overall_rating',
        'comment',
        'patient_name',
        'contact',
        'client_ip',
        'user_agent',
        'status',
        'decision_note',
        'decided_by',
        'decided_at',
    ];

    protected $casts = [
        'status'     => ReviewStatus::class,
        'decided_at' => 'datetime',
    ];

    /*
    |--------------------------------------------------------------------------
    | RELATIONSHIPS
    |--------------------------------------------------------------------------
    */
    public function visit(): BelongsTo
    {
        return $this->belongsTo(Visit::class);
    }

    public function details(): HasMany
    {
        return $this->hasMany(ReviewDetail::class);
    }

    public function unit(): BelongsTo
    {
        return $this->belongsTo(Unit::class);
    }

    public function decidedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'decided_by');
    }

    public function getAverageRatingAttribute(): ?float
    {
        $average = $this->relationLoaded('details')
            ? $this->details->avg('rating')
            : $this->details()->avg('rating');

        if ($average === null) {
            return $this->overall_rating !== null ? (float) $this->overall_rating : null;
        }

        return round((float) $average, 1);
    }
}
