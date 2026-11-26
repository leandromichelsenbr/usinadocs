<?php

declare(strict_types=1);

use Dotenv\Dotenv;
use UsinaDocs\Core\Infrastructure\Database;
use UsinaDocs\Core\Social\ArticleLinks;
use UsinaDocs\Core\Social\SocialPublication;
use UsinaDocs\Core\Social\SocialPublisher;

$root = dirname(__DIR__);
require $root.'/vendor/autoload.php';

Dotenv::createImmutable($root)->safeLoad();

// The scheduler can pass its own private file path so the web server has no access to the provider tokens.
// O agendador pode informar o caminho do arquivo privado para que o servidor web não tenha acesso aos tokens.
$configPath = $argv[1] ?? '';
if (!is_file($configPath) || !is_readable($configPath)) {
    fwrite(STDERR, "Provide a readable private social credentials file.\n");
    exit(1);
}
$config = json_decode((string) file_get_contents($configPath), true);
$origin = ArticleLinks::origin($_ENV['USINADOCS_PUBLIC_ORIGIN'] ?? '');
if (!is_array($config) || $origin === null || !str_starts_with($origin, 'https://') || !extension_loaded('curl')) {
    fwrite(STDERR, "Social publishing requires a JSON object, a public HTTPS origin and PHP cURL.\n");
    exit(1);
}
$dbPath = $_ENV['USINADOCS_DB_PATH'] ?? 'database/database.sqlite';
if (!str_starts_with($dbPath, DIRECTORY_SEPARATOR) && !preg_match('/^[A-Za-z]:[\\\\\/]/', $dbPath)) {
    $dbPath = $root.DIRECTORY_SEPARATOR.$dbPath;
}
if (!is_file($dbPath)) {
    fwrite(STDERR, "The configured Usina Docs database does not exist.\n");
    exit(1);
}
$db = Database::connect($dbPath);

// The worker keeps the status only. Provider response bodies can contain user data and must stay out of logs.
// O processo de envio guarda apenas o status. Respostas dos provedores podem conter dados de usuários e ficam fora dos logs.
$post = static function (string $url, array $headers, string $body): int {
    $curl = curl_init($url);
    curl_setopt_array($curl, [
        CURLOPT_POSTFIELDS => $body,
        CURLOPT_HTTPHEADER => $headers,
        CURLOPT_FOLLOWLOCATION => false,
        CURLOPT_CONNECTTIMEOUT => 5,
        CURLOPT_TIMEOUT => 12,
        CURLOPT_WRITEFUNCTION => static fn ($handle, string $chunk): int => strlen($chunk),
    ]);
    try {
        return curl_exec($curl) === false ? 0 : curl_getinfo($curl, CURLINFO_RESPONSE_CODE);
    } finally {
        curl_close($curl);
    }
};

(new SocialPublisher(new SocialPublication($db)))->run($origin, $config, $post);
