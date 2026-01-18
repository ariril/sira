<?php

// Debug: inspect snapshot payload + criteria flags for 360 criteria in a period.
// Run: php tools/debug_snapshot_360_flags.php

require __DIR__ . '/../vendor/autoload.php';

$app = require __DIR__ . '/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\DB;

$unitId = (int) (DB::table('units')->where('slug', 'poliklinik-umum')->value('id') ?? 0);
$userId = (int) (DB::table('users')->where('email', 'kepala.umum@rsud.local')->value('id') ?? 0);

$criteriaIds = DB::table('performance_criterias')
    ->whereIn('name', ['Kedisiplinan (360)', 'Kerjasama (360)'])
    ->pluck('id')
    ->map(fn ($v) => (int) $v)
    ->all();

echo "unit_id={$unitId} user_id={$userId}\n";
echo "criteria_ids=" . json_encode($criteriaIds) . "\n";

$critRows = DB::table('performance_criterias')
    ->whereIn('id', $criteriaIds)
    ->get(['id','name','is_active','is_360','normalization_basis']);

echo "\nperformance_criterias:\n";
foreach ($critRows as $r) {
    echo "- {$r->id} {$r->name} is_active=" . (int)$r->is_active . " is_360=" . (int)$r->is_360 . " basis={$r->normalization_basis}\n";
}

$periodIds = [2,3];
foreach ($periodIds as $pid) {
    $snap = DB::table('performance_assessment_snapshots')
        ->where('assessment_period_id', $pid)
        ->where('user_id', $userId)
        ->first(['payload','snapshotted_at']);

    echo "\nperiod_id={$pid} snapshot_at=" . ($snap->snapshotted_at ?? 'null') . "\n";
    if (!$snap) {
        echo "(no snapshot row)\n";
        continue;
    }

    $payload = json_decode((string)$snap->payload, true);
    $criteria = $payload['calc']['user']['criteria'] ?? [];
    $byId = [];
    foreach ($criteria as $row) {
        $cid = (int)($row['criteria_id'] ?? 0);
        if ($cid > 0) {
            $byId[$cid] = $row;
        }
    }

    foreach ($criteriaIds as $cid) {
        $row = $byId[$cid] ?? null;
        echo "criteria_id={$cid}: " . ($row ? 'found' : 'missing') . "\n";
        if ($row) {
            $keep = [
                'criteria_name' => $row['criteria_name'] ?? null,
                'is_active' => $row['is_active'] ?? null,
                'included_in_wsm' => $row['included_in_wsm'] ?? null,
                'weight' => $row['weight'] ?? null,
                'weight_status' => $row['weight_status'] ?? null,
                'readiness_status' => $row['readiness_status'] ?? null,
                'readiness_message' => $row['readiness_message'] ?? null,
                'raw' => $row['raw'] ?? null,
                'nilai_normalisasi' => $row['nilai_normalisasi'] ?? null,
                'nilai_relativ_unit' => $row['nilai_relativ_unit'] ?? null,
            ];
            echo json_encode($keep, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n";
        }
    }
}
