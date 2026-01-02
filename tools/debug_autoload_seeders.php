<?php

require __DIR__ . '/../vendor/autoload.php';

$classes = [
    'Database\\Seeders\\DatabaseSeeder',
    'Database\\Seeders\\EightStaffKpiSeeder',
    'Database\\Seeders\\NovemberRaterWeightSeeder',
    'Database\\Seeders\\DecemberRaterWeightSeeder',
];

foreach ($classes as $c) {
    echo $c . ' => ' . (class_exists($c) ? 'YES' : 'NO') . PHP_EOL;
}

$loader = require __DIR__ . '/../vendor/autoload.php';
$prefixes = $loader->getPrefixesPsr4();
$key = 'Database\\Seeders\\';

echo 'Has prefix ' . $key . ': ' . (array_key_exists($key, $prefixes) ? 'YES' : 'NO') . PHP_EOL;
if (array_key_exists($key, $prefixes)) {
    echo 'Paths: ' . json_encode($prefixes[$key], JSON_UNESCAPED_SLASHES) . PHP_EOL;
    foreach ($prefixes[$key] as $p) {
        echo ' - ' . $p . ' (exists=' . (is_dir($p) ? 'yes' : 'no') . ')' . PHP_EOL;
    }
}
