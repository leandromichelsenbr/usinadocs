<?php

declare(strict_types=1);

use Dotenv\Dotenv;
use UsinaDocs\Core\Infrastructure\Database;
use UsinaDocs\Core\Infrastructure\Schema;

require dirname(__DIR__).'/vendor/autoload.php';

$root = dirname(__DIR__);
Dotenv::createImmutable($root)->safeLoad();

$configuredPath = $_ENV['USINADOCS_DB_PATH'] ?? 'database/database.sqlite';
$isAbsolute = str_starts_with($configuredPath, DIRECTORY_SEPARATOR) || preg_match('/^[A-Za-z]:[\\\\\/]/', $configuredPath) === 1;
$databasePath = $isAbsolute ? $configuredPath : $root.DIRECTORY_SEPARATOR.$configuredPath;

$database = Database::connect($databasePath);
Schema::migrate($database, $root);
Schema::seed($database);

fwrite(STDOUT, "Database ready: {$databasePath}".PHP_EOL);
