<?php

namespace App\Enums;

enum AdditionalTaskClaimStatus: string
{
    case ACTIVE    = 'active';       // diklaim & dikerjakan
    case SUBMITTED = 'submitted';    // hasil tugas dikirim pegawai
    case VALIDATED = 'validated';    // validasi awal oleh kepala unit
    case APPROVED  = 'approved';     // disetujui final (skor/bonus diproses)
    case REJECTED  = 'rejected';     // ditolak saat review
    case COMPLETED = 'completed';    // dipertahankan untuk backward compatibility
    case CANCELLED = 'cancelled';    // dibatalkan (dalam/luar tenggat)
    case AUTO_UNCLAIM = 'auto_unclaim'; // dilepas sistem

    public function isTerminal(): bool
    {
        return in_array($this, [self::APPROVED, self::REJECTED, self::CANCELLED]);
    }
}
