<?php
declare(strict_types=1);
namespace UsinaDocs\Core\Tests;
use PHPUnit\Framework\TestCase;
use Slim\Psr7\Factory\ServerRequestFactory;
use Slim\Psr7\Factory\StreamFactory;
use UsinaDocs\Core\AppFactory;
use UsinaDocs\Core\Infrastructure\Database;
use UsinaDocs\Core\Infrastructure\Schema;
final class LoginRouteTest extends TestCase {
    private string $databasePath;
    protected function setUp(): void {
        $this->databasePath = sys_get_temp_dir().DIRECTORY_SEPARATOR.'usinadocs-login-'.bin2hex(random_bytes(8)).'.sqlite';
        $_ENV['USINADOCS_ADMIN_EMAIL'] = 'admin@example.test'; $_ENV['USINADOCS_ADMIN_PASSWORD'] = 'secret-password';
        $db = Database::connect($this->databasePath); Schema::migrate($db, dirname(__DIR__)); Schema::seed($db);
    }
    protected function tearDown(): void { if (is_file($this->databasePath)) @unlink($this->databasePath); }
    public function test_login_form_redirects_an_administrator_to_the_dashboard(): void {
        $request = (new ServerRequestFactory())->createServerRequest('POST', '/login')
            ->withHeader('Content-Type', 'application/x-www-form-urlencoded')
            ->withBody((new StreamFactory())->createStream('email=admin%40example.test&password=secret-password'));
        $response = AppFactory::create(dirname(__DIR__), $this->databasePath)->handle($request);
        self::assertSame(302, $response->getStatusCode()); self::assertSame('/admin', $response->getHeaderLine('Location'));
    }
}
