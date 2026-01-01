<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class AuditUnitCriteriaWeights extends Command
{
    protected $signature = 'weights:audit {--period_id=} {--unit_id=}';

    protected $description = 'Audit bobot aktif unit_criteria_weights: duplikasi dan ΣBobotAktif per unit+periode';

    public function handle(): int
    {
        if (!Schema::hasTable('unit_criteria_weights')) {
            $this->error('Table unit_criteria_weights not found');
            return 1;
        }

        $periodId = $this->option('period_id') !== null ? (int) $this->option('period_id') : null;
        $unitId = $this->option('unit_id') !== null ? (int) $this->option('unit_id') : null;

        // 1) Duplikasi ACTIVE untuk (unit, period, criteria) -> seharusnya tidak ada.
        $dupQ = DB::table('unit_criteria_weights')
            ->selectRaw('unit_id, assessment_period_id, performance_criteria_id, COUNT(*) as cnt')
            ->where('status', 'active')
            ->when($periodId, fn($q) => $q->where('assessment_period_id', $periodId))
            ->when($unitId, fn($q) => $q->where('unit_id', $unitId))
            ->groupBy('unit_id', 'assessment_period_id', 'performance_criteria_id')
            ->havingRaw('COUNT(*) > 1');

        $dups = $dupQ->get();
        if ($dups->isEmpty()) {
            $this->info('OK: tidak ada duplikasi bobot ACTIVE per (unit, period, criteria).');
        } else {
            $this->warn('Ditemukan duplikasi bobot ACTIVE (ini harus dibersihkan):');
            foreach ($dups as $r) {
                $this->line("- unit={$r->unit_id} period={$r->assessment_period_id} criteria={$r->performance_criteria_id} cnt={$r->cnt}");
            }
        }

        // 1b) Duplikasi lintas status untuk (unit, period, criteria) -> audit workflow.
        $dupAnyQ = DB::table('unit_criteria_weights')
            ->selectRaw('unit_id, assessment_period_id, performance_criteria_id, COUNT(*) as cnt')
            ->when($periodId, fn($q) => $q->where('assessment_period_id', $periodId))
            ->when($unitId, fn($q) => $q->where('unit_id', $unitId))
            ->groupBy('unit_id', 'assessment_period_id', 'performance_criteria_id')
            ->havingRaw('COUNT(*) > 1');

        $dupsAny = $dupAnyQ->get();
        if ($dupsAny->isEmpty()) {
            $this->info('OK: tidak ada bobot ganda lintas status per (unit, period, criteria).');
        } else {
            $this->warn('Ditemukan bobot ganda lintas status (cek workflow/status):');
            foreach ($dupsAny as $r) {
                $this->line("- unit={$r->unit_id} period={$r->assessment_period_id} criteria={$r->performance_criteria_id} cnt={$r->cnt}");
            }
        }

        // 2) ΣBobotAktif per (unit, period)
        $sumQ = DB::table('unit_criteria_weights')
            ->selectRaw('unit_id, assessment_period_id, SUM(weight) as sum_weight, COUNT(*) as cnt')
            ->where('status', 'active')
            ->when($periodId, fn($q) => $q->where('assessment_period_id', $periodId))
            ->when($unitId, fn($q) => $q->where('unit_id', $unitId))
            ->groupBy('unit_id', 'assessment_period_id')
            ->orderBy('assessment_period_id')
            ->orderBy('unit_id');

        $rows = $sumQ->get();
        if ($rows->isEmpty()) {
            $this->info('Tidak ada bobot ACTIVE untuk filter yang diberikan.');
            return $dups->isEmpty() ? 0 : 1;
        }

        $this->line('ΣBobotAktif (status=active):');
        foreach ($rows as $r) {
            $sum = (float) ($r->sum_weight ?? 0.0);
            $flag = (abs($sum - 100.0) > 0.0001) ? ' (≠ 100)' : '';
            $this->line("- unit={$r->unit_id} period={$r->assessment_period_id} sum=" . number_format($sum, 2) . " cnt={$r->cnt}{$flag}");
        }

        return ($dups->isEmpty() && $dupsAny->isEmpty()) ? 0 : 1;
    }
}
