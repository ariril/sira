<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Carbon\Carbon;

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

    public function additionalContributions()
    {
        return $this->hasMany(AdditionalContribution::class, 'assessment_period_id');
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
    public function scopeActive($q)
    {
        return $q->where('status', self::STATUS_ACTIVE);
    }

    public function getIsActiveAttribute(): bool
    {
        return (string)($this->attributes['status'] ?? '') === self::STATUS_ACTIVE;
    }

    // Transitions
    public function activate(?int $byUserId = null): void
    {
        // Demote other active periods
        DB::table('assessment_periods')->where('id', '!=', $this->id)->where('status', self::STATUS_ACTIVE)
            ->update(['status' => self::STATUS_DRAFT]);
        $updates = ['status' => self::STATUS_ACTIVE];
        if (Schema::hasColumn('assessment_periods','locked_at'))    $updates['locked_at'] = null;
        if (Schema::hasColumn('assessment_periods','locked_by_id')) $updates['locked_by_id'] = null;
        if (Schema::hasColumn('assessment_periods','closed_at'))    $updates['closed_at'] = null;
        if (Schema::hasColumn('assessment_periods','closed_by_id')) $updates['closed_by_id'] = null;
        DB::table('assessment_periods')->where('id', $this->id)->update($updates);
        $this->refresh();
    }

    public function lock(?int $byUserId = null, ?string $notes = null): void
    {
        $updates = ['status' => self::STATUS_LOCKED];
        if (Schema::hasColumn('assessment_periods','locked_at'))    $updates['locked_at'] = now();
        if (Schema::hasColumn('assessment_periods','locked_by_id')) $updates['locked_by_id'] = $byUserId;
        if ($notes !== null && Schema::hasColumn('assessment_periods','notes')) $updates['notes'] = $notes;
        DB::table('assessment_periods')->where('id', $this->id)->update($updates);
        $this->refresh();
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
