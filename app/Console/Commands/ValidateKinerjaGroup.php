<?php

namespace App\Console\Commands;

use App\Models\AssessmentPeriod;
use App\Models\User;
use App\Services\PerformanceScore\PerformanceScoreService;
use Illuminate\Console\Command;

class ValidateKinerjaGroup extends Command
{
    protected $signature = 'kinerja:validate-group
        {period_id : Assessment period id}
        {unit_id : Unit id}
        {profession_id? : Profession id (omit to validate profession_id NULL)}
        {--tolerance=0.05 : Allowed numeric drift in points}';

    protected $description = 'Validasi sinkronisasi raw → normalisasi (TOTAL_UNIT) → relatif → WSM untuk 1 grup unit+profesi+periode.';

    public function handle(PerformanceScoreService $svc): int
    {
        $periodId = (int) $this->argument('period_id');
        $unitId = (int) $this->argument('unit_id');
        $professionArg = $this->argument('profession_id');
        $professionId = $professionArg !== null ? (int) $professionArg : null;
        $tol = (float) $this->option('tolerance');
        $tol = $tol > 0 ? $tol : 0.05;

        if ($periodId <= 0 || $unitId <= 0) {
            $this->error('period_id dan unit_id wajib > 0.');
            return self::FAILURE;
        }

        $period = AssessmentPeriod::query()->find($periodId);
        if (!$period) {
            $this->error('AssessmentPeriod tidak ditemukan: ' . $periodId);
            return self::FAILURE;
        }

        $userQuery = User::query()
            ->role(User::ROLE_PEGAWAI_MEDIS)
            ->where('unit_id', $unitId);

        if ($professionArg === null) {
            $userQuery->whereNull('profession_id');
        } else {
            $userQuery->where('profession_id', $professionId);
        }

        $userIds = $userQuery->pluck('id')->map(fn ($v) => (int) $v)->all();
        if (empty($userIds)) {
            $this->warn('Tidak ada pegawai medis untuk grup ini.');
            return self::SUCCESS;
        }

        $calc = $svc->calculate($unitId, $period, $userIds, $professionId);

        $criteriaIds = array_values(array_map('intval', (array) ($calc['criteria_ids'] ?? [])));
        $sumRawByCriteria = (array) ($calc['sum_raw_by_criteria'] ?? []);
        $maxNormByCriteria = (array) ($calc['max_by_criteria'] ?? []);
        $minNormByCriteria = (array) ($calc['min_by_criteria'] ?? []);

        if (empty($criteriaIds)) {
            $this->warn('Tidak ada kriteria berbobot untuk grup ini pada periode tsb.');
            return self::SUCCESS;
        }

        $perCriteria = [];
        $userTotalErrors = [];

        foreach ($userIds as $uid) {
            $uid = (int) $uid;
            $userRow = $calc['users'][$uid] ?? null;
            if (!$userRow) {
                continue;
            }

            $criteriaRows = (array) ($userRow['criteria'] ?? []);

            $sumWeight = 0.0;
            $sumWeighted = 0.0;

            foreach ($criteriaRows as $r) {
                $cid = (int) ($r['criteria_id'] ?? 0);
                if ($cid <= 0) {
                    continue;
                }

                $norm = (float) ($r['nilai_normalisasi'] ?? 0.0);
                $rel = (float) ($r['nilai_relativ_unit'] ?? 0.0);

                if (!isset($perCriteria[$cid])) {
                    $perCriteria[$cid] = [
                        'criteria_id' => $cid,
                        'sum_norm' => 0.0,
                        'max_rel' => null,
                        'count' => 0,
                    ];
                }

                $perCriteria[$cid]['sum_norm'] += $norm;
                $perCriteria[$cid]['max_rel'] = $perCriteria[$cid]['max_rel'] === null
                    ? $rel
                    : max((float) $perCriteria[$cid]['max_rel'], $rel);
                $perCriteria[$cid]['count'] += 1;

                if (!empty($r['included_in_wsm'])) {
                    $w = (float) ($r['weight'] ?? 0.0);
                    $sumWeight += $w;
                    $sumWeighted += ($w * $rel);
                }
            }

            $recomputed = $sumWeight > 0.0 ? ($sumWeighted / $sumWeight) : null;
            $reported = array_key_exists('total_wsm', $userRow) ? $userRow['total_wsm'] : null;
            $reported = $reported !== null ? (float) $reported : null;

            if ($recomputed === null && $reported === null) {
                continue;
            }

            if ($recomputed === null || $reported === null || abs($recomputed - $reported) > $tol) {
                $userTotalErrors[] = [
                    'user_id' => $uid,
                    'reported_total_wsm' => $reported !== null ? round($reported, 6) : null,
                    'recomputed_total_wsm' => $recomputed !== null ? round($recomputed, 6) : null,
                    'sum_weight' => round($sumWeight, 6),
                ];
            }
        }

        $criteriaErrors = [];
        foreach ($criteriaIds as $cid) {
            $cid = (int) $cid;
            $sumNorm = (float) ($perCriteria[$cid]['sum_norm'] ?? 0.0);
            $maxRel = $perCriteria[$cid]['max_rel'] ?? null;
            $maxRel = $maxRel !== null ? (float) $maxRel : null;

            $den = array_key_exists($cid, $sumRawByCriteria) ? (float) $sumRawByCriteria[$cid] : 0.0;
            $maxNorm = array_key_exists($cid, $maxNormByCriteria) ? (float) $maxNormByCriteria[$cid] : 0.0;
            $minNorm = array_key_exists($cid, $minNormByCriteria) ? (float) $minNormByCriteria[$cid] : 0.0;

            $expectedSumNorm = $den > 0.0 ? 100.0 : 0.0;
            $sumNormOk = abs($sumNorm - $expectedSumNorm) <= $tol;

            $expectedMaxRel = $maxNorm > 0.0 ? 100.0 : 0.0;
            $maxRelVal = $maxRel ?? 0.0;
            $maxRelOk = abs($maxRelVal - $expectedMaxRel) <= $tol;

            if (!$sumNormOk || !$maxRelOk) {
                $criteriaErrors[] = [
                    'criteria_id' => $cid,
                    'sum_raw_peer' => round($den, 6),
                    'sum_norm' => round($sumNorm, 6),
                    'expected_sum_norm' => $expectedSumNorm,
                    'max_norm' => round($maxNorm, 6),
                    'min_norm' => round($minNorm, 6),
                    'max_rel' => $maxRel !== null ? round($maxRel, 6) : null,
                    'expected_max_rel' => $expectedMaxRel,
                ];
            }
        }

        if (empty($criteriaErrors) && empty($userTotalErrors)) {
            $this->info('OK: Semua cek sinkronisasi lolos.');
            $this->line('Grup: unit_id=' . $unitId . ', profession_id=' . ($professionArg === null ? 'NULL' : (string) $professionId) . ', period_id=' . $periodId);
            $this->line('Users: ' . count($userIds) . ' | Criteria: ' . count($criteriaIds));
            return self::SUCCESS;
        }

        $this->error('TIDAK SINKRON: ada anomali.');
        $this->line('Grup: unit_id=' . $unitId . ', profession_id=' . ($professionArg === null ? 'NULL' : (string) $professionId) . ', period_id=' . $periodId);

        if (!empty($criteriaErrors)) {
            $this->warn('Breakdown per-kriteria:');
            $this->table(
                ['criteria_id', 'sum_raw_peer', 'sum_norm', 'expected_sum_norm', 'max_norm', 'min_norm', 'max_rel', 'expected_max_rel'],
                $criteriaErrors
            );
        }

        if (!empty($userTotalErrors)) {
            $this->warn('Breakdown total WSM per-user (reported vs recomputed):');
            $this->table(['user_id', 'reported_total_wsm', 'recomputed_total_wsm', 'sum_weight'], $userTotalErrors);
        }

        return self::FAILURE;
    }
}
