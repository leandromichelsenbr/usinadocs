<?php
declare(strict_types=1);
namespace UsinaDocs\Core\Tests;
use PHPUnit\Framework\TestCase;
use Slim\Psr7\Factory\ServerRequestFactory;
use Slim\Psr7\Factory\StreamFactory;
use UsinaDocs\Core\AppFactory;
use UsinaDocs\Core\Security\CsrfToken;
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
        // EN: The browser first receives the token with the login form; the POST sends it back.
        // PT: O navegador recebe o token junto com o formulário de login; o POST o devolve.
        $app = AppFactory::create(dirname(__DIR__), $this->databasePath);
        $_SESSION = [];
        $beforeLogin = CsrfToken::current();
        $request = (new ServerRequestFactory())->createServerRequest('POST', '/login')
            ->withHeader('Content-Type', 'application/x-www-form-urlencoded')
            ->withBody((new StreamFactory())->createStream('email=admin%40example.test&password=secret-password&csrf='.$beforeLogin));
        $response = $app->handle($request);
        self::assertSame(302, $response->getStatusCode()); self::assertSame('/admin', $response->getHeaderLine('Location'));
        // EN: Signing in rotates the token, so a value seen before authentication stops working.
        // PT: O login troca o token; um valor visto antes da autenticação deixa de valer.
        self::assertNotSame($beforeLogin, CsrfToken::current());
        self::assertFalse(CsrfToken::isValid($beforeLogin));
    }
}
