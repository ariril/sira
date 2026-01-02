<?php

// One-off debug script to inspect rater weights history visibility.
// Run: php tools/debug_rater_weights_history.php

require __DIR__ . '/../vendor/autoload.php';

$app = require __DIR__ . '/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\DB;

$report = [];

function add(string $label, $data): void {
    global $report;
    $report[] = ['label' => $label, 'data' => $data];
}

$activePeriod = DB::table('assessment_periods')
    ->where('status', 'active')
    ->orderByDesc('start_date')
    ->first(['id', 'name', 'start_date', 'end_date', 'status']);

add('active_period', $activePeriod);

// Snapshot: unit_criteria_weights counts by period/status for 360 criterias
if (DB::getSchemaBuilder()->hasTable('unit_criteria_weights') && DB::getSchemaBuilder()->hasTable('performance_criterias')) {
    $ucw360 = DB::table('unit_criteria_weights as ucw')
        ->join('performance_criterias as pc', 'pc.id', '=', 'ucw.performance_criteria_id')
        ->where('pc.is_360', 1)
        ->selectRaw('ucw.assessment_period_id, ucw.unit_id, ucw.status, COUNT(*) as cnt')
        ->groupBy('ucw.assessment_period_id', 'ucw.unit_id', 'ucw.status')
        ->orderByDesc('ucw.assessment_period_id')
        ->orderBy('ucw.unit_id')
        ->orderBy('ucw.status')
        ->limit(200)
        ->get();

    add('unit_criteria_weights (is_360=1) grouped counts (top 200)', $ucw360);
}

// Snapshot counts by period+unit+status (top 200 combos)
$rwCounts = DB::table('unit_rater_weights')
    ->selectRaw('assessment_period_id, unit_id, status, COUNT(*) as cnt')
    ->groupBy('assessment_period_id', 'unit_id', 'status')
    ->orderByDesc('assessment_period_id')
    ->orderBy('unit_id')
    ->orderBy('status')
    ->limit(200)
    ->get();

add('unit_rater_weights grouped counts (top 200)', $rwCounts);

// If there is an active period, show how many rows exist outside it (top 50 units)
if (!empty($activePeriod?->id)) {
    $outsideActive = DB::table('unit_rater_weights')
        ->where('assessment_period_id', '!=', (int) $activePeriod->id)
        ->selectRaw('unit_id, COUNT(*) as cnt')
        ->groupBy('unit_id')
        ->orderByDesc('cnt')
        ->limit(50)
        ->get();

    add('rows outside active period (top 50 units)', $outsideActive);
}

$outPath = __DIR__ . '/../storage/logs/debug_rater_weights_history.json';
file_put_contents($outPath, json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

echo "WROTE: {$outPath}\n";
