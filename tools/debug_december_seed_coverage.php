<?php

// Debug helper: verify December 2025 demo seed coverage across key tables.
// Run: php tools/debug_december_seed_coverage.php

require __DIR__ . '/../vendor/autoload.php';

$app = require __DIR__ . '/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

$period = DB::table('assessment_periods')->where('name', 'December 2025')->first(['id','name','start_date','end_date','status']);
if (!$period) {
    echo "Period 'December 2025' NOT FOUND\n";
    exit(1);
}

$pid = (int) $period->id;
$start = (string) $period->start_date;
$end = (string) $period->end_date;

$staffEmails = [
    'kepala.umum@rsud.local',
    'dokter.umum1@rsud.local',
    'dokter.umum2@rsud.local',
    'perawat1@rsud.local',
    'perawat2@rsud.local',
    'kepala.gigi@rsud.local',
    'dokter.spes1@rsud.local',
    'dokter.spes2@rsud.local',
];

$userIds = DB::table('users')->whereIn('email', $staffEmails)->pluck('id')->map(fn($v) => (int) $v)->filter()->values()->all();

$out = [
    'period' => $period,
    'staff_user_ids' => $userIds,
    'counts' => [],
];

$cnt = function (string $table, ?callable $q = null) {
    if (!Schema::hasTable($table)) {
        return null;
    }
    if ($q) {
        return $q();
    }
    return (int) DB::table($table)->count();
};

$out['counts']['additional_tasks'] = $cnt('additional_tasks', fn() => (int) DB::table('additional_tasks')->where('assessment_period_id', $pid)->count());
$out['counts']['additional_task_claims'] = $cnt('additional_task_claims', fn() => (int) DB::table('additional_task_claims as c')->join('additional_tasks as t', 't.id', '=', 'c.additional_task_id')->where('t.assessment_period_id', $pid)->whereIn('c.user_id', $userIds)->count());

$out['counts']['reviews'] = $cnt('reviews', fn() => (int) DB::table('reviews')->where('registration_ref', 'like', 'DRV-' . $pid . '-%')->count());
$out['counts']['review_details'] = $cnt('review_details', fn() => (int) DB::table('review_details as d')->join('reviews as r', 'r.id', '=', 'd.review_id')->whereIn('d.medical_staff_id', $userIds)->where('r.registration_ref', 'like', 'DRV-' . $pid . '-%')->count());
$out['counts']['review_invitations'] = $cnt('review_invitations', fn() => (int) DB::table('review_invitations')->where('assessment_period_id', $pid)->where('registration_ref', 'like', 'DRV-' . $pid . '-%')->count());
$out['counts']['review_invitation_staff'] = $cnt('review_invitation_staff', fn() => (int) DB::table('review_invitation_staff as s')->join('review_invitations as i', 'i.id', '=', 's.invitation_id')->whereIn('s.user_id', $userIds)->where('i.assessment_period_id', $pid)->count());

$out['counts']['assessment_360_windows'] = $cnt('assessment_360_windows', fn() => (int) DB::table('assessment_360_windows')->where('assessment_period_id', $pid)->count());
$out['counts']['multi_rater_assessments'] = $cnt('multi_rater_assessments', fn() => (int) DB::table('multi_rater_assessments')->where('assessment_period_id', $pid)->whereIn('assessee_id', $userIds)->count());
$out['counts']['multi_rater_assessment_details'] = $cnt('multi_rater_assessment_details', fn() => (int) DB::table('multi_rater_assessment_details as d')->join('multi_rater_assessments as a', 'a.id', '=', 'd.multi_rater_assessment_id')->where('a.assessment_period_id', $pid)->whereIn('a.assessee_id', $userIds)->count());

$out['counts']['attendance_import_batches'] = $cnt('attendance_import_batches', fn() => (int) DB::table('attendance_import_batches')->where('assessment_period_id', $pid)->count());
$out['counts']['attendance_import_rows'] = $cnt('attendance_import_rows', fn() => (int) DB::table('attendance_import_rows as r')->join('attendance_import_batches as b', 'b.id', '=', 'r.batch_id')->where('b.assessment_period_id', $pid)->whereIn('r.user_id', $userIds)->count());
$out['counts']['attendances'] = $cnt('attendances', function () use ($pid, $userIds, $start, $end) {
    if (Schema::hasTable('attendance_import_batches') && Schema::hasColumn('attendances', 'import_batch_id')) {
        return (int) DB::table('attendances as a')->join('attendance_import_batches as b', 'b.id', '=', 'a.import_batch_id')->where('b.assessment_period_id', $pid)->whereIn('a.user_id', $userIds)->count();
    }
    return (int) DB::table('attendances')->whereIn('user_id', $userIds)->whereBetween('attendance_date', [$start, $end])->count();
});

$out['counts']['metric_import_batches'] = $cnt('metric_import_batches', fn() => (int) DB::table('metric_import_batches')->where('assessment_period_id', $pid)->count());
$out['counts']['imported_criteria_values'] = $cnt('imported_criteria_values', fn() => (int) DB::table('imported_criteria_values')->where('assessment_period_id', $pid)->whereIn('user_id', $userIds)->count());

$out['counts']['performance_assessments'] = $cnt('performance_assessments', fn() => (int) DB::table('performance_assessments')->where('assessment_period_id', $pid)->whereIn('user_id', $userIds)->count());
$out['counts']['performance_assessment_details'] = $cnt('performance_assessment_details', fn() => (int) DB::table('performance_assessment_details as d')->join('performance_assessments as a', 'a.id', '=', 'd.performance_assessment_id')->where('a.assessment_period_id', $pid)->whereIn('a.user_id', $userIds)->count());

$out['counts']['assessment_approvals'] = $cnt('assessment_approvals', fn() => (int) DB::table('assessment_approvals as ap')->join('performance_assessments as pa', 'pa.id', '=', 'ap.performance_assessment_id')->where('pa.assessment_period_id', $pid)->whereIn('pa.user_id', $userIds)->count());

$out['counts']['unit_profession_remuneration_allocations'] = $cnt('unit_profession_remuneration_allocations', fn() => (int) DB::table('unit_profession_remuneration_allocations')->where('assessment_period_id', $pid)->count());
$out['counts']['remunerations'] = $cnt('remunerations', fn() => (int) DB::table('remunerations')->where('assessment_period_id', $pid)->whereIn('user_id', $userIds)->count());

// Extra: schedule sanity for December.
$out['counts']['attendance_schedule_ok'] = $cnt('attendances', function () use ($pid) {
    if (!Schema::hasTable('attendance_import_batches') || !Schema::hasColumn('attendances', 'import_batch_id')) {
        return null;
    }

    $agg = DB::table('attendances as a')
        ->join('attendance_import_batches as b', 'b.id', '=', 'a.import_batch_id')
        ->where('b.assessment_period_id', $pid)
        ->selectRaw('COUNT(*) as row_cnt')
        ->selectRaw('SUM(a.scheduled_in = "07:30:00") as in_ok')
        ->selectRaw('SUM(a.scheduled_out = "15:00:00") as out_ok')
        ->first();

    if (!$agg) {
        return null;
    }

    return [
        'row_cnt' => (int) $agg->row_cnt,
        'scheduled_in_0730_cnt' => (int) $agg->in_ok,
        'scheduled_out_1500_cnt' => (int) $agg->out_ok,
    ];
});

$path = __DIR__ . '/../storage/logs/debug_december_seed_coverage.json';
file_put_contents($path, json_encode($out, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

echo "WROTE: {$path}\n";
