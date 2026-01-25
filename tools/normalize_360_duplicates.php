<?php

// Normalize duplicate 360 assessments inside a period to avoid double-counting.
// It merges duplicate multi_rater_assessments (same assessee+assessor+type+level+period)
// by copying missing detail rows into a chosen primary record and cancelling the rest.
//
// Run:
//   php tools/normalize_360_duplicates.php <period_id> [--dry]

require __DIR__ . '/../vendor/autoload.php';

$app = require __DIR__ . '/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\DB;

$periodId = (int) ($argv[1] ?? 0);
$dry = in_array('--dry', $argv, true);

if ($periodId <= 0) {
    fwrite(STDERR, "Usage: php tools/normalize_360_duplicates.php <period_id> [--dry]\n");
    exit(2);
}

if (!DB::getSchemaBuilder()->hasTable('multi_rater_assessments') || !DB::getSchemaBuilder()->hasTable('multi_rater_assessment_details')) {
    fwrite(STDERR, "Required tables missing\n");
    exit(1);
}

$dups = DB::table('multi_rater_assessments')
    ->where('assessment_period_id', $periodId)
    ->selectRaw('assessee_id, assessor_id, assessor_type, COALESCE(assessor_level, 0) as assessor_level_norm, COUNT(*) as cnt')
    ->groupBy('assessee_id', 'assessor_id', 'assessor_type', 'assessor_level_norm')
    ->having('cnt', '>', 1)
    ->get();

echo "Period {$periodId}: duplicate groups=" . $dups->count() . " dry=" . ($dry ? 'yes' : 'no') . "\n";

$groupsFixed = 0;
$assessmentsCancelled = 0;
$detailsCopied = 0;

foreach ($dups as $g) {
    $assesseeId = (int) $g->assessee_id;
    $assessorId = (int) $g->assessor_id;
    $assessorType = (string) $g->assessor_type;
    $assessorLevel = (int) $g->assessor_level_norm;

    $rows = DB::table('multi_rater_assessments')
        ->where('assessment_period_id', $periodId)
        ->where('assessee_id', $assesseeId)
        ->where('assessor_id', $assessorId)
        ->where('assessor_type', $assessorType)
        ->whereRaw('COALESCE(assessor_level, 0) = ?', [$assessorLevel])
        ->orderByDesc('id')
        ->get(['id', 'status', 'submitted_at']);

    if ($rows->count() <= 1) {
        continue;
    }

    $primary = $rows->first();
    $primaryId = (int) $primary->id;

    $bestSubmittedAt = $primary->submitted_at;
    $bestStatus = (string) $primary->status;
    foreach ($rows as $r) {
        if ((string) $r->status === 'submitted') {
            $bestStatus = 'submitted';
            if ($r->submitted_at && (!$bestSubmittedAt || $r->submitted_at > $bestSubmittedAt)) {
                $bestSubmittedAt = $r->submitted_at;
            }
        }
    }

    foreach ($rows as $r) {
        $rid = (int) $r->id;
        if ($rid === $primaryId) continue;

        $dupDetails = DB::table('multi_rater_assessment_details')
            ->where('multi_rater_assessment_id', $rid)
            ->get(['performance_criteria_id', 'score']);

        foreach ($dupDetails as $d) {
            $exists = DB::table('multi_rater_assessment_details')
                ->where('multi_rater_assessment_id', $primaryId)
                ->where('performance_criteria_id', (int) $d->performance_criteria_id)
                ->exists();

            if (!$exists) {
                $detailsCopied++;
                if (!$dry) {
                    DB::table('multi_rater_assessment_details')->insert([
                        'multi_rater_assessment_id' => $primaryId,
                        'performance_criteria_id' => (int) $d->performance_criteria_id,
                        'score' => (int) $d->score,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }
            }
        }

        if ((string) $r->status !== 'cancelled') {
            $assessmentsCancelled++;
            if (!$dry) {
                DB::table('multi_rater_assessments')
                    ->where('id', $rid)
                    ->update(['status' => 'cancelled']);
            }
        }
    }

    if ($bestStatus === 'submitted') {
        if (!$dry) {
            DB::table('multi_rater_assessments')
                ->where('id', $primaryId)
                ->update([
                    'status' => 'submitted',
                    'submitted_at' => $bestSubmittedAt,
                ]);
        }
    }

    $groupsFixed++;
}

echo "Groups processed: {$groupsFixed}\n";
echo "Details copied: {$detailsCopied}\n";
echo "Assessments cancelled: {$assessmentsCancelled}\n";
