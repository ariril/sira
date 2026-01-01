<?php

// One-off debug script to inspect November periods and rater weight readiness.
// Run: php tools/debug_rater_weights_november.php

require __DIR__ . '/../vendor/autoload.php';

$app = require __DIR__ . '/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\DB;

$report = [];

function add(string $label, $data): void {
    global $report;
    $report[] = ['label' => $label, 'data' => $data];
}

$periods = DB::table('assessment_periods')
    ->orderByDesc('start_date')
    ->limit(24)
    ->get(['id','name','start_date','end_date','status']);

add('assessment_periods (latest 24)', $periods);

$novPeriods = DB::table('assessment_periods')
    ->whereMonth('start_date', 11)
    ->orWhere('name', 'like', '%Nov%')
    ->orWhere('name', 'like', '%November%')
    ->orderByDesc('start_date')
    ->limit(12)
    ->get(['id','name','start_date','end_date','status']);

add('candidate November periods', $novPeriods);

$periodIds = $novPeriods->pluck('id')->map(fn($v) => (int)$v)->filter()->values()->all();

foreach ($periodIds as $pid) {
    $ucw = DB::table('unit_criteria_weights')
        ->where('assessment_period_id', $pid)
        ->selectRaw('status, COUNT(*) as cnt')
        ->groupBy('status')
        ->orderBy('status')
        ->get();

    add("unit_criteria_weights by status (period_id={$pid})", $ucw);

    $ucwActive360 = DB::table('unit_criteria_weights as ucw')
        ->join('performance_criterias as pc', 'pc.id', '=', 'ucw.performance_criteria_id')
        ->where('ucw.assessment_period_id', $pid)
        ->where('ucw.status', 'active')
        ->where('pc.is_360', 1)
        ->selectRaw('COUNT(*) as cnt')
        ->value('cnt');

    add("unit_criteria_weights active & is_360 count (period_id={$pid})", (int)$ucwActive360);

    $rwCounts = DB::table('unit_rater_weights')
        ->where('assessment_period_id', $pid)
        ->selectRaw('status, COUNT(*) as cnt')
        ->groupBy('status')
        ->orderBy('status')
        ->get();

    add("unit_rater_weights by status (period_id={$pid})", $rwCounts);

    // Group completeness stats
    // MySQL/MariaDB doesn't allow selecting multiple aggregates via value(); do a get().
    $groupStats = DB::table('unit_rater_weights')
        ->where('assessment_period_id', $pid)
        ->selectRaw('COUNT(*) as row_cnt')
        ->selectRaw('COUNT(DISTINCT CONCAT(unit_id,":",performance_criteria_id,":",assessee_profession_id)) as group_cnt')
        ->selectRaw('SUM(weight IS NULL) as null_cnt')
        ->first();

    add("unit_rater_weights aggregate (period_id={$pid})", $groupStats);

    // MariaDB returns one row per group; count them from a subquery instead.
    $allNullGroupCount = DB::table('unit_rater_weights as rw')
        ->where('rw.assessment_period_id', $pid)
        ->selectRaw('rw.unit_id, rw.performance_criteria_id, rw.assessee_profession_id')
        ->groupBy('rw.unit_id','rw.performance_criteria_id','rw.assessee_profession_id')
        ->havingRaw('SUM(rw.weight IS NULL) = COUNT(*)')
        ->get()
        ->count();

    add("unit_rater_weights groups ALL NULL (period_id={$pid})", $allNullGroupCount);

    $sampleGroup = DB::table('unit_rater_weights as rw')
        ->join('performance_criterias as pc', 'pc.id', '=', 'rw.performance_criteria_id')
        ->join('professions as p', 'p.id', '=', 'rw.assessee_profession_id')
        ->where('rw.assessment_period_id', $pid)
        ->orderByDesc('rw.id')
        ->limit(8)
        ->get(['rw.id','rw.unit_id','pc.name as criteria','p.name as assessee_profession','rw.assessor_type','rw.assessor_level','rw.weight','rw.status']);

    add("sample unit_rater_weights rows (period_id={$pid})", $sampleGroup);
}

$outPath = __DIR__ . '/../storage/logs/debug_rater_weights_november.json';
file_put_contents($outPath, json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
echo "WROTE: {$outPath}\n";
