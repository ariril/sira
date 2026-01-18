<?php

// Debug helper: emulate PerformanceScoreService output for one user in Poli Umum for Nov/Dec 2025.
// Run: php tools/debug_my_performance_nov_dec.php

require __DIR__ . '/../vendor/autoload.php';

$app = require __DIR__ . '/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\AssessmentPeriod;
use App\Services\MultiRater\RaterWeightResolver;
use App\Services\PerformanceScore\PerformanceScoreService;
use Illuminate\Support\Facades\DB;

$unitId = (int) (DB::table('units')->where('slug', 'poliklinik-umum')->value('id') ?? 0);
if ($unitId <= 0) {
    $unitId = 2;
}

$user = DB::table('users')
    ->where('unit_id', $unitId)
    ->whereNotNull('profession_id')
    ->orderBy('id')
    ->first(['id', 'name', 'profession_id']);

if (!$user) {
    echo "No user found for unit_id={$unitId}\n";
    exit(1);
}

$criteriaIds = DB::table('performance_criterias')
    ->whereIn('name', ['Kedisiplinan (360)', 'Kerjasama (360)'])
    ->pluck('id')
    ->map(fn ($v) => (int) $v)
    ->all();

$periodIds = [2, 3];
$out = [
    'unit_id' => $unitId,
    'user' => ['id' => (int) $user->id, 'name' => (string) $user->name, 'profession_id' => (int) $user->profession_id],
    'criteria_ids' => $criteriaIds,
    'periods' => [],
];

/** @var PerformanceScoreService $svc */
$svc = app(PerformanceScoreService::class);

foreach ($periodIds as $pid) {
    /** @var AssessmentPeriod|null $period */
    $period = AssessmentPeriod::query()->where('id', (int) $pid)->first();
    if (!$period) {
        continue;
    }

    $calc = $svc->calculate($unitId, $period, [(int) $user->id], (int) $user->profession_id);
    $rows = $calc['users'][(int) $user->id]['criteria'] ?? [];

    $resolvedByCriteria = [];
    foreach ($criteriaIds as $cid) {
        $resolvedByCriteria[$cid] = RaterWeightResolver::resolveForCriteria((int) $period->id, (int) $unitId, (int) $cid, [(int) $user->profession_id]);
    }

    $filtered = [];
    foreach ($rows as $r) {
        if (in_array((int) ($r['criteria_id'] ?? 0), $criteriaIds, true)) {
            $filtered[] = [
                'criteria_id' => (int) $r['criteria_id'],
                'criteria_name' => (string) ($r['criteria_name'] ?? ''),
                'is_360' => (bool) ($r['is_360'] ?? false),
                'weight' => (float) ($r['weight'] ?? 0),
                'weight_status' => (string) ($r['weight_status'] ?? ''),
                'included_in_wsm' => (bool) ($r['included_in_wsm'] ?? false),
                'readiness_status' => (string) ($r['readiness_status'] ?? ''),
                'readiness_message' => $r['readiness_message'] ?? null,
                'raw' => (float) ($r['raw'] ?? 0),
            ];
        }
    }

    $out['periods'][] = [
        'id' => (int) $period->id,
        'name' => (string) $period->name,
        'status' => (string) $period->status,
        'calculation_source' => (string) (($calc['calculation_source'] ?? null) ?: 'unknown'),
        'snapshotted_at' => $calc['snapshotted_at'] ?? null,
        'resolved_rater_weights' => $resolvedByCriteria,
        'criteria' => $filtered,
        'total_wsm' => $calc['users'][(int) $user->id]['total_wsm'] ?? null,
        'sum_weight' => $calc['users'][(int) $user->id]['sum_weight'] ?? null,
    ];
}

$logPath = __DIR__ . '/../storage/logs/debug_my_performance_nov_dec.json';
file_put_contents($logPath, json_encode($out, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

echo "WROTE: {$logPath}\n";
