<?php

// Debug helper: inspect 360 window rows for January 2026.
// Run: php tools/debug_january2026_360_window.php

require __DIR__ . '/../vendor/autoload.php';

$app = require __DIR__ . '/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\DB;

$period = DB::table('assessment_periods')
    ->whereIn('name', ['Januari 2026', 'January 2026'])
    ->orderByDesc('start_date')
    ->first(['id','name','start_date','end_date','status']);

if (!$period) {
    echo "Period Januari 2026 not found" . PHP_EOL;
    exit(1);
}

$pid = (int) $period->id;

$windows = DB::table('assessment_360_windows')
    ->where('assessment_period_id', $pid)
    ->orderBy('id')
    ->get(['id','start_date','end_date','is_active','opened_by','created_at','updated_at']);

echo "Period: {$period->name} (id={$pid}, status={$period->status})" . PHP_EOL;
echo "Window count: {$windows->count()}" . PHP_EOL;
foreach ($windows as $w) {
    echo "- id={$w->id} start={$w->start_date} end={$w->end_date} is_active=" . ((int) $w->is_active) . " opened_by=" . ($w->opened_by ?? 'null') . " created_at={$w->created_at}" . PHP_EOL;
}
