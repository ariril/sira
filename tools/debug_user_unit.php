<?php

require __DIR__ . '/../vendor/autoload.php';

$app = require __DIR__ . '/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$userId = (int) ($argv[1] ?? 0);
if ($userId <= 0) {
    fwrite(STDERR, "Usage: php tools/debug_user_unit.php <user_id>\n");
    exit(2);
}

$u = App\Models\User::query()->find($userId);
if (!$u) {
    fwrite(STDERR, "User not found\n");
    exit(1);
}

echo json_encode([
    'user_id' => (int) $u->id,
    'unit_id' => (int) ($u->unit_id ?? 0),
    'profession_id' => $u->profession_id !== null ? (int) $u->profession_id : null,
], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . PHP_EOL;
