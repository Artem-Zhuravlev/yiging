<?php

declare(strict_types=1);

/**
 * Applies pending SQL migrations from apps/api/database/migrations to the
 * SQLite database, tracking applied migrations in a schema_migrations table.
 *
 * Usage: php scripts/migrate.php
 */

$apiRoot = dirname(__DIR__) . '/apps/api';

require $apiRoot . '/vendor/autoload.php';

use App\Core\Config;
use App\Core\Database;

$config = Config::fromEnv($apiRoot);
$pdo = Database::connect($config);

$pdo->exec(
    'CREATE TABLE IF NOT EXISTS schema_migrations (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        migration TEXT NOT NULL UNIQUE,
        applied_at TEXT NOT NULL DEFAULT (datetime(\'now\'))
    )',
);

$applied = $pdo->query('SELECT migration FROM schema_migrations')
    ->fetchAll(PDO::FETCH_COLUMN);

$migrationsDir = $apiRoot . '/database/migrations';
$files = glob($migrationsDir . '/*.php') ?: [];
sort($files);

$ran = 0;

foreach ($files as $file) {
    $name = basename($file, '.php');

    if (in_array($name, $applied, true)) {
        continue;
    }

    /** @var array{up: string} $migration */
    $migration = require $file;

    $pdo->beginTransaction();
    $pdo->exec($migration['up']);
    $stmt = $pdo->prepare('INSERT INTO schema_migrations (migration) VALUES (:migration)');
    $stmt->execute(['migration' => $name]);
    $pdo->commit();

    echo "Applied migration: {$name}\n";
    $ran++;
}

if ($ran === 0) {
    echo "No pending migrations. Database is up to date.\n";
}
