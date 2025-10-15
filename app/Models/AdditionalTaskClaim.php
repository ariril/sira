<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

class AdditionalTaskClaim extends Model
{
    use HasFactory;

    protected $table = 'additional_task_claims';

    protected $fillable = [
        'additional_task_id',
        'user_id',
        'status',
        'claimed_at',
        'completed_at',
        'cancelled_at',
        'cancel_deadline_at',
        'cancel_reason',
        'penalty_type',
        'penalty_value',
        'penalty_applied',
        'penalty_applied_at',
        'penalty_amount',
        'penalty_note',
    ];

    protected $casts = [
        'claimed_at'         => 'datetime',
        'completed_at'       => 'datetime',
        'cancelled_at'       => 'datetime',
        'cancel_deadline_at' => 'datetime',
        'penalty_applied_at' => 'datetime',
        'penalty_value'      => 'decimal:2',
        'penalty_amount'     => 'decimal:2',
        'penalty_applied'    => 'boolean',
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

    /* =========================================================================
     |  SCOPES
     ========================================================================= */

    // Hanya klaim yang masih aktif
    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    // Klaim yang melewati tenggat cancel
    public function scopeOverdue($query)
    {
        return $query->where('status', 'active')
            ->whereNotNull('cancel_deadline_at')
            ->where('cancel_deadline_at', '<', now());
    }

    /* =========================================================================
     |  LOGIKA / HELPER
     ========================================================================= */

    // Cek apakah user masih boleh cancel
    public function canCancel(): bool
    {
        if ($this->status !== 'active') {
            return false;
        }
        if (!$this->cancel_deadline_at) {
            return true;
        }
        return now()->lessThanOrEqualTo($this->cancel_deadline_at);
    }

    // Hitung sanksi aktual (percent/amount)
    public function calculatePenalty(): float
    {
        if ($this->penalty_type === 'none') {
            return 0;
        }

        if ($this->penalty_type === 'amount') {
            return (float) $this->penalty_value;
        }

        if ($this->penalty_type === 'percent' && $this->task && $this->task->bonus_amount) {
            // misal: sanksi % terhadap bonus task
            return ($this->penalty_value / 100) * $this->task->bonus_amount;
        }

        return 0;
    }

    // Terapkan sanksi (simulasi logika potong remunerasi)
    public function applyPenalty(?string $note = null): void
    {
        $this->penalty_applied   = true;
        $this->penalty_applied_at = now();
        $this->penalty_amount     = $this->calculatePenalty();
        $this->penalty_note       = $note ?? 'Sanksi otomatis karena lewat tenggat cancel';
        $this->save();
    }

    // Tandai cancel
    public function cancel(string $reason = null): bool
    {
        if (!$this->canCancel()) {
            // otomatis kenakan sanksi bila lewat tenggat
            $this->status = 'cancelled';
            $this->cancelled_at = now();
            $this->cancel_reason = $reason;
            $this->penalty_type  = $this->penalty_type === 'none' ? 'percent' : $this->penalty_type;
            $this->penalty_value = $this->penalty_value ?: 10; // default 10%
            $this->applyPenalty('Batal lewat tenggat waktu');
            return true;
        }

        $this->update([
            'status'        => 'cancelled',
            'cancelled_at'  => now(),
            'cancel_reason' => $reason,
        ]);
        return true;
    }
}
