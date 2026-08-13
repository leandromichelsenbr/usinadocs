<?php
declare(strict_types=1);
use Dotenv\Dotenv;
use UsinaDocs\Core\Infrastructure\Database;
require dirname(__DIR__).'/vendor/autoload.php';
$root = dirname(__DIR__); Dotenv::createImmutable($root)->safeLoad();
$configuredPath = $_ENV['USINADOCS_DB_PATH'] ?? 'database/database.sqlite';
$isAbsolute = str_starts_with($configuredPath, DIRECTORY_SEPARATOR) || preg_match('/^[A-Za-z]:[\\\\\/]/', $configuredPath) === 1;
$database = Database::connect($isAbsolute ? $configuredPath : $root.DIRECTORY_SEPARATOR.$configuredPath);
$email = $_ENV['USINADOCS_ADMIN_EMAIL'] ?? '';
$password = $_ENV['USINADOCS_ADMIN_PASSWORD'] ?? '';
if ($email === '' || $password === '') { fwrite(STDERR, "Set USINADOCS_ADMIN_EMAIL and USINADOCS_ADMIN_PASSWORD in .env first.".PHP_EOL); exit(1); }
$statement = $database->prepare("UPDATE users SET email = :email, password_hash = :password_hash, role = 'administrator' WHERE id = 'user-admin'");
$statement->execute(['email' => $email, 'password_hash' => password_hash($password, PASSWORD_DEFAULT)]);
if ($statement->rowCount() === 0) { fwrite(STDERR, "Administrator not found. Run php bin/migrate.php first.".PHP_EOL); exit(1); }
fwrite(STDOUT, "Administrator credentials updated.".PHP_EOL);
