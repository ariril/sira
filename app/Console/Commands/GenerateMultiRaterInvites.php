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

        $users = User::query()->get(['id','role','unit_id']);
        $byUnit = $users->groupBy('unit_id');

        $count = 0;
        foreach ($users as $u) {
            // Only assessee for medis and kepala_unit (both get assessed)
            if (!in_array($u->role, ['pegawai_medis','kepala_unit'])) continue;

            // Self
            $count += $this->ensureInvite($u->id, $u->id, 'self', $periodId);

            // Supervisor: kepala_unit of the same unit (if assessee is medis)
            if ($u->role === 'pegawai_medis') {
                $supervisor = $users->firstWhere(fn($x) => $x->unit_id === $u->unit_id && $x->role === 'kepala_unit');
                if ($supervisor) $count += $this->ensureInvite($u->id, $supervisor->id, 'supervisor', $periodId);
            }

            // Kepala Poliklinik as additional supervisor for all medis in poliklinik-type units (best-effort)
            $polichief = $users->firstWhere('role','kepala_poliklinik');
            if ($polichief) $count += $this->ensureInvite($u->id, $polichief->id, 'supervisor', $periodId);

            // Simple peer: another medis in same unit (if exists)
            $peers = ($byUnit[$u->unit_id] ?? collect())->where('role','pegawai_medis')->where('id','!=',$u->id)->take(2);
            foreach ($peers as $peer) { $count += $this->ensureInvite($u->id, $peer->id, 'peer', $periodId); }

            // Subordinate: none by default (optional future mapping)
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
