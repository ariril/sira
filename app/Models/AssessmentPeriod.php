<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;

class AssessmentPeriod extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'start_date',
        'end_date',
        'status',
        'locked_at',
        'closed_at',
        'notes',
    ];

    protected $casts = [
        'start_date' => 'date',
        'end_date'   => 'date',
        'locked_at'  => 'datetime',
        'closed_at'  => 'datetime',
    ];

    /*
    |--------------------------------------------------------------------------
    | RELATIONSHIPS
    |--------------------------------------------------------------------------
    */
    public function performanceAssessments()
    {
        return $this->hasMany(PerformanceAssessment::class, 'assessment_period_id');
    }

    public function remunerations()
    {
        return $this->hasMany(Remuneration::class, 'assessment_period_id');
    }

    /*
    |--------------------------------------------------------------------------
    | BUSINESS LOGIC
    |--------------------------------------------------------------------------
    */
    public const STATUS_DRAFT   = 'draft';
    public const STATUS_ACTIVE  = 'active';
    public const STATUS_LOCKED  = 'locked';
    public const STATUS_APPROVAL = 'approval';
    public const STATUS_CLOSED  = 'closed';

    /**
     * NOTE: 'archived' is used in some legacy checks even though it's not part of STATUSES.
     */
    public const STATUS_ARCHIVED = 'archived';

    public const STATUSES = [
        self::STATUS_DRAFT,
        self::STATUS_ACTIVE,
        self::STATUS_LOCKED,
        self::STATUS_APPROVAL,
        self::STATUS_CLOSED,
    ];

    public const NON_DELETABLE_STATUSES = [
        self::STATUS_ACTIVE,
        self::STATUS_LOCKED,
        self::STATUS_APPROVAL,
        self::STATUS_CLOSED,
    ];

    public static function statusOptions(): array
    {
        return [
            self::STATUS_DRAFT => 'Draft',
            self::STATUS_ACTIVE => 'Aktif',
            self::STATUS_LOCKED => 'Dikunci',
            self::STATUS_APPROVAL => 'Persetujuan',
            self::STATUS_CLOSED => 'Ditutup',
        ];
    }

    public static function statusLabel(string $status): string
    {
        return self::statusOptions()[$status] ?? $status;
    }

    /**
     * Periode dianggap "aktif saat ini" bila hari ini berada pada rentang start_date..end_date
     * dan status bukan CLOSED/ARCHIVED.
     */
    public function isCurrentlyActive(): bool
    {
        $today = Carbon::today();

        if (!$this->start_date || !$this->end_date) {
            return false;
        }

        $status = (string) ($this->status ?? '');
        if ($status === self::STATUS_CLOSED || $status === 'archived') {
            return false;
        }

        return $today->betweenIncluded(Carbon::parse($this->start_date), Carbon::parse($this->end_date));
    }

    public function isNonDeletable(): bool
    {
        return in_array((string) $this->status, self::NON_DELETABLE_STATUSES, true);
    }

    /**
     * Periode dianggap "frozen" bila hasil penilaian sudah harus stabil dan tidak boleh berubah
     * meskipun admin mengubah konfigurasi kriteria (mis. normalization_basis).
     */
    public function isFrozen(): bool
    {
        $status = (string) ($this->status ?? '');

        return in_array($status, [
            self::STATUS_LOCKED,
            self::STATUS_APPROVAL,
            self::STATUS_CLOSED,
            self::STATUS_ARCHIVED,
        ], true);
    }

    protected static function booted(): void
    {
        // After save, re-sync statuses to keep lifecycle consistent
        static::saved(function () {
            self::syncByNow();
        });
    }

    // Sync all periods to reflect current date: statuses and single active row
    public static function syncByNow(): void
    {
        $today = Carbon::today()->toDateString();
        $protected = [self::STATUS_LOCKED, self::STATUS_APPROVAL, self::STATUS_CLOSED];

        // Auto-lock past periods that are not yet locked/approval/closed
        $toLock = self::query()
            ->whereNotIn('status', $protected)
            ->where('end_date', '<', $today)
            ->get();
        foreach ($toLock as $p) {
            $p->lock();
        }

        // Future periods become draft (unless already protected status)
        DB::table('assessment_periods')
            ->whereNotIn('status', $protected)
            ->where('start_date', '>', $today)
            ->update(['status' => self::STATUS_DRAFT]);

        // Candidates within range (could be multiple if data overlapped)
        $candidates = DB::table('assessment_periods')
            ->select('id')
            ->whereNotIn('status', $protected)
            ->where('start_date', '<=', $today)
            ->where('end_date', '>=', $today)
            ->orderBy('start_date')
            ->get();

        if ($candidates->isEmpty()) {
            return; // nothing to activate
        }

        // Mark all candidates as draft first
        DB::table('assessment_periods')
            ->whereIn('id', $candidates->pluck('id'))
            ->update(['status' => self::STATUS_DRAFT]);

        // Choose the latest by start_date to be the single active one
        $winnerId = $candidates->last()->id;
        DB::table('assessment_periods')->where('id', $winnerId)->update(['status' => self::STATUS_ACTIVE]);
    }

    // Helper scopes & accessors
    /**
     * Scope periode "aktif saat ini" (date-based). Dipertahankan namanya agar kompatibel.
     */
    public function scopeActive(Builder $q): Builder
    {
        $today = Carbon::today()->toDateString();
        return $q
            ->where('start_date', '<=', $today)
            ->where('end_date', '>=', $today)
            ->whereNotIn('status', [self::STATUS_CLOSED, 'archived']);
    }

    public function getIsActiveAttribute(): bool
    {
        return $this->isCurrentlyActive();
    }

    // Transitions
    public function activate(?int $byUserId = null): void
    {
        throw new \LogicException('Aktivasi periode manual sudah dihapus. Status aktif ditentukan otomatis berdasarkan tanggal (start_date..end_date).');
    }

    public function lock(?int $byUserId = null, ?string $notes = null): void
    {
        $updates = ['status' => self::STATUS_LOCKED];
        if (Schema::hasColumn('assessment_periods','locked_at'))    $updates['locked_at'] = now();
        if (Schema::hasColumn('assessment_periods','locked_by_id')) $updates['locked_by_id'] = $byUserId;
        if ($notes !== null && Schema::hasColumn('assessment_periods','notes')) $updates['notes'] = $notes;
        DB::table('assessment_periods')->where('id', $this->id)->update($updates);
        $this->refresh();

        // Side-effect: ensure assessments exist once period becomes LOCKED.
        // This keeps manual vs automatic locking consistent.
        try {
            if (class_exists(\App\Services\AssessmentPeriods\PeriodPerformanceAssessmentService::class)) {
                app(\App\Services\AssessmentPeriods\PeriodPerformanceAssessmentService::class)->initializeForPeriod($this);
            }
        } catch (\Throwable $e) {
            // Intentionally swallow: lifecycle status should still sync even if recalculation fails.
        }
    }

    public function close(?int $byUserId = null, ?string $notes = null): void
    {
        $updates = ['status' => self::STATUS_CLOSED];
        if (Schema::hasColumn('assessment_periods','closed_at'))    $updates['closed_at'] = now();
        if (Schema::hasColumn('assessment_periods','closed_by_id')) $updates['closed_by_id'] = $byUserId;
        if ($notes !== null && Schema::hasColumn('assessment_periods','notes')) $updates['notes'] = $notes;
        DB::table('assessment_periods')->where('id', $this->id)->update($updates);
        $this->refresh();
    }
}
