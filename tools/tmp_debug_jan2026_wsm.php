<?php

require __DIR__ . '/../vendor/autoload.php';

$app = require __DIR__ . '/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

$periodId = (int)($argv[1] ?? 4);
$period = DB::table('assessment_periods')->where('id', $periodId)->first();
if (!$period) {
    fwrite(STDERR, "Period #{$periodId} not found\n");
    exit(1);
}

echo "period_id={$periodId} name={$period->name} status={$period->status} start={$period->start_date} end={$period->end_date}\n";

$assessments = DB::table('performance_assessments as pa')
    ->join('users as u', 'u.id', '=', 'pa.user_id')
    ->where('pa.assessment_period_id', $periodId)
    ->orderBy('u.name')
    ->get(['pa.id as pa_id','pa.user_id','u.name as user_name','u.unit_id','u.profession_id','pa.total_wsm_score','pa.total_wsm_value_score','pa.supervisor_comment']);

echo "assessments_count=" . count($assessments) . "\n";
foreach ($assessments as $r) {
    $wsm = $r->total_wsm_score === null ? 'NULL' : (string)$r->total_wsm_score;
    $wsmv = $r->total_wsm_value_score === null ? 'NULL' : (string)$r->total_wsm_value_score;
    echo "pa_id={$r->pa_id} user_id={$r->user_id} unit_id={$r->unit_id} profession_id={$r->profession_id} wsm={$wsm} wsm_value={$wsmv} name={$r->user_name}\n";
}

// Weight availability by unit
if (Schema::hasTable('unit_criteria_weights')) {
    $unitIds = collect($assessments)->pluck('unit_id')->filter()->unique()->values()->all();
    foreach ($unitIds as $uid) {
        $active = (int) DB::table('unit_criteria_weights')
            ->where('assessment_period_id', $periodId)
            ->where('unit_id', (int)$uid)
            ->where('status', 'active')
            ->count();
        $all = (int) DB::table('unit_criteria_weights')
            ->where('assessment_period_id', $periodId)
            ->where('unit_id', (int)$uid)
            ->count();
        echo "unit_id={$uid} unit_criteria_weights active={$active} total={$all}\n";
    }
}

// Raw data indicators
$userIds = collect($assessments)->pluck('user_id')->values()->all();

$attendanceRows = Schema::hasTable('attendances')
    ? (int) DB::table('attendances')->whereIn('user_id', $userIds)->whereBetween('attendance_date', [$period->start_date, $period->end_date])->count()
    : -1;

$metricRows = Schema::hasTable('imported_criteria_values')
    ? (int) DB::table('imported_criteria_values')->where('assessment_period_id', $periodId)->whereIn('user_id', $userIds)->count()
    : -1;

$metricActiveRows = (Schema::hasTable('imported_criteria_values') && Schema::hasColumn('imported_criteria_values', 'is_active'))
    ? (int) DB::table('imported_criteria_values')->where('assessment_period_id', $periodId)->whereIn('user_id', $userIds)->where('is_active', 1)->count()
    : -1;

$invites = Schema::hasTable('review_invitations')
    ? (int) DB::table('review_invitations')->where('assessment_period_id', $periodId)->count()
    : -1;

$reviewsApproved = Schema::hasTable('reviews')
    ? (int) DB::table('reviews')->where('status', 'approved')->whereDate('decided_at', '>=', $period->start_date)->whereDate('decided_at', '<=', $period->end_date)->count()
    : -1;

$mraSubmitted = (Schema::hasTable('multi_rater_assessments'))
    ? (int) DB::table('multi_rater_assessments')->where('assessment_period_id', $periodId)->where('status', 'submitted')->count()
    : -1;

echo "raw_attendances_in_range={$attendanceRows}\n";
echo "raw_imported_criteria_values={$metricRows} active={$metricActiveRows}\n";
echo "review_invitations={$invites} approved_reviews_in_range={$reviewsApproved}\n";
echo "multi_rater_assessments_submitted={$mraSubmitted}\n";
