<?php

// Debug helper: inspect unit_criteria_weights + unit_rater_weights for 360 criterias in Nov/Dec 2025.
// Run: php tools/debug_ucw_nov_dec_360.php

require __DIR__ . '/../vendor/autoload.php';

$app = require __DIR__ . '/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

$periodIds = [2, 3]; // November 2025, December 2025 (per current demo DB)

$unitId = (int) (DB::table('units')->where('slug', 'poliklinik-umum')->value('id') ?? 0);
if ($unitId <= 0) {
    // Fallback: try unit_id=3 used in existing debug outputs
    $unitId = 3;
}

$criteriaIdsByName = DB::table('performance_criterias')
    ->whereIn('name', ['Kedisiplinan (360)', 'Kerjasama (360)'])
    ->pluck('id', 'name')
    ->map(fn ($v) => (int) $v)
    ->all();

$out = [
    'unit_id' => $unitId,
    'criteria_ids' => $criteriaIdsByName,
];

$ucwSelect = ['assessment_period_id', 'unit_id', 'performance_criteria_id', 'weight', 'status'];
if (Schema::hasColumn('unit_criteria_weights', 'was_active_before')) {
    $ucwSelect[] = 'was_active_before';
}

$out['unit_criteria_weights'] = DB::table('unit_criteria_weights')
    ->whereIn('assessment_period_id', $periodIds)
    ->where('unit_id', $unitId)
    ->whereIn('performance_criteria_id', array_values($criteriaIdsByName))
    ->orderBy('assessment_period_id')
    ->orderBy('performance_criteria_id')
    ->get($ucwSelect);

$rwSelect = ['assessment_period_id', 'unit_id', 'performance_criteria_id', 'assessee_profession_id', 'assessor_type', 'assessor_level', 'weight', 'status'];
if (Schema::hasColumn('unit_rater_weights', 'was_active_before')) {
    $rwSelect[] = 'was_active_before';
}

$out['unit_rater_weights_sample'] = DB::table('unit_rater_weights')
    ->whereIn('assessment_period_id', $periodIds)
    ->where('unit_id', $unitId)
    ->whereIn('performance_criteria_id', array_values($criteriaIdsByName))
    ->orderBy('assessment_period_id')
    ->orderBy('performance_criteria_id')
    ->orderBy('assessee_profession_id')
    ->orderBy('assessor_type')
    ->orderByRaw('COALESCE(assessor_level, 999999) ASC')
    ->limit(100)
    ->get($rwSelect);

// Submitted 360 data presence (per period + criteria) for assessees in this unit.
$out['submitted_360_counts'] = [];
foreach ($periodIds as $pid) {
    foreach ($criteriaIdsByName as $cname => $cid) {
        $cnt = (int) DB::table('multi_rater_assessment_details as d')
            ->join('multi_rater_assessments as mra', 'mra.id', '=', 'd.multi_rater_assessment_id')
            ->join('users as u', 'u.id', '=', 'mra.assessee_id')
            ->where('mra.assessment_period_id', (int) $pid)
            ->where('mra.status', 'submitted')
            ->where('d.performance_criteria_id', (int) $cid)
            ->where('u.unit_id', (int) $unitId)
            ->count();
        $out['submitted_360_counts'][] = [
            'assessment_period_id' => (int) $pid,
            'criteria_id' => (int) $cid,
            'criteria_name' => (string) $cname,
            'submitted_detail_rows' => $cnt,
        ];
    }
}

$logPath = __DIR__ . '/../storage/logs/debug_ucw_nov_dec_360.json';
file_put_contents($logPath, json_encode($out, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

echo "WROTE: {$logPath}\n";
