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

$decPeriods = DB::table('assessment_periods')
    ->whereMonth('start_date', 12)
    ->orWhere('name', 'like', '%Dec%')
    ->orWhere('name', 'like', '%December%')
    ->orWhere('name', 'like', '%Des%')
    ->orWhere('name', 'like', '%Desember%')
    ->orderByDesc('start_date')
    ->limit(12)
    ->get(['id','name','start_date','end_date','status']);

add('candidate December periods', $decPeriods);

$periodIds = $novPeriods
    ->merge($decPeriods)
    ->pluck('id')
    ->map(fn ($v) => (int) $v)
    ->filter()
    ->unique()
    ->values()
    ->all();

foreach ($periodIds as $pid) {
    $periodMeta = DB::table('assessment_periods')->where('id', $pid)->first(['id','name','start_date','end_date','status']);
    add("period_meta (period_id={$pid})", $periodMeta);

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

    $invalidSumGroups = DB::table('unit_rater_weights as rw')
        ->where('rw.assessment_period_id', $pid)
        ->selectRaw('rw.unit_id, rw.performance_criteria_id, rw.assessee_profession_id')
        ->groupBy('rw.unit_id','rw.performance_criteria_id','rw.assessee_profession_id')
        ->havingRaw('ABS(SUM(COALESCE(rw.weight, 0)) - 100) > 0.01')
        ->get()
        ->count();

    add("unit_rater_weights groups sum(weight)!=100 (period_id={$pid})", $invalidSumGroups);

    // Check fairness: when types are present, enforce supervisor >= peer >= subordinate and self smallest.
    $typeSums = DB::table('unit_rater_weights as rw')
        ->where('rw.assessment_period_id', $pid)
        ->selectRaw('rw.unit_id, rw.performance_criteria_id, rw.assessee_profession_id')
        ->selectRaw("SUM(CASE WHEN rw.assessor_type='supervisor' THEN 1 ELSE 0 END) as supervisor_cnt")
        ->selectRaw("SUM(CASE WHEN rw.assessor_type='peer' THEN 1 ELSE 0 END) as peer_cnt")
        ->selectRaw("SUM(CASE WHEN rw.assessor_type='subordinate' THEN 1 ELSE 0 END) as subordinate_cnt")
        ->selectRaw("SUM(CASE WHEN rw.assessor_type='self' THEN 1 ELSE 0 END) as self_cnt")
        ->selectRaw("SUM(CASE WHEN rw.assessor_type='supervisor' THEN COALESCE(rw.weight,0) ELSE 0 END) as supervisor_sum")
        ->selectRaw("SUM(CASE WHEN rw.assessor_type='peer' THEN COALESCE(rw.weight,0) ELSE 0 END) as peer_sum")
        ->selectRaw("SUM(CASE WHEN rw.assessor_type='subordinate' THEN COALESCE(rw.weight,0) ELSE 0 END) as subordinate_sum")
        ->selectRaw("SUM(CASE WHEN rw.assessor_type='self' THEN COALESCE(rw.weight,0) ELSE 0 END) as self_sum")
        ->groupBy('rw.unit_id','rw.performance_criteria_id','rw.assessee_profession_id')
        ->get();

    $fairnessViolations = 0;
    $fullTypeGroups = 0;
    $fullTypeViolations = 0;
    $missingAnyTypeGroups = 0;
    $violationSamples = [];
    foreach ($typeSums as $g) {
        $sup = (float) ($g->supervisor_sum ?? 0);
        $peer = (float) ($g->peer_sum ?? 0);
        $sub = (float) ($g->subordinate_sum ?? 0);
        $self = (float) ($g->self_sum ?? 0);
        $supCnt = (int) ($g->supervisor_cnt ?? 0);
        $peerCnt = (int) ($g->peer_cnt ?? 0);
        $subCnt = (int) ($g->subordinate_cnt ?? 0);
        $selfCnt = (int) ($g->self_cnt ?? 0);

        if ($sup + $peer + $sub + $self <= 0) {
            continue;
        }

        $present = [
            'supervisor' => $supCnt > 0,
            'peer' => $peerCnt > 0,
            'subordinate' => $subCnt > 0,
            'self' => $selfCnt > 0,
        ];
        if (in_array(false, $present, true)) {
            $missingAnyTypeGroups++;
        } else {
            $fullTypeGroups++;
        }

        $ok = true;
        // Compare only where both sides exist.
        if ($present['supervisor'] && $present['peer'] && !($sup + 0.001 >= $peer)) {
            $ok = false;
        }
        if ($present['peer'] && $present['subordinate'] && !($peer + 0.001 >= $sub)) {
            $ok = false;
        }
        if ($present['subordinate'] && $present['self'] && !($sub + 0.001 >= $self)) {
            $ok = false;
        }
        // If self exists, it should not exceed any other present type.
        if ($present['self']) {
            if (($present['supervisor'] && $self > $sup + 0.001) || ($present['peer'] && $self > $peer + 0.001) || ($present['subordinate'] && $self > $sub + 0.001)) {
                $ok = false;
            }
        }

        if (!$ok) {
            $fairnessViolations++;
            if (count($violationSamples) < 5) {
                $violationSamples[] = [
                    'unit_id' => (int) $g->unit_id,
                    'performance_criteria_id' => (int) $g->performance_criteria_id,
                    'assessee_profession_id' => (int) $g->assessee_profession_id,
                    'counts' => ['supervisor' => $supCnt, 'peer' => $peerCnt, 'subordinate' => $subCnt, 'self' => $selfCnt],
                    'sums' => ['supervisor' => $sup, 'peer' => $peer, 'subordinate' => $sub, 'self' => $self],
                ];
            }
        }

        if (!$ok && $present['supervisor'] && $present['peer'] && $present['subordinate'] && $present['self']) {
            $fullTypeViolations++;
        }
    }

    add("unit_rater_weights groups violating fairness (period_id={$pid})", $fairnessViolations);
    add("unit_rater_weights groups with all 4 types (period_id={$pid})", $fullTypeGroups);
    add("unit_rater_weights groups with all 4 types violating fairness (period_id={$pid})", $fullTypeViolations);
    add("unit_rater_weights groups missing any type (period_id={$pid})", $missingAnyTypeGroups);
    add("unit_rater_weights fairness violation samples (period_id={$pid})", $violationSamples);

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
