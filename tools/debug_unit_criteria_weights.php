<?php

require __DIR__ . '/../vendor/autoload.php';

$app = require __DIR__ . '/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$periodId = (int) ($argv[1] ?? 0);
$unitId = (int) ($argv[2] ?? 0);

if ($periodId <= 0 || $unitId <= 0) {
    fwrite(STDERR, "Usage: php tools/debug_unit_criteria_weights.php <period_id> <unit_id>\n");
    exit(2);
}

$rows = Illuminate\Support\Facades\DB::table('unit_criteria_weights as ucw')
    ->join('performance_criterias as pc', 'pc.id', '=', 'ucw.performance_criteria_id')
    ->where('ucw.assessment_period_id', $periodId)
    ->where('ucw.unit_id', $unitId)
    ->orderBy('ucw.performance_criteria_id')
    ->orderBy('ucw.status')
    ->get(['ucw.performance_criteria_id','pc.name','ucw.weight','ucw.status']);

$counts = [];
foreach ($rows as $r) {
    $st = (string) ($r->status ?? '');
    $counts[$st] = ($counts[$st] ?? 0) + 1;
}
ksort($counts);

$outRows = [];
foreach ($rows as $r) {
    $outRows[] = [
        'criteria_id' => (int) $r->performance_criteria_id,
        'criteria' => (string) ($r->name ?? ''),
        'weight' => (float) $r->weight,
        'status' => (string) $r->status,
    ];
}

echo json_encode([
    'period_id' => $periodId,
    'unit_id' => $unitId,
    'counts_by_status' => $counts,
    'rows' => $outRows,
], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . PHP_EOL;
