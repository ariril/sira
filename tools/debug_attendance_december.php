<?php

// One-off debug script to inspect seeded December attendance schedule.
// Run: php tools/debug_attendance_december.php

require __DIR__ . '/../vendor/autoload.php';

$app = require __DIR__ . '/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\DB;

$report = [];

function add(string $label, $data): void {
    global $report;
    $report[] = ['label' => $label, 'data' => $data];
}

$period = DB::table('assessment_periods')->where('name', 'December 2025')->first(['id','name','start_date','end_date','status']);
add('period', $period);

if (!$period) {
    $outPath = __DIR__ . '/../storage/logs/debug_attendance_december.json';
    file_put_contents($outPath, json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    echo "WROTE: {$outPath}\n";
    exit(0);
}

$pid = (int) $period->id;

$sample = DB::table('attendances as a')
    ->join('attendance_import_batches as b', 'b.id', '=', 'a.import_batch_id')
    ->join('users as u', 'u.id', '=', 'a.user_id')
    ->where('b.assessment_period_id', $pid)
    ->orderByDesc('a.attendance_date')
    ->orderBy('u.email')
    ->limit(20)
    ->get([
        'a.attendance_date',
        'u.email',
        'a.scheduled_in',
        'a.scheduled_out',
        'a.check_in',
        'a.check_out',
        'a.late_minutes',
        'a.work_duration_minutes',
        'a.overtime_shift',
        'a.overtime_end',
        'a.attendance_status',
    ]);

add('sample attendances (via import batch join)', $sample);

// Quick aggregate sanity: expected schedule times.
$agg = DB::table('attendances as a')
    ->join('attendance_import_batches as b', 'b.id', '=', 'a.import_batch_id')
    ->where('b.assessment_period_id', $pid)
    ->selectRaw('COUNT(*) as row_cnt')
    ->selectRaw('SUM(a.scheduled_in = "07:30:00") as scheduled_in_0730_cnt')
    ->selectRaw('SUM(a.scheduled_out = "15:00:00") as scheduled_out_1500_cnt')
    ->first();

add('aggregate schedule checks', $agg);

$outPath = __DIR__ . '/../storage/logs/debug_attendance_december.json';
file_put_contents($outPath, json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
echo "WROTE: {$outPath}\n";
