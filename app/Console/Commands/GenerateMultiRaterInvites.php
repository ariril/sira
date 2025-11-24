<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\User;
use App\Models\MultiRaterAssessment;
use App\Models\PerformanceCriteria;

class GenerateMultiRaterInvites extends Command
{
    protected $signature = 'mra:generate {period_id} {--reset}';
    protected $description = 'Generate 360° invitations for active 360-based criterias across users';

    public function handle(): int
    {
        $periodId = (int)$this->argument('period_id');
        $reset = (bool)$this->option('reset');

        $criterias = PerformanceCriteria::where('is_360_based', true)->where('is_active', true)->exists();
        if (!$criterias) { $this->warn('No 360-based criteria active; nothing to generate.'); return 0; }

        if ($reset) {
            MultiRaterAssessment::where('assessment_period_id', $periodId)->delete();
        }

        // Eager load roles pivot; 'role' kolom lama sudah dihapus.
        $users = User::with('roles:id,slug')->get(['id','unit_id','last_role']);
        $byUnit = $users->groupBy('unit_id');

        $count = 0;
        foreach ($users as $u) {
            $isMedis = $u->hasRole('pegawai_medis');
            $isKepalaUnit = $u->hasRole('kepala_unit');
            if (!$isMedis && !$isKepalaUnit) continue; // only assess medis or kepala_unit

            // Self assessment
            $count += $this->ensureInvite($u->id, $u->id, 'self', $periodId);

            // Supervisor: kepala_unit of same unit when assessee is medis
            if ($isMedis) {
                $supervisor = $users->first(fn($x) => $x->unit_id === $u->unit_id && $x->hasRole('kepala_unit'));
                if ($supervisor) $count += $this->ensureInvite($u->id, $supervisor->id, 'supervisor', $periodId);
            }

            // Kepala Poliklinik as additional supervisor (if exists)
            $polichief = $users->first(fn($x) => $x->hasRole('kepala_poliklinik'));
            if ($polichief) $count += $this->ensureInvite($u->id, $polichief->id, 'supervisor', $periodId);

            // Peer medis in same unit (exclude self)
            if ($isMedis) {
                $peers = ($byUnit[$u->unit_id] ?? collect())
                    ->filter(fn($x) => $x->hasRole('pegawai_medis') && $x->id !== $u->id)
                    ->take(2);
                foreach ($peers as $peer) {
                    $count += $this->ensureInvite($u->id, $peer->id, 'peer', $periodId);
                }
            }
            // Subordinate mapping reserved for future
        }

        $this->info("Generated/ensured {$count} invitations for period {$periodId}.");
        return 0;
    }

    private function ensureInvite(int $assesseeId, ?int $assessorId, string $type, int $periodId): int
    {
        $exists = MultiRaterAssessment::where([
            'assessee_id' => $assesseeId,
            'assessor_id' => $assessorId,
            'assessor_type' => $type,
            'assessment_period_id' => $periodId,
        ])->exists();
        if ($exists) return 0;
        MultiRaterAssessment::create([
            'assessee_id' => $assesseeId,
            'assessor_id' => $assessorId,
            'assessor_type' => $type,
            'assessment_period_id' => $periodId,
            'status' => 'invited',
        ]);
        return 1;
    }
}
