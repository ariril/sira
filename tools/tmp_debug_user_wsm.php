<?php

require __DIR__ . '/../vendor/autoload.php';

$app = require __DIR__ . '/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\AssessmentPeriod;
use App\Services\PerformanceScore\PerformanceScoreService;
use Illuminate\Support\Facades\DB;

$periodId = (int) ($argv[1] ?? 0);
$userId = (int) ($argv[2] ?? 0);

if ($periodId <= 0 || $userId <= 0) {
    fwrite(STDERR, "Usage: php tools/tmp_debug_user_wsm.php <period_id> <user_id>\n");
    exit(2);
}

/** @var AssessmentPeriod|null $period */
$period = AssessmentPeriod::query()->where('id', $periodId)->first();
if (!$period) {
    fwrite(STDERR, "Period #{$periodId} not found\n");
    exit(1);
}

$user = DB::table('users')->where('id', $userId)->first(['id', 'name', 'unit_id', 'profession_id']);
if (!$user) {
    fwrite(STDERR, "User #{$userId} not found\n");
    exit(1);
}
if (!$user->unit_id || !$user->profession_id) {
    fwrite(STDERR, "User #{$userId} missing unit_id/profession_id\n");
    exit(1);
}

/** @var PerformanceScoreService $svc */
$svc = app(PerformanceScoreService::class);

$calc = $svc->calculate((int) $user->unit_id, $period, [(int) $userId], (int) $user->profession_id);
$userOut = $calc['users'][(int) $userId] ?? [];

$criteriaRows = $userOut['criteria'] ?? [];
$rows = [];
foreach ($criteriaRows as $r) {
    $rows[] = [
        'criteria_id' => (int) ($r['criteria_id'] ?? 0),
        'criteria_name' => (string) ($r['criteria_name'] ?? ''),
        'collector' => (string) ($r['collector'] ?? ''),
        'is_360' => (bool) ($r['is_360'] ?? false),
        'weight' => (float) ($r['weight'] ?? 0),
        'weight_status' => (string) ($r['weight_status'] ?? ''),
        'is_active' => (bool) ($r['is_active'] ?? false),
        'included_in_wsm' => (bool) ($r['included_in_wsm'] ?? false),
        'readiness_status' => (string) ($r['readiness_status'] ?? ''),
        'readiness_message' => $r['readiness_message'] ?? null,
        'raw' => $r['raw'] ?? null,
        'score' => $r['score'] ?? null,
    ];
}

$out = [
    'period' => ['id' => (int) $period->id, 'name' => (string) $period->name, 'status' => (string) $period->status],
    'user' => ['id' => (int) $user->id, 'name' => (string) $user->name, 'unit_id' => (int) $user->unit_id, 'profession_id' => (int) $user->profession_id],
    'calculation_source' => (string) (($calc['calculation_source'] ?? null) ?: 'unknown'),
    'snapshotted_at' => $calc['snapshotted_at'] ?? null,
    'total_wsm' => $userOut['total_wsm'] ?? null,
    'total_wsm_value' => $userOut['total_wsm_value'] ?? null,
    'sum_weight' => $userOut['sum_weight'] ?? null,
    'sum_weight_included' => $userOut['sum_weight_included'] ?? null,
    'criteria' => $rows,
];

$logPath = __DIR__ . '/../storage/logs/tmp_debug_user_wsm_period_' . $periodId . '_user_' . $userId . '.json';
file_put_contents($logPath, json_encode($out, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

echo "WROTE: {$logPath}\n";
