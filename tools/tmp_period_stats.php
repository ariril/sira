<?php

require __DIR__ . '/../vendor/autoload.php';

$app = require __DIR__ . '/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$periods = Illuminate\Support\Facades\DB::table('assessment_periods')
    ->orderByDesc('start_date')
    ->limit(5)
    ->get(['id','name','status','start_date','end_date']);

foreach ($periods as $p) {
    $pid = (int) $p->id;
    $total = (int) Illuminate\Support\Facades\DB::table('performance_assessments')
        ->where('assessment_period_id', $pid)
        ->count();

    $null = (int) Illuminate\Support\Facades\DB::table('performance_assessments')
        ->where('assessment_period_id', $pid)
        ->whereNull('total_wsm_score')
        ->count();

    $nullVal = (int) Illuminate\Support\Facades\DB::table('performance_assessments')
        ->where('assessment_period_id', $pid)
        ->whereNull('total_wsm_value_score')
        ->count();

    $m = Illuminate\Support\Facades\Schema::hasTable('metric_import_batches')
        ? (int) Illuminate\Support\Facades\DB::table('metric_import_batches')->where('assessment_period_id', $pid)->count()
        : -1;

    $a = Illuminate\Support\Facades\Schema::hasTable('attendance_import_batches')
        ? (int) Illuminate\Support\Facades\DB::table('attendance_import_batches')->where('assessment_period_id', $pid)->count()
        : -1;

    $r = Illuminate\Support\Facades\Schema::hasTable('review_invitations')
        ? (int) Illuminate\Support\Facades\DB::table('review_invitations')->where('assessment_period_id', $pid)->count()
        : -1;

    echo "period_id={$pid} status={$p->status} name={$p->name} assessments={$total} null_wsm={$null} null_wsm_value={$nullVal} metric_batches={$m} attendance_batches={$a} review_invites={$r}\n";
}
