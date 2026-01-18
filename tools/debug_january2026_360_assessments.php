<?php

// Debug helper to verify January 2026 360 seeding.
// Run: php tools/debug_january2026_360_assessments.php

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

echo "Period: {$period->name} (id={$pid})" . PHP_EOL;

$rows = DB::table('multi_rater_assessments as mra')
    ->join('users as u_assessee', 'u_assessee.id', '=', 'mra.assessee_id')
    ->join('users as u_assessor', 'u_assessor.id', '=', 'mra.assessor_id')
    ->where('mra.assessment_period_id', $pid)
    ->select([
        'mra.assessee_id',
        'u_assessee.name as assessee_name',
        'mra.assessor_type',
        'mra.assessor_level',
        'mra.assessor_id',
        'u_assessor.name as assessor_name',
        'mra.status',
    ])
    ->orderBy('mra.assessee_id')
    ->orderBy('mra.assessor_type')
    ->orderBy('mra.assessor_level')
    ->orderBy('mra.assessor_id')
    ->get();

$grouped = [];
foreach ($rows as $r) {
    $key = $r->assessee_id . '|' . $r->assessor_type . '|' . (string)($r->assessor_level ?? '');
    if (!isset($grouped[$key])) {
        $grouped[$key] = [
            'assessee_id' => (int) $r->assessee_id,
            'assessee_name' => (string) $r->assessee_name,
            'assessor_type' => (string) $r->assessor_type,
            'assessor_level' => $r->assessor_level === null ? null : (int) $r->assessor_level,
            'count' => 0,
            'assessors' => [],
        ];
    }
    $grouped[$key]['count']++;
    $grouped[$key]['assessors'][] = [
        'id' => (int) $r->assessor_id,
        'name' => (string) $r->assessor_name,
        'status' => (string) $r->status,
    ];
}

foreach ($grouped as $g) {
    $lvl = $g['assessor_level'] === null ? 'null' : (string)$g['assessor_level'];
    echo PHP_EOL;
    echo "Assessee: {$g['assessee_name']} (id={$g['assessee_id']})" . PHP_EOL;
    echo "  Type: {$g['assessor_type']} | Level: {$lvl} | Count: {$g['count']}" . PHP_EOL;
    foreach ($g['assessors'] as $a) {
        echo "    - {$a['name']} (id={$a['id']}) status={$a['status']}" . PHP_EOL;
    }
}

echo PHP_EOL . "Total assessments: " . $rows->count() . PHP_EOL;
