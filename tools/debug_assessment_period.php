<?php

require __DIR__ . '/../vendor/autoload.php';

$app = require __DIR__ . '/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$assessmentId = (int) ($argv[1] ?? 0);
if ($assessmentId <= 0) {
    fwrite(STDERR, "Usage: php tools/debug_assessment_period.php <performance_assessment_id>\n");
    exit(2);
}

$assessment = App\Models\PerformanceAssessment::query()->find($assessmentId);
if (!$assessment) {
    fwrite(STDERR, "PerformanceAssessment #{$assessmentId} not found\n");
    exit(1);
}

$periodId = (int) ($assessment->assessment_period_id ?? 0);
$period = $periodId > 0 ? App\Models\AssessmentPeriod::query()->find($periodId) : null;

$snapshotCount = 0;
if ($periodId > 0 && Illuminate\Support\Facades\Schema::hasTable('performance_assessment_snapshots')) {
    $snapshotCount = (int) Illuminate\Support\Facades\DB::table('performance_assessment_snapshots')
        ->where('assessment_period_id', $periodId)
        ->count();
}

echo json_encode([
    'assessment_id' => (int) $assessment->id,
    'assessment_period_id' => $periodId,
    'user_id' => (int) ($assessment->user_id ?? 0),
    'period_status' => (string) ($period->status ?? ''),
    'period_name' => (string) ($period->name ?? ''),
    'period_is_frozen' => (bool) ($period ? $period->isFrozen() : false),
    'snapshots_in_period' => $snapshotCount,
], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . PHP_EOL;
