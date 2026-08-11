<?php

declare(strict_types=1);

/**
 * Runs deterministic seed scripts from apps/api/database/seeds against the
 * SQLite database. Safe to re-run.
 *
 * Usage: php scripts/seed.php
 */

$apiRoot = dirname(__DIR__) . '/apps/api';

require $apiRoot . '/vendor/autoload.php';

use App\Core\Config;
use App\Core\Database;

$config = Config::fromEnv($apiRoot);
$pdo = Database::connect($config);

$seedsDir = $apiRoot . '/database/seeds';
$files = glob($seedsDir . '/*.php') ?: [];
sort($files);

if ($files === []) {
    echo "No seeds defined yet.\n";
    exit(0);
}

foreach ($files as $file) {
    $seed = require $file;
    $seed($pdo);
    echo 'Seeded: ' . basename($file, '.php') . "\n";
}
