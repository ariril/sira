<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Illuminate\Database\Eloquent\Builder;
use App\Services\AdditionalTasks\AdditionalTaskStatusService;

class AdditionalTaskClaim extends Model
{
    use HasFactory;

    protected $table = 'additional_task_claims';

    protected $fillable = [
        'additional_task_id',
        'user_id',
        'status',
        'submitted_at',
        'result_file_path',
        'result_note',
        'awarded_points',
        'reviewed_by_id',
        'reviewed_at',
        'review_comment',
    ];

    protected $casts = [
        'submitted_at'       => 'datetime',
        'reviewed_at'        => 'datetime',
        'awarded_points'     => 'decimal:2',
    ];

    /* =========================================================================
     |  RELASI
     ========================================================================= */

    // Relasi ke tugas tambahan
    public function task()
    {
        return $this->belongsTo(AdditionalTask::class, 'additional_task_id');
    }

    // Relasi ke user/pegawai yang mengklaim
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function reviewedBy()
    {
        return $this->belongsTo(User::class, 'reviewed_by_id');
    }

    /* =========================================================================
     |  SCOPES
     ========================================================================= */

    // Klaim yang dihitung untuk kuota (submitted/approved)
    public function scopeQuotaCounted(Builder $query): Builder
    {
        return $query->whereIn('status', AdditionalTaskStatusService::ACTIVE_STATUSES);
    }
}
