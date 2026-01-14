<?php

require __DIR__ . '/../vendor/autoload.php';

$app = require __DIR__ . '/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$userId = (int) ($argv[1] ?? 0);
if ($userId <= 0) {
    fwrite(STDERR, "Usage: php tools/debug_360_summary.php <user_id> [period_id]\n");
    exit(2);
}

$periodId = isset($argv[2]) ? (int) $argv[2] : null;

$summary = App\Services\MultiRater\SummaryService::build($userId, $periodId);
$rows = $summary['rows'] ?? collect();

$out = [];
foreach ($rows as $r) {
    $out[] = [
        'criteria' => $r['name'] ?? null,
        'type' => $r['type'] ?? null,
        'final' => $r['avg_score'] ?? null,
    ];
}

echo json_encode([
    'user_id' => $userId,
    'period_id' => $summary['selected_period']->id ?? null,
    'period_name' => $summary['selected_period']->name ?? null,
    'rows' => $out,
], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . PHP_EOL;
