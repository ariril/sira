<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\User;
use App\Models\MultiRaterAssessment;
use App\Models\PerformanceCriteria;
use App\Services\MultiRater\AssessorTypeResolver;

class GenerateMultiRaterInvites extends Command
{
    protected $signature = 'mra:generate {period_id} {--reset}';
    protected $description = 'Generate 360° invitations for active 360-based criterias across users';

    public function handle(): int
    {
        $periodId = (int)$this->argument('period_id');
        $reset = (bool)$this->option('reset');

        $criterias = PerformanceCriteria::where('is_360', true)->where('is_active', true)->exists();
        if (!$criterias) { $this->warn('No 360-based criteria active; nothing to generate.'); return 0; }

        if ($reset) {
            MultiRaterAssessment::where('assessment_period_id', $periodId)->delete();
        }

        // Assessor type must be determined by PROFESSION (not role), except kepala_poliklinik.
        $users = User::query()
            ->with(['roles:id,slug', 'profession:id,code'])
            ->get(['id', 'unit_id', 'profession_id', 'last_role']);
        $byUnit = $users->groupBy('unit_id');

        $polyclinicHeads = $users->filter(fn ($u) => $u->hasRole('kepala_poliklinik'));

        $count = 0;
        foreach ($users as $u) {
            $code = $u->profession?->code;
            $isDoctorOrNurse = $code && (str_starts_with($code, 'DOK') || $code === 'PRW');
            if (!$isDoctorOrNurse) {
                continue;
            }

            // Self assessment
            $count += $this->ensureInvite($u->id, $u->id, 'self', $periodId);

            // Kepala Poliklinik acts as supervisor for dokter/perawat
            foreach ($polyclinicHeads as $head) {
                $count += $this->ensureInvite($u->id, $head->id, 'supervisor', $periodId);
            }

            // Within same unit: generate invites using profession-based assessor_type
            $candidates = ($byUnit[$u->unit_id] ?? collect())
                ->filter(function ($x) {
                    $c = $x->profession?->code;
                    return $c && (str_starts_with($c, 'DOK') || $c === 'PRW');
                })
                ->filter(fn ($x) => (int) $x->id !== (int) $u->id);

            foreach ($candidates as $assessor) {
                $type = AssessorTypeResolver::resolve($assessor, $u);
                if ($type === 'self') {
                    continue;
                }
                $count += $this->ensureInvite($u->id, $assessor->id, $type, $periodId);
            }
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
