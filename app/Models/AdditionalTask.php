<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Support\Carbon;
use App\Services\AdditionalTasks\AdditionalTaskStatusService;
use Illuminate\Database\Eloquent\Builder;
use App\Models\AssessmentPeriod;

class AdditionalTask extends Model
{
    use HasFactory;

    protected $table = 'additional_tasks';

    protected $fillable = [
        'unit_id',
        'assessment_period_id',
        'title',
        'description',
        'policy_doc_path',
        'start_date',
        'due_date',
        'start_time',
        'due_time',
        'bonus_amount',
        'points',
        'max_claims',
        'cancel_window_hours',
        'default_penalty_type',
        'default_penalty_value',
        'penalty_base',
        'status',
        'created_by',
    ];

    protected $casts = [
        'start_date'   => 'date',
        'due_date'     => 'date',
        'start_time'   => 'string',
        'due_time'     => 'string',
        'bonus_amount' => 'decimal:2',
        'points'       => 'decimal:2',
        'cancel_window_hours' => 'integer',
        'default_penalty_value' => 'decimal:2',
    ];

    /* ============================================================
     |  RELASI
     ============================================================ */

    public function unit()
    {
        return $this->belongsTo(Unit::class);
    }

    public function period()
    {
        return $this->belongsTo(AssessmentPeriod::class, 'assessment_period_id');
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    // Relasi ke riwayat claim (multi-claim atau audit)
    public function claims()
    {
        return $this->hasMany(AdditionalTaskClaim::class, 'additional_task_id');
    }

    // Format tanggal jatuh tempo untuk UI
    public function getFormattedDueDateAttribute(): string
    {
        $time = $this->due_time ?: '23:59:59';
        $date = Carbon::parse($this->due_date)->toDateString();
        $tz = config('app.timezone');
        return Carbon::parse($date . ' ' . $time, $tz)->format('d M Y H:i');
    }

    public function refreshLifecycleStatus(): void
    {
        $latest = $this->fresh() ?? $this;
        AdditionalTaskStatusService::sync($latest);
    }

    /* ============================================================
     |  SCOPES
     ============================================================ */

    public function scopeForUnit(Builder $query, ?int $unitId): Builder
    {
        if ($unitId === null) {
            return $query->whereNull('unit_id');
        }

        return $query->where('unit_id', $unitId);
    }

    public function scopeForActivePeriod(Builder $query): Builder
    {
        return $query->whereHas('period', fn (Builder $q) => $q->where('status', AssessmentPeriod::STATUS_ACTIVE));
    }

    public function scopeOpen(Builder $query): Builder
    {
        return $query->where('status', 'open');
    }

    public function scopeNotDraft(Builder $query): Builder
    {
        return $query->where('status', '!=', 'draft');
    }

    public function scopeExcludeCreator(Builder $query, ?int $userId): Builder
    {
        if (!$userId) {
            return $query;
        }

        return $query->where(function (Builder $q) use ($userId) {
            $q->whereNull('created_by')->orWhere('created_by', '!=', $userId);
        });
    }

    public function scopeWithActiveClaimsCount(Builder $query): Builder
    {
        return $query->withCount([
            'claims as active_claims' => function (Builder $q) {
                $q->whereIn('status', AdditionalTaskStatusService::ACTIVE_STATUSES);
            },
        ]);
    }
}
