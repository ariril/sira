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
        'due_date',
        'due_time',
        'points',
        'max_claims',
        'status',
        'created_by',
    ];

    protected $casts = [
        'due_date'     => 'date',
        'due_time'     => 'string',
        'points'       => 'decimal:2',
    ];

    public function getPolicyDocUrlAttribute(): ?string
    {
        $path = (string) ($this->policy_doc_path ?? '');
        if ($path === '') {
            return null;
        }

        return asset('storage/' . ltrim($path, '/'));
    }

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

    public function createdBy()
    {
        return $this->creator();
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
        return $query;
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
