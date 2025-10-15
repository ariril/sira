<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Support\Carbon;

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
        'bonus_amount',
        'points',
        'max_claims',
        'status',
        'created_by',

        // Tambahan untuk sistem claim
        'claimed_by',
        'claimed_at',
        'claim_status',
    ];

    protected $casts = [
        'start_date'   => 'date',
        'due_date'     => 'date',
        'bonus_amount' => 'decimal:2',
        'points'       => 'decimal:2',
        'claimed_at'   => 'datetime',
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

    // Relasi ke pegawai yang meng-claim (single claim)
    public function claimedBy()
    {
        return $this->belongsTo(User::class, 'claimed_by');
    }

    // Relasi ke riwayat claim (multi-claim atau audit)
    public function claims()
    {
        return $this->hasMany(AdditionalTaskClaim::class, 'additional_task_id');
    }

    // Jika masih dipakai: relasi kontribusi tambahan
    public function contributions()
    {
        return $this->hasMany(AdditionalContribution::class, 'task_id');
    }

    /* ============================================================
     |  HELPER / LOGIKA
     ============================================================ */

    // Cek apakah task masih bisa di-claim
    public function isAvailable(): bool
    {
        return $this->claim_status === 'open' && is_null($this->claimed_by);
    }

    // Claim oleh user
    public function claimBy(User $user): bool
    {
        if (!$this->isAvailable()) {
            return false;
        }

        $this->update([
            'claimed_by'   => $user->id,
            'claimed_at'   => now(),
            'claim_status' => 'claimed',
        ]);

        // Simpan ke riwayat klaim
        $this->claims()->create([
            'user_id'            => $user->id,
            'status'             => 'active',
            'claimed_at'         => now(),
            'cancel_deadline_at' => now()->addDay(), // default 1 hari
        ]);

        return true;
    }

    // Cancel oleh user
    public function cancelBy(User $user, string $reason = null): bool
    {
        $claim = $this->claims()
            ->where('user_id', $user->id)
            ->where('status', 'active')
            ->latest('claimed_at')
            ->first();

        if (!$claim) {
            return false;
        }

        $claim->cancel($reason);

        $this->update([
            'claimed_by'   => null,
            'claimed_at'   => null,
            'claim_status' => 'open',
        ]);

        return true;
    }

    // Tandai selesai
    public function completeBy(User $user): bool
    {
        $claim = $this->claims()
            ->where('user_id', $user->id)
            ->where('status', 'active')
            ->latest('claimed_at')
            ->first();

        if (!$claim) {
            return false;
        }

        $claim->update([
            'status'       => 'completed',
            'completed_at' => now(),
        ]);

        $this->update(['claim_status' => 'completed']);

        return true;
    }

    // Format tanggal jatuh tempo untuk UI
    public function getFormattedDueDateAttribute(): string
    {
        return Carbon::parse($this->due_date)->translatedFormat('d F Y');
    }
}
