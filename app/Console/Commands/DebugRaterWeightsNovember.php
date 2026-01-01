<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class DebugRaterWeightsNovember extends Command
{
    protected $signature = 'debug:rater-weights-november {--period-id= : Force assessment_period_id}';

    protected $description = 'Debug helper: inspect November periods and rater-weight readiness (unit_criteria_weights + unit_rater_weights)';

    public function handle(): int
    {
        $forced = (int) ($this->option('period-id') ?: 0);

        $periods = DB::table('assessment_periods')
            ->orderByDesc('start_date')
            ->limit(24)
            ->get(['id', 'name', 'start_date', 'end_date', 'status']);

        $this->info('assessment_periods (latest 24)');
        $this->line(json_encode($periods, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

        $novPeriodsQ = DB::table('assessment_periods')
            ->orderByDesc('start_date');

        if ($forced > 0) {
            $novPeriodsQ->where('id', $forced);
        } else {
            $novPeriodsQ
                ->whereMonth('start_date', 11)
                ->orWhere('name', 'like', '%Nov%')
                ->orWhere('name', 'like', '%November%');
        }

        $novPeriods = $novPeriodsQ->limit(12)->get(['id', 'name', 'start_date', 'end_date', 'status']);

        $this->info('candidate periods');
        $this->line(json_encode($novPeriods, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

        $periodIds = $novPeriods->pluck('id')->map(fn ($v) => (int) $v)->filter()->values()->all();

        foreach ($periodIds as $pid) {
            $this->newLine();
            $this->info("period_id={$pid}");

            $ucwByStatus = DB::table('unit_criteria_weights')
                ->where('assessment_period_id', $pid)
                ->selectRaw('status, COUNT(*) as cnt')
                ->groupBy('status')
                ->orderBy('status')
                ->get();

            $this->line('unit_criteria_weights by status: ' . json_encode($ucwByStatus, JSON_UNESCAPED_UNICODE));

            $ucwActive360 = (int) DB::table('unit_criteria_weights as ucw')
                ->join('performance_criterias as pc', 'pc.id', '=', 'ucw.performance_criteria_id')
                ->where('ucw.assessment_period_id', $pid)
                ->where('ucw.status', 'active')
                ->where('pc.is_360', 1)
                ->count();

            $this->line('unit_criteria_weights active & is_360 count: ' . $ucwActive360);

            $rwByStatus = DB::table('unit_rater_weights')
                ->where('assessment_period_id', $pid)
                ->selectRaw('status, COUNT(*) as cnt')
                ->groupBy('status')
                ->orderBy('status')
                ->get();

            $this->line('unit_rater_weights by status: ' . json_encode($rwByStatus, JSON_UNESCAPED_UNICODE));

            $rwAgg = DB::table('unit_rater_weights')
                ->where('assessment_period_id', $pid)
                ->selectRaw('COUNT(*) as row_cnt')
                ->selectRaw('COUNT(DISTINCT CONCAT(unit_id,":",performance_criteria_id,":",assessee_profession_id)) as group_cnt')
                ->selectRaw('SUM(weight IS NULL) as null_cnt')
                ->first();

            $this->line('unit_rater_weights aggregate: ' . json_encode($rwAgg, JSON_UNESCAPED_UNICODE));

            $allNullGroupCount = DB::table('unit_rater_weights as rw')
                ->where('rw.assessment_period_id', $pid)
                ->selectRaw('rw.unit_id, rw.performance_criteria_id, rw.assessee_profession_id')
                ->groupBy('rw.unit_id', 'rw.performance_criteria_id', 'rw.assessee_profession_id')
                ->havingRaw('SUM(rw.weight IS NULL) = COUNT(*)')
                ->get()
                ->count();

            $this->line('groups ALL NULL: ' . $allNullGroupCount);
        }

        return self::SUCCESS;
    }
}
