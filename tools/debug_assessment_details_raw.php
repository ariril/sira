<?php

require __DIR__ . '/../vendor/autoload.php';

$app = require __DIR__ . '/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$assessmentId = (int) ($argv[1] ?? 0);
if ($assessmentId <= 0) {
    fwrite(STDERR, "Usage: php tools/debug_assessment_details_raw.php <performance_assessment_id>\n");
    exit(2);
}

$assessment = App\Models\PerformanceAssessment::query()
    ->with(['details.performanceCriteria', 'assessmentPeriod', 'user'])
    ->find($assessmentId);

if (!$assessment) {
    fwrite(STDERR, "Assessment not found\n");
    exit(1);
}

$out = [];
foreach ($assessment->details as $d) {
    $name = (string) ($d->performanceCriteria->name ?? '');
    if (!str_contains(mb_strtolower($name), '(360)')) {
        continue;
    }

    $meta = is_array($d->meta) ? $d->meta : (array) ($d->meta ?? []);

    $out[] = [
        'detail_id' => (int) $d->id,
        'criteria_id' => (int) $d->performance_criteria_id,
        'criteria' => $name,
        'stored_score' => $d->score !== null ? (float) $d->score : null,
        'meta_raw_value' => array_key_exists('raw_value', $meta) ? (float) $meta['raw_value'] : null,
        'meta_nilai_normalisasi' => array_key_exists('nilai_normalisasi', $meta) ? (float) $meta['nilai_normalisasi'] : null,
    ];
}

echo json_encode([
    'assessment_id' => (int) $assessment->id,
    'period_id' => (int) ($assessment->assessment_period_id ?? 0),
    'period_name' => (string) ($assessment->assessmentPeriod->name ?? ''),
    'user_id' => (int) ($assessment->user_id ?? 0),
    '360_details' => $out,
], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . PHP_EOL;
