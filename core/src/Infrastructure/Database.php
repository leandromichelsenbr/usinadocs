<?php

declare(strict_types=1);

namespace UsinaDocs\Core\Infrastructure;

use PDO;

final class Database
{
    public static function connect(string $databasePath): PDO
    {
        $directory = dirname($databasePath);
        if (! is_dir($directory)) {
            mkdir($directory, 0775, true);
        }

        $pdo = new PDO('sqlite:'.$databasePath, null, null, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);
        $pdo->exec('PRAGMA foreign_keys = ON');

        return $pdo;
    }
}
