<?php

namespace App\Console\Commands;

use App\Models\AssessmentPeriod;
use App\Models\Remuneration;
use App\Models\UnitRemunerationAllocation as Allocation;
use App\Models\User;
use App\Support\AttachedRemunerationCalculator;
use App\Support\ProportionalAllocator;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class DebugRemunerationPeriod extends Command
{
    protected $signature = 'debug:remuneration-period {period_id? : assessment_periods.id} {--unit_id=} {--profession_id=} {--limit=15}';

    protected $description = 'Audit remunerasi: alokasi, total WSM grup, porsi, dan nominal hasil (sesuai Excel).';

    private function resolveDistributionMode(AssessmentPeriod $period, int $unitId, ?int $professionId): string
    {
        if ($period->isFrozen()) {
            $snap = $this->tryResolveDistributionModeFromSnapshot($period, $unitId, $professionId);
            if ($snap !== null) {
                return $snap;
            }
        }

        $periodId = (int) $period->id;
        $bases = DB::table('unit_criteria_weights as ucw')
            ->join('performance_criterias as pc', 'pc.id', '=', 'ucw.performance_criteria_id')
            ->where('ucw.assessment_period_id', $periodId)
            ->where('ucw.unit_id', $unitId)
            ->where('ucw.status', 'active')
            ->where('pc.is_active', 1)
            ->distinct()
            ->pluck('pc.normalization_basis')
            ->map(fn ($v) => (string) ($v ?? 'total_unit'))
            ->filter(fn ($v) => $v !== '')
            ->values()
            ->all();

        return $this->determineDistributionModeFromBases($bases);
    }

    /**
     * @param array<int,string> $bases
     */
    private function determineDistributionModeFromBases(array $bases): string
    {
        foreach ($bases as $b) {
            if (in_array((string) $b, ['average_unit', 'max_unit', 'custom_target'], true)) {
                return 'attached_per_employee';
            }
        }

        return 'total_unit_proportional';
    }

    private function tryResolveDistributionModeFromSnapshot(AssessmentPeriod $period, int $unitId, ?int $professionId): ?string
    {
        if (!$period->isFrozen()) {
            return null;
        }

        if (!Schema::hasTable('assessment_period_user_membership_snapshots')) {
            return null;
        }

        if (!Schema::hasTable('performance_assessment_snapshots')) {
            return null;
        }

        $periodId = (int) $period->id;
        $q = DB::table('assessment_period_user_membership_snapshots')
            ->where('assessment_period_id', $periodId)
            ->where('unit_id', (int) $unitId);

        if ($professionId !== null) {
            $q->where('profession_id', (int) $professionId);
        }

        $sampleUserId = $q->orderBy('user_id')->value('user_id');
        if (!$sampleUserId) {
            return null;
        }

        $payload = DB::table('performance_assessment_snapshots')
            ->where('assessment_period_id', $periodId)
            ->where('user_id', (int) $sampleUserId)
            ->value('payload');

        if ($payload === null) {
            return null;
        }

        $decoded = is_string($payload) ? json_decode($payload, true) : (array) $payload;
        if (!is_array($decoded)) {
            return null;
        }

        $calc = (array) ($decoded['calc'] ?? []);
        $bases = array_values((array) ($calc['basis_by_criteria'] ?? []));
        $bases = array_values(array_filter(array_map(fn ($v) => (string) ($v ?? ''), $bases), fn ($v) => $v !== ''));
        if (empty($bases)) {
            return null;
        }

        return $this->determineDistributionModeFromBases($bases);
    }

    /**
     * @return array<int>
     */
    private function resolveRecipientUserIds(AssessmentPeriod $period, int $unitId, ?int $professionId): array
    {
        $periodId = (int) $period->id;
        if ($period->isFrozen() && Schema::hasTable('assessment_period_user_membership_snapshots')) {
            $q = DB::table('assessment_period_user_membership_snapshots')
                ->where('assessment_period_id', $periodId)
                ->where('unit_id', (int) $unitId);

            if ($professionId !== null) {
                $q->where('profession_id', (int) $professionId);
            }

            return $q->pluck('user_id')->map(fn ($v) => (int) $v)->all();
        }

        $usersQ = User::query()
            ->role(User::ROLE_PEGAWAI_MEDIS)
            ->where('unit_id', (int) $unitId);

        if ($professionId !== null) {
            $usersQ->where('profession_id', (int) $professionId);
        }

        return $usersQ->pluck('id')->map(fn ($v) => (int) $v)->all();
    }

    /**
     * @param array<int> $userIds
     * @return array{relative: array<int,?float>, value: array<int,?float>}|null
     */
    private function tryLoadSnapshotWsmScores(int $periodId, array $userIds): ?array
    {
        if ($periodId <= 0 || empty($userIds)) {
            return null;
        }

        if (!Schema::hasTable('performance_assessment_snapshots')) {
            return null;
        }

        $rows = DB::table('performance_assessment_snapshots')
            ->where('assessment_period_id', (int) $periodId)
            ->whereIn('user_id', array_map('intval', $userIds))
            ->get(['user_id', 'payload']);

        if ($rows->isEmpty()) {
            return null;
        }

        $relative = [];
        $value = [];
        foreach ($rows as $r) {
            $uid = (int) $r->user_id;
            $payload = is_string($r->payload) ? json_decode($r->payload, true) : (array) $r->payload;
            if (!is_array($payload)) {
                continue;
            }

            $calc = (array) ($payload['calc'] ?? []);
            $rel = $calc['total_wsm_relative'] ?? null;
            $val = $calc['total_wsm_value'] ?? null;

            if ($rel === null) {
                $rel = $calc['user']['total_wsm_relative'] ?? ($calc['user']['total_wsm'] ?? null);
            }
            if ($val === null) {
                $val = $calc['user']['total_wsm_value'] ?? null;
            }

            $relative[$uid] = $rel === null ? null : (float) $rel;
            $value[$uid] = $val === null ? null : (float) $val;
        }

        foreach ($userIds as $uid) {
            $uid = (int) $uid;
            if (!array_key_exists($uid, $relative) || !array_key_exists($uid, $value)) {
                return null;
            }
        }

        return ['relative' => $relative, 'value' => $value];
    }

    public function handle(): int
    {
        $periodId = (int) ($this->argument('period_id') ?? 0);
        if ($periodId <= 0) {
            $periods = AssessmentPeriod::query()
                ->orderByDesc('start_date')
                ->limit(15)
                ->get(['id', 'name', 'status', 'start_date', 'end_date']);

            $this->info('Pilih period_id. Contoh: php artisan debug:remuneration-period 12');
            $this->table(['id', 'name', 'status', 'start', 'end'], $periods->map(function ($p) {
                return [
                    'id' => $p->id,
                    'name' => $p->name,
                    'status' => $p->status,
                    'start' => $p->start_date,
                    'end' => $p->end_date,
                ];
            })->all());
            return self::SUCCESS;
        }

        $unitId = (int) ($this->option('unit_id') ?: 0);
        $professionId = (int) ($this->option('profession_id') ?: 0);
        $limit = (int) ($this->option('limit') ?: 15);
        $limit = max(1, min(200, $limit));

        $period = AssessmentPeriod::query()->find($periodId);
        if (!$period) {
            $this->error('Period tidak ditemukan.');
            return self::FAILURE;
        }

        $allocQ = Allocation::query()
            ->with(['unit:id,name', 'profession:id,name'])
            ->where('assessment_period_id', $periodId)
            ->whereNotNull('published_at');

        if ($unitId) {
            $allocQ->where('unit_id', $unitId);
        }
        if ($professionId) {
            $allocQ->where('profession_id', $professionId);
        }

        $allocations = $allocQ->orderBy('unit_id')->orderBy('profession_id')->get();
        if ($allocations->isEmpty()) {
            $this->warn('Tidak ada alokasi published untuk periode ini (atau filter terlalu sempit).');
            return self::SUCCESS;
        }

        $rows = [];

        foreach ($allocations as $alloc) {
            $userIds = $this->resolveRecipientUserIds(
                $period,
                (int) $alloc->unit_id,
                $alloc->profession_id === null ? null : (int) $alloc->profession_id
            );
            $userCount = count($userIds);

            if ($userCount === 0) {
                $rows[] = [
                    'unit' => $alloc->unit?->name ?? ('Unit#' . $alloc->unit_id),
                    'profession' => $alloc->profession?->name ?? ($alloc->profession_id ? ('Profesi#' . $alloc->profession_id) : 'Semua'),
                    'alloc' => (float) $alloc->amount,
                    'users' => 0,
                    'wsm_total' => 0,
                    'wsm_null' => 0,
                    'wsm_min' => null,
                    'wsm_max' => null,
                    'sum_expected' => 0,
                    'sum_actual' => (float) Remuneration::query()->where('assessment_period_id', $periodId)->whereIn('user_id', [0])->sum('amount'),
                    'diff_users' => 0,
                ];
                continue;
            }

            $wsmRelative = [];
            $wsmValue = [];

            $snapWsm = $period->isFrozen() ? $this->tryLoadSnapshotWsmScores($periodId, $userIds) : null;
            if ($snapWsm !== null) {
                $wsmRelative = (array) ($snapWsm['relative'] ?? []);
                $wsmValue = (array) ($snapWsm['value'] ?? []);
            } else {
                $wsmRows = DB::table('performance_assessments')
                    ->where('assessment_period_id', $periodId)
                    ->whereIn('user_id', $userIds)
                    ->get(['user_id', 'total_wsm_score', 'total_wsm_value_score']);

                foreach ($wsmRows as $r) {
                    $uid = (int) $r->user_id;
                    $wsmRelative[$uid] = $r->total_wsm_score === null ? null : (float) $r->total_wsm_score;
                    $wsmValue[$uid] = $r->total_wsm_value_score === null ? null : (float) $r->total_wsm_value_score;
                }
            }

            $weights = [];
            $nullCount = 0;
            $minWsm = null;
            $maxWsm = null;
            $groupTotal = 0.0;

            foreach ($userIds as $uid) {
                $val = array_key_exists($uid, $wsmRelative) ? $wsmRelative[$uid] : null;
                if ($val === null) {
                    $nullCount++;
                    $w = 0.0;
                } else {
                    $w = (float) $val;
                }
                $weights[$uid] = $w;
                $groupTotal += $w;

                $minWsm = $minWsm === null ? $w : min($minWsm, $w);
                $maxWsm = $maxWsm === null ? $w : max($maxWsm, $w);
            }

            $expected = [];
            $mode = $this->resolveDistributionMode(
                $period,
                (int) $alloc->unit_id,
                $alloc->profession_id === null ? null : (int) $alloc->profession_id
            );
            if ($mode === 'total_unit_proportional') {
                if ($groupTotal > 0) {
                    $expected = ProportionalAllocator::allocate((float) $alloc->amount, $weights);
                }
            } else {
                $payoutPct = [];
                foreach ($userIds as $uid) {
                    $payoutPct[$uid] = (float) (($wsmValue[$uid] ?? null) ?? 0.0);
                }
                $calc = AttachedRemunerationCalculator::calculate((float) $alloc->amount, $payoutPct, 2);
                $expected = (array) ($calc['amounts'] ?? []);
            }

            $actualByUser = Remuneration::query()
                ->where('assessment_period_id', $periodId)
                ->whereIn('user_id', $userIds)
                ->pluck('amount', 'user_id')
                ->map(fn ($v) => $v === null ? null : (float) $v)
                ->all();

            $sumExpected = 0.0;
            $sumActual = 0.0;
            $diffUsers = 0;

            foreach ($userIds as $uid) {
                $e = (float) ($expected[$uid] ?? 0.0);
                $a = $actualByUser[$uid] ?? null;

                $sumExpected += $e;
                $sumActual += (float) ($a ?? 0.0);

                if ($a === null || abs(((float) $a) - $e) >= 0.01) {
                    $diffUsers++;
                }
            }

            $rows[] = [
                'unit' => $alloc->unit?->name ?? ('Unit#' . $alloc->unit_id),
                'profession' => $alloc->profession?->name ?? ($alloc->profession_id ? ('Profesi#' . $alloc->profession_id) : 'Semua'),
                'alloc' => (float) $alloc->amount,
                'users' => $userCount,
                'wsm_total' => round($groupTotal, 2),
                'wsm_null' => $nullCount,
                'wsm_min' => $minWsm === null ? null : round((float) $minWsm, 2),
                'wsm_max' => $maxWsm === null ? null : round((float) $maxWsm, 2),
                'sum_expected' => round($sumExpected, 2),
                'sum_actual' => round($sumActual, 2),
                'diff_users' => $diffUsers,
            ];
        }

        $this->info('Audit Remunerasi - ' . ($period->name ?? ('Period#' . $periodId)));
        $this->table(
            ['Unit', 'Profesi', 'Alokasi', 'Users', 'WSM Total', 'WSM NULL', 'WSM Min', 'WSM Max', 'Sum Expected', 'Sum Actual', 'Diff Users'],
            collect($rows)->map(function ($r) {
                return [
                    $r['unit'],
                    $r['profession'],
                    number_format((float) $r['alloc'], 2, '.', ''),
                    $r['users'],
                    number_format((float) $r['wsm_total'], 2, '.', ''),
                    $r['wsm_null'],
                    $r['wsm_min'] === null ? '-' : number_format((float) $r['wsm_min'], 2, '.', ''),
                    $r['wsm_max'] === null ? '-' : number_format((float) $r['wsm_max'], 2, '.', ''),
                    number_format((float) $r['sum_expected'], 2, '.', ''),
                    number_format((float) $r['sum_actual'], 2, '.', ''),
                    $r['diff_users'],
                ];
            })->take($limit)->all()
        );

        $hasBad = collect($rows)->contains(fn ($r) => (int) $r['wsm_null'] > 0);
        if ($hasBad) {
            $this->warn('Ada grup dengan WSM NULL > 0. Itu akan membuat perhitungan remunerasi tidak konsisten.');
        }

        $this->line('Tips: jalankan ulang kalkulasi admin setelah WSM siap: Admin RS → Remunerasi → Kalkulasi → Run.');
        return self::SUCCESS;
    }
}
