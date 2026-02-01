<?php

require __DIR__ . '/../vendor/autoload.php';

$app = require __DIR__ . '/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\DB;

$pid = (int)($argv[1] ?? 4);

$rows = DB::table('performance_assessments')
    ->where('assessment_period_id', $pid)
    ->selectRaw('COUNT(*) as total, SUM(CASE WHEN total_wsm_score IS NULL THEN 1 ELSE 0 END) as null_wsm, SUM(CASE WHEN total_wsm_value_score IS NULL THEN 1 ELSE 0 END) as null_val')
    ->first();

echo "performance_assessments: " . json_encode($rows, JSON_UNESCAPED_UNICODE) . "\n";

$weights = DB::table('unit_criteria_weights')
    ->where('assessment_period_id', $pid)
    ->selectRaw('status, was_active_before, COUNT(*) as c')
    ->groupBy('status', 'was_active_before')
    ->orderBy('status')
    ->orderBy('was_active_before')
    ->get();

echo "unit_criteria_weights groups:\n";
foreach ($weights as $w) {
    echo "- status={$w->status} was_active_before={$w->was_active_before} count={$w->c}\n";
}
