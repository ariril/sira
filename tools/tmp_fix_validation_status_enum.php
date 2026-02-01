<?php

require __DIR__ . '/../vendor/autoload.php';

$app = require __DIR__ . '/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Enums\AssessmentValidationStatus;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

if (!Schema::hasTable('performance_assessments')) {
    fwrite(STDERR, "performance_assessments table not found\n");
    exit(1);
}

$col = Schema::hasColumn('performance_assessments', 'validation_status');
if (!$col) {
    fwrite(STDERR, "performance_assessments.validation_status column not found\n");
    exit(1);
}

$before = DB::table('performance_assessments')
    ->selectRaw('validation_status, COUNT(*) as c')
    ->groupBy('validation_status')
    ->orderBy('c', 'desc')
    ->get();

echo "BEFORE:\n";
foreach ($before as $r) {
    $v = $r->validation_status === null ? 'NULL' : (string) $r->validation_status;
    echo "- {$v}: {$r->c}\n";
}

$map = [
    'pending' => AssessmentValidationStatus::PENDING->value,
    'validated' => AssessmentValidationStatus::VALIDATED->value,
    'rejected' => AssessmentValidationStatus::REJECTED->value,
    'PENDING' => AssessmentValidationStatus::PENDING->value,
    'VALIDATED' => AssessmentValidationStatus::VALIDATED->value,
    'REJECTED' => AssessmentValidationStatus::REJECTED->value,
];

$updated = 0;
foreach ($map as $from => $to) {
    $updated += (int) DB::table('performance_assessments')
        ->where('validation_status', $from)
        ->update(['validation_status' => $to, 'updated_at' => now()]);
}

echo "UPDATED_ROWS={$updated}\n";

$after = DB::table('performance_assessments')
    ->selectRaw('validation_status, COUNT(*) as c')
    ->groupBy('validation_status')
    ->orderBy('c', 'desc')
    ->get();

echo "AFTER:\n";
foreach ($after as $r) {
    $v = $r->validation_status === null ? 'NULL' : (string) $r->validation_status;
    echo "- {$v}: {$r->c}\n";
}
