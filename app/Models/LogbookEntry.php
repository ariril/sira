<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Enums\LogbookStatus;

class LogbookEntry extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'entry_date',
        'start_time',
        'end_time',
        'duration_minutes',
        'activity',
        'category',
        'status',
        'approver_id',
        'approved_at',
        'attachments',
    ];

    protected $casts = [
        'entry_date'       => 'date',
        'start_time'       => 'datetime:H:i',
        'end_time'         => 'datetime:H:i',
        'approved_at'      => 'datetime',
        'attachments'      => 'array',
        'status'           => LogbookStatus::class,
    ];

    /*
    |--------------------------------------------------------------------------
    | RELATIONSHIPS
    |--------------------------------------------------------------------------
    */
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function approver()
    {
        return $this->belongsTo(User::class, 'approver_id');
    }
}
