<?php

declare(strict_types=1);

use Dotenv\Dotenv;
use UsinaDocs\Core\Authentication\OAuthAccounts;
use UsinaDocs\Core\Infrastructure\Database;
use UsinaDocs\Core\Infrastructure\Schema;

$root = dirname(__DIR__);
require $root.'/vendor/autoload.php';

$email = trim($argv[1] ?? '');
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    fwrite(STDERR, "Usage: php bin/invite-administrator.php verified-address@example.com\n");
    exit(1);
}

Dotenv::createImmutable($root)->safeLoad();
$path = $_ENV['USINADOCS_DB_PATH'] ?? 'database/database.sqlite';
$isAbsolute = str_starts_with($path, '/') || preg_match('~^[a-z]:[\\\\/]~i', $path) === 1;
$database = Database::connect($isAbsolute ? $path : $root.'/'.$path);
Schema::migrate($database, $root);
(new OAuthAccounts($database))->inviteAdministrator($email);
fwrite(STDOUT, "Administrator invitation saved. It will be claimed through verified Google sign-in.\n");
