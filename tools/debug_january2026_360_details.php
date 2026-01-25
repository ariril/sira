<?php

// Debug helper to inspect 360 detail rows for January 2026.
// Run: php tools/debug_january2026_360_details.php [assessor_user_id]

require __DIR__ . '/../vendor/autoload.php';

$app = require __DIR__ . '/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\DB;

$assessorId = (int) ($argv[1] ?? 0);

$period = DB::table('assessment_periods')
    ->whereIn('name', ['Januari 2026', 'January 2026'])
    ->orderByDesc('start_date')
    ->first(['id','name','start_date','end_date','status']);

if (!$period) {
    echo "Period Januari 2026 not found" . PHP_EOL;
    exit(1);
}

$pid = (int) $period->id;

echo "Period: {$period->name} (id={$pid}, status={$period->status})" . PHP_EOL;

$q = DB::table('multi_rater_assessment_details as d')
    ->join('multi_rater_assessments as mra', 'mra.id', '=', 'd.multi_rater_assessment_id')
    ->join('users as assessee', 'assessee.id', '=', 'mra.assessee_id')
    ->join('users as assessor', 'assessor.id', '=', 'mra.assessor_id')
    ->leftJoin('performance_criterias as pc', 'pc.id', '=', 'd.performance_criteria_id')
    ->where('mra.assessment_period_id', $pid)
    ->where('pc.is_360', 1);

if ($assessorId > 0) {
    $q->where('mra.assessor_id', $assessorId);
}

$total = (clone $q)->count();

echo "Total 360 detail rows" . ($assessorId > 0 ? " for assessor_id={$assessorId}" : "") . ": {$total}" . PHP_EOL;

$sample = (clone $q)
    ->orderByDesc('d.updated_at')
    ->limit(30)
    ->get([
        'd.id as detail_id',
        'mra.id as assessment_id',
        'mra.status as assessment_status',
        'mra.assessor_type',
        'mra.assessor_level',
        'mra.assessor_profession_id',
        'assessor.name as assessor_name',
        'assessee.name as assessee_name',
        'pc.name as criteria',
        'd.score',
        'd.updated_at',
    ]);

foreach ($sample as $r) {
    $lvl = $r->assessor_level === null ? 'null' : (string) $r->assessor_level;
    $prof = $r->assessor_profession_id === null ? 'null' : (string) $r->assessor_profession_id;
    echo "- detail_id={$r->detail_id} assessment_id={$r->assessment_id} status={$r->assessment_status} type={$r->assessor_type} level={$lvl} assessor_profession_id={$prof}\n";
    echo "  assessor={$r->assessor_name} -> assessee={$r->assessee_name}\n";
    echo "  criteria={$r->criteria} score={$r->score} updated_at={$r->updated_at}\n";
}
