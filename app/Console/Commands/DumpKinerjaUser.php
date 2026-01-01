<?php

namespace App\Console\Commands;

use App\Models\AssessmentPeriod;
use App\Models\User;
use App\Services\PerformanceScore\PerformanceScoreService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class DumpKinerjaUser extends Command
{
    protected $signature = 'kinerja:dump-user
        {period_id : Assessment period id}
        {unit_id : Unit id}
        {profession_id : Profession id}
        {user : User id or email}
        {--tolerance=0.0001 : Float compare tolerance}';

    protected $description = 'Dump detail kinerja (sum_weight + kriteria included/excluded) untuk 1 user pada scope unit+periode(+profesi).';

    public function handle(PerformanceScoreService $service): int
    {
        $periodId = (int) $this->argument('period_id');
        $unitId = (int) $this->argument('unit_id');
        $professionId = (int) $this->argument('profession_id');
        $userArg = (string) $this->argument('user');
        $tolerance = (float) $this->option('tolerance');

        /** @var AssessmentPeriod|null $period */
        $period = AssessmentPeriod::query()->find($periodId);
        if (!$period) {
            $this->error("Period id={$periodId} tidak ditemukan.");
            return self::FAILURE;
        }

        /** @var User|null $user */
        $user = ctype_digit($userArg)
            ? User::query()->find((int) $userArg)
            : User::query()->where('email', $userArg)->first();

        if (!$user) {
            $this->error("User '{$userArg}' tidak ditemukan.");
            return self::FAILURE;
        }

        $groupQuery = User::role(User::ROLE_PEGAWAI_MEDIS)
            ->where('unit_id', $unitId);
        if ($professionId > 0) {
            $groupQuery->where('profession_id', $professionId);
        }

        $groupUserIds = $groupQuery->pluck('id')->map(fn ($v) => (int) $v)->all();
        if (empty($groupUserIds)) {
            $this->error('Tidak ada user dalam grup (unit/profesi) ini.');
            return self::FAILURE;
        }

        $out = $service->calculate($unitId, $period, $groupUserIds, $professionId);
        $row = $out['users'][(int) $user->id] ?? null;
        if (!$row) {
            $this->error('Output tidak memiliki user target (cek role/unit/profesi).');
            return self::FAILURE;
        }

        $sumWeight = (float) ($row['sum_weight'] ?? 0.0);
        $totalWsm = $row['total_wsm'] ?? null;

        $this->info('Scope');
        $this->line('  period_id=' . $periodId . ' unit_id=' . $unitId . ' profession_id=' . $professionId);
        $this->line('  group_users=' . count($groupUserIds) . ' target_user_id=' . (int) $user->id . ' email=' . (string) $user->email);

        $this->info('WSM');
        $this->line('  sum_weight=' . $sumWeight);
        $this->line('  total_wsm=' . ($totalWsm === null ? 'null' : (string) $totalWsm));

        $weightsRows = DB::table('unit_criteria_weights')
            ->where('unit_id', $unitId)
            ->where('assessment_period_id', $periodId)
            ->orderBy('performance_criteria_id')
            ->get(['performance_criteria_id', 'status', 'weight']);

        if ($weightsRows->isNotEmpty()) {
            $sumActive = 0.0;
            $weightsOut = [];
            foreach ($weightsRows as $wr) {
                $cid = (int) $wr->performance_criteria_id;
                $st = (string) ($wr->status ?? '');
                $w = (float) ($wr->weight ?? 0.0);
                if ($st === 'active') {
                    $sumActive += $w;
                }
                $weightsOut[] = [
                    'criteria_id' => $cid,
                    'status' => $st,
                    'weight' => $w,
                ];
            }

            $this->info('Weights config');
            $this->line('  sum_active_weights=' . $sumActive);
            $this->table(['criteria_id', 'status', 'weight'], $weightsOut);
        }

        $included = [];
        $excludedActiveMissing = [];

        foreach (($row['criteria'] ?? []) as $c) {
            $isIncluded = (bool) ($c['included_in_wsm'] ?? false);
            $activeWeight = (float) ($c['weight'] ?? 0.0);
            $isActive = (bool) ($c['is_active'] ?? false);
            $readiness = (string) ($c['readiness_status'] ?? '');
            $relative = (float) ($c['nilai_relativ_unit'] ?? 0.0);

            $contrib = ($sumWeight > $tolerance)
                ? (($activeWeight / $sumWeight) * $relative)
                : 0.0;

            $rowOut = [
                'criteria_id' => (int) ($c['criteria_id'] ?? 0),
                'criteria' => (string) ($c['criteria_name'] ?? ''),
                'weight_used' => $activeWeight,
                'relative' => $relative,
                'contrib' => round($contrib, 6),
                'ready' => $readiness,
                'active' => $isActive ? 'yes' : 'no',
            ];

            if ($isIncluded) {
                $included[] = $rowOut;
            } elseif ($isActive && $activeWeight > 0.0 && $readiness !== 'ready') {
                $excludedActiveMissing[] = $rowOut;
            }
        }

        $this->info('Included criteria (count=' . count($included) . ')');
        $this->table(
            ['criteria_id', 'criteria', 'weight_used', 'relative', 'contrib', 'ready', 'active'],
            $included
        );

        if (!empty($excludedActiveMissing)) {
            $this->warn('Active-weight criteria excluded due to readiness (these can explain dilution if they are mistakenly marked ready elsewhere)');
            $this->table(
                ['criteria_id', 'criteria', 'weight_used', 'relative', 'contrib', 'ready', 'active'],
                $excludedActiveMissing
            );
        }

        return self::SUCCESS;
    }
}
