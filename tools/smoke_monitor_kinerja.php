<?php

/**
 * Smoke test for Kepala Unit Monitor Kinerja module queries.
 *
 * Usage:
 *   php tools/smoke_monitor_kinerja.php
 */

require __DIR__ . '/../vendor/autoload.php';

$app = require __DIR__ . '/../bootstrap/app.php';

/** @var \Illuminate\Contracts\Console\Kernel $kernel */
$kernel = $app->make(\Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$actor = \App\Models\User::query()
    ->whereHas('roles', fn ($q) => $q->where('slug', \App\Models\User::ROLE_KEPALA_UNIT))
    ->first();

$period = \App\Models\AssessmentPeriod::query()->orderByDesc('start_date')->first();

if (!$actor) {
    fwrite(STDERR, "No kepala_unit user found.\n");
    exit(2);
}
if (!$period) {
    fwrite(STDERR, "No assessment_period found.\n");
    exit(2);
}

/** @var \App\Services\UnitHead\PerformanceMonitorService $svc */
$svc = app(\App\Services\UnitHead\PerformanceMonitorService::class);

$mode = $svc->resolveMode($period, null);
$pager = $svc->paginateUnitMembers($actor, $period, $mode, [], 5);

echo "OK\n";
echo "period_id={$period->id} mode={$mode}\n";
echo "unit_id=" . ((int) ($actor->unit_id ?? 0)) . " total_members={$pager->total()}\n";

$first = $pager->items()[0] ?? null;
if ($first) {
    echo "sample_user_id={$first->id} name={$first->name} score=" . ($first->total_wsm_score ?? 'null') . "\n";
}

$frozen = \App\Models\AssessmentPeriod::query()
    ->whereIn('status', [
        \App\Models\AssessmentPeriod::STATUS_LOCKED,
        \App\Models\AssessmentPeriod::STATUS_APPROVAL,
        \App\Models\AssessmentPeriod::STATUS_REVISION,
        \App\Models\AssessmentPeriod::STATUS_CLOSED,
    ])
    ->orderByDesc('start_date')
    ->first();

if ($frozen) {
    $mode2 = $svc->resolveMode($frozen, 'snapshot');
    $pager2 = $svc->paginateUnitMembers($actor, $frozen, $mode2, [], 5);
    echo "\nSNAPSHOT TEST\n";
    echo "period_id={$frozen->id} status={$frozen->status} mode={$mode2}\n";
    echo "unit_id=" . ((int) ($actor->unit_id ?? 0)) . " total_members={$pager2->total()}\n";
}
