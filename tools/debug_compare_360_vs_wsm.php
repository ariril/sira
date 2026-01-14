<?php

require __DIR__ . '/../vendor/autoload.php';

$app = require __DIR__ . '/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$userId = (int) ($argv[1] ?? 0);
$periodId = (int) ($argv[2] ?? 0);

if ($userId <= 0 || $periodId <= 0) {
    fwrite(STDERR, "Usage: php tools/debug_compare_360_vs_wsm.php <user_id> <period_id>\n");
    exit(2);
}

$user = App\Models\User::query()->find($userId);
$period = App\Models\AssessmentPeriod::query()->find($periodId);
if (!$user || !$period) {
    fwrite(STDERR, "User or period not found\n");
    exit(1);
}

$unitId = (int) ($user->unit_id ?? 0);
$professionId = $user->profession_id !== null ? (int) $user->profession_id : null;

// WSM engine (will use snapshot if period frozen)
$svc = app(App\Services\PerformanceScore\PerformanceScoreService::class);
$calc = $svc->calculate($unitId, $period, [$userId], $professionId);
$userRow = $calc['users'][$userId] ?? null;
$criteriaRows = is_array($userRow) ? ($userRow['criteria'] ?? []) : [];

$wsm360 = [];
foreach ($criteriaRows as $row) {
    $name = (string) ($row['criteria_name'] ?? '');
    $is360 = str_contains(mb_strtolower($name), '(360)');
    if (!$is360) {
        continue;
    }
    $wsm360[] = [
        'criteria_id' => (int) ($row['criteria_id'] ?? 0),
        'criteria' => $name,
        'raw' => (float) ($row['raw'] ?? 0.0),
        'normalized' => (float) ($row['nilai_normalisasi'] ?? 0.0),
        'relative' => (float) ($row['nilai_relativ_unit'] ?? 0.0),
        'included' => (bool) ($row['included_in_wsm'] ?? false),
    ];
}

// 360 summary (always live)
$summary = App\Services\MultiRater\SummaryService::build($userId, $periodId);
$rows = $summary['rows'] ?? collect();
$summaryOut = [];
foreach ($rows as $r) {
    $summaryOut[] = [
        'criteria' => $r['name'] ?? null,
        'final' => $r['avg_score'] ?? null,
    ];
}

echo json_encode([
    'user_id' => $userId,
    'period_id' => $periodId,
    'period_status' => (string) ($period->status ?? ''),
    'wsm_calculation_source' => (string) ($calc['calculation_source'] ?? 'unknown'),
    'wsm_360_rows' => $wsm360,
    'summary_360_rows' => $summaryOut,
], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . PHP_EOL;
