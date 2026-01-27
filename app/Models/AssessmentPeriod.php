<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Validation\ValidationException;

use App\Models\User;

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
        'rejected_level',
        'rejected_by_id',
        'rejected_at',
        'rejected_reason',
        'revision_opened_by_id',
        'revision_opened_at',
        'revision_opened_reason',
        'approval_attempt',
    ];

    protected $casts = [
        'start_date' => 'date',
        'end_date'   => 'date',
        'locked_at'  => 'datetime',
        'closed_at'  => 'datetime',
        'rejected_at' => 'datetime',
        'revision_opened_at' => 'datetime',
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

    public function rejectedBy()
    {
        return $this->belongsTo(User::class, 'rejected_by_id');
    }

    public function revisionOpenedBy()
    {
        return $this->belongsTo(User::class, 'revision_opened_by_id');
    }

    /*
    |--------------------------------------------------------------------------
    | BUSINESS LOGIC
    |--------------------------------------------------------------------------
    */
    public const STATUS_DRAFT   = 'draft';
    public const STATUS_ACTIVE  = 'active';
    public const STATUS_REVISION = 'revision';
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
        self::STATUS_REVISION,
        self::STATUS_LOCKED,
        self::STATUS_APPROVAL,
        self::STATUS_CLOSED,
    ];

    public const NON_DELETABLE_STATUSES = [
        self::STATUS_ACTIVE,
        self::STATUS_REVISION,
        self::STATUS_LOCKED,
        self::STATUS_APPROVAL,
        self::STATUS_CLOSED,
    ];

    public static function statusOptions(): array
    {
        return [
            self::STATUS_DRAFT => 'Draft',
            self::STATUS_ACTIVE => 'Aktif',
            self::STATUS_REVISION => 'Revisi',
            self::STATUS_LOCKED => 'Dikunci',
            self::STATUS_APPROVAL => 'Persetujuan',
            self::STATUS_CLOSED => 'Ditutup',
        ];
    }

    public function isRejectedApproval(): bool
    {
        return (string) ($this->status ?? '') === self::STATUS_APPROVAL && $this->rejected_at !== null;
    }

    public function isInRevision(): bool
    {
        return (string) ($this->status ?? '') === self::STATUS_REVISION;
    }

    public function currentApprovalAttempt(): int
    {
        return (int) ($this->approval_attempt ?? 0);
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
            self::STATUS_REVISION,
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
        $protected = [self::STATUS_LOCKED, self::STATUS_APPROVAL, self::STATUS_REVISION, self::STATUS_CLOSED];

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
        $this->ensureWeightsReadyForLock();

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
        $this->ensureWeightsReadyForLock();
        $updates = ['status' => self::STATUS_CLOSED];
        if (Schema::hasColumn('assessment_periods','closed_at'))    $updates['closed_at'] = now();
        if (Schema::hasColumn('assessment_periods','closed_by_id')) $updates['closed_by_id'] = $byUserId;
        if ($notes !== null && Schema::hasColumn('assessment_periods','notes')) $updates['notes'] = $notes;
        DB::table('assessment_periods')->where('id', $this->id)->update($updates);
        $this->refresh();
    }

    public function ensureWeightsReadyForLock(): void
    {
        if (!Schema::hasTable('unit_criteria_weights') || !Schema::hasTable('users')) {
            return;
        }

        $periodId = (int) $this->id;
        if ($periodId <= 0) {
            return;
        }

        $previous = $this->resolvePreviousPeriodForFallback();
        $previousId = $previous ? (int) $previous->id : 0;

        $roleColumn = Schema::hasColumn('users', 'role')
            ? 'role'
            : (Schema::hasColumn('users', 'last_role') ? 'last_role' : null);

        if ($roleColumn === null) {
            return;
        }

        $unitIds = DB::table('users')
            ->where($roleColumn, User::ROLE_PEGAWAI_MEDIS)
            ->whereNotNull('unit_id')
            ->distinct()
            ->pluck('unit_id')
            ->map(fn($v) => (int) $v)
            ->filter(fn($v) => $v > 0)
            ->values()
            ->all();

        if (empty($unitIds)) {
            return;
        }

        foreach ($unitIds as $unitId) {
            $this->ensureUnitCriteriaWeightsReadyForUnit($unitId, $periodId, $previousId);

            if (Schema::hasTable('unit_rater_weights') && Schema::hasTable('performance_criterias')) {
                $criteriaIds = DB::table('unit_criteria_weights as ucw')
                    ->join('performance_criterias as pc', 'pc.id', '=', 'ucw.performance_criteria_id')
                    ->where('ucw.unit_id', $unitId)
                    ->where('ucw.assessment_period_id', $periodId)
                    ->where('ucw.status', 'active')
                    ->where('pc.is_360', 1)
                    ->where('pc.is_active', 1)
                    ->distinct()
                    ->pluck('pc.id')
                    ->map(fn($v) => (int) $v)
                    ->filter(fn($v) => $v > 0)
                    ->values()
                    ->all();

                if (!empty($criteriaIds)) {
                    $this->ensureRaterWeightsReadyForUnit($unitId, $periodId, $previousId, $criteriaIds);
                }
            }
        }
    }

    private function ensureUnitCriteriaWeightsReadyForUnit(int $unitId, int $periodId, int $previousId): void
    {
        $hasActive = DB::table('unit_criteria_weights')
            ->where('unit_id', $unitId)
            ->where('assessment_period_id', $periodId)
            ->where('status', 'active')
            ->exists();

        if ($hasActive) {
            return;
        }

        $draftIds = DB::table('unit_criteria_weights')
            ->where('unit_id', $unitId)
            ->where('assessment_period_id', $periodId)
            ->where('status', 'draft')
            ->pluck('id')
            ->map(fn($v) => (int) $v)
            ->filter(fn($v) => $v > 0)
            ->values()
            ->all();

        if (!empty($draftIds)) {
            DB::table('unit_criteria_weights')
                ->whereIn('id', $draftIds)
                ->update([
                    'status' => 'active',
                    'updated_at' => now(),
                ]);
            return;
        }

        $hasWasActiveBefore = Schema::hasColumn('unit_criteria_weights', 'was_active_before');
        $prevRows = $previousId > 0
            ? DB::table('unit_criteria_weights')
                ->where('unit_id', $unitId)
                ->where('assessment_period_id', $previousId)
                ->whereIn('status', ['active', 'archived'])
                ->when($hasWasActiveBefore, fn($q) => $q->where('was_active_before', 1))
                ->get([
                    'unit_id',
                    'performance_criteria_id',
                    'weight',
                    'policy_doc_path',
                    'policy_note',
                    'proposed_by',
                    'proposed_note',
                    'decided_by',
                    'decided_at',
                    'decided_note',
                ])
            : collect();

        if ($prevRows->isEmpty()) {
            throw ValidationException::withMessages([
                'status' => 'Periode tidak dapat diproses karena bobot kinerja tidak tersedia dan tidak ditemukan bobot aktif pada periode sebelumnya.',
            ]);
        }

        $now = now();
        $insertRows = [];
        foreach ($prevRows as $r) {
            $insertRows[] = [
                'unit_id' => (int) ($r->unit_id ?? 0),
                'performance_criteria_id' => (int) ($r->performance_criteria_id ?? 0),
                'weight' => (float) ($r->weight ?? 0.0),
                'assessment_period_id' => $periodId,
                'status' => 'active',
                'was_active_before' => 0,
                'policy_doc_path' => $r->policy_doc_path ?? null,
                'policy_note' => $r->policy_note ?? null,
                'proposed_by' => $r->proposed_by ?? null,
                'proposed_note' => $r->proposed_note ?? null,
                'decided_by' => $r->decided_by ?? null,
                'decided_at' => $r->decided_at ?? null,
                'decided_note' => $r->decided_note ?? null,
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        if (!empty($insertRows)) {
            DB::table('unit_criteria_weights')->insert($insertRows);
        }
    }

    /**
     * @param array<int> $criteriaIds
     */
    private function ensureRaterWeightsReadyForUnit(int $unitId, int $periodId, int $previousId, array $criteriaIds): void
    {
        $criteriaIds = array_values(array_unique(array_map('intval', $criteriaIds)));
        $criteriaIds = array_values(array_filter($criteriaIds, fn($v) => $v > 0));
        if (empty($criteriaIds)) {
            return;
        }

        foreach ($criteriaIds as $criteriaId) {
            $hasActive = DB::table('unit_rater_weights')
                ->where('unit_id', $unitId)
                ->where('assessment_period_id', $periodId)
                ->where('performance_criteria_id', $criteriaId)
                ->where('status', 'active')
                ->exists();

            if ($hasActive) {
                continue;
            }

            $draftIds = DB::table('unit_rater_weights')
                ->where('unit_id', $unitId)
                ->where('assessment_period_id', $periodId)
                ->where('performance_criteria_id', $criteriaId)
                ->where('status', 'draft')
                ->pluck('id')
                ->map(fn($v) => (int) $v)
                ->filter(fn($v) => $v > 0)
                ->values()
                ->all();

            if (!empty($draftIds)) {
                DB::table('unit_rater_weights')
                    ->whereIn('id', $draftIds)
                    ->update([
                        'status' => 'active',
                        'updated_at' => now(),
                    ]);
                continue;
            }

            $hasWasActiveBefore = Schema::hasColumn('unit_rater_weights', 'was_active_before');
            $prevRows = $previousId > 0
                ? DB::table('unit_rater_weights')
                    ->where('unit_id', $unitId)
                    ->where('assessment_period_id', $previousId)
                    ->where('performance_criteria_id', $criteriaId)
                    ->whereIn('status', ['active', 'archived'])
                    ->when($hasWasActiveBefore, fn($q) => $q->where('was_active_before', 1))
                    ->get([
                        'unit_id',
                        'performance_criteria_id',
                        'assessee_profession_id',
                        'assessor_type',
                        'assessor_profession_id',
                        'assessor_level',
                        'weight',
                        'proposed_by',
                        'decided_by',
                        'decided_at',
                        'decided_note',
                    ])
                : collect();

            if ($prevRows->isEmpty()) {
                throw ValidationException::withMessages([
                    'status' => 'Periode tidak dapat diproses karena bobot kinerja tidak tersedia dan tidak ditemukan bobot aktif pada periode sebelumnya.',
                ]);
            }

            $now = now();
            $insertRows = [];
            foreach ($prevRows as $r) {
                $insertRows[] = [
                    'assessment_period_id' => $periodId,
                    'unit_id' => (int) ($r->unit_id ?? 0),
                    'performance_criteria_id' => (int) ($r->performance_criteria_id ?? 0),
                    'assessee_profession_id' => (int) ($r->assessee_profession_id ?? 0),
                    'assessor_type' => (string) ($r->assessor_type ?? ''),
                    'assessor_profession_id' => $r->assessor_profession_id === null ? null : (int) $r->assessor_profession_id,
                    'assessor_level' => $r->assessor_level === null ? null : (int) $r->assessor_level,
                    'weight' => (float) ($r->weight ?? 0.0),
                    'status' => 'active',
                    'was_active_before' => 0,
                    'proposed_by' => $r->proposed_by ?? null,
                    'decided_by' => $r->decided_by ?? null,
                    'decided_at' => $r->decided_at ?? null,
                    'decided_note' => $r->decided_note ?? null,
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }

            if (!empty($insertRows)) {
                DB::table('unit_rater_weights')->insert($insertRows);
            }
        }
    }

    private function resolvePreviousPeriodForFallback(): ?object
    {
        if (!Schema::hasTable('assessment_periods')) {
            return null;
        }

        $query = DB::table('assessment_periods')
            ->where('id', '!=', (int) $this->id)
            ->whereIn('status', [self::STATUS_LOCKED, self::STATUS_APPROVAL, self::STATUS_CLOSED]);

        if (Schema::hasColumn('assessment_periods', 'end_date') && Schema::hasColumn('assessment_periods', 'start_date') && !empty($this->start_date)) {
            $query->where('end_date', '<', $this->start_date)
                ->orderByDesc('end_date');
        } else {
            $query->where('id', '<', (int) $this->id)
                ->orderByDesc('id');
        }

        return $query->first();
    }
}
