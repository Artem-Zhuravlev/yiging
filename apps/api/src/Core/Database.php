<?php

declare(strict_types=1);

namespace App\Core;

use PDO;

final class Database
{
    public static function connect(Config $config): PDO
    {
        $path = $config->string('database_path');

        $pdo = new PDO('sqlite:' . $path);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
        $pdo->exec('PRAGMA foreign_keys = ON');

        return $pdo;
    }
}
