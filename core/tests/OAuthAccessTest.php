<?php

declare(strict_types=1);

namespace UsinaDocs\Core\Tests;

use PHPUnit\Framework\TestCase;
use Slim\Psr7\Factory\ServerRequestFactory;
use UsinaDocs\Core\AppFactory;
use UsinaDocs\Core\Security\CsrfToken;
use UsinaDocs\Core\Authentication\OAuthAccounts;
use UsinaDocs\Core\Infrastructure\Database;
use UsinaDocs\Core\Infrastructure\Schema;

final class OAuthAccessTest extends TestCase
{
    private string $databasePath;
    private string $configPath;
    private array $previousEnvironment;
    private OAuthAccounts $accounts;

    protected function setUp(): void
    {
        $this->databasePath = tempnam(sys_get_temp_dir(), 'usinadocs-oauth-db-');
        $this->configPath = tempnam(sys_get_temp_dir(), 'usinadocs-oauth-config-');
        $this->previousEnvironment = $_ENV;
        $_ENV['USINADOCS_AUTH_MODE'] = 'oauth';
        $_ENV['USINADOCS_OAUTH_CONFIG'] = $this->configPath;
        file_put_contents($this->configPath, json_encode([
            'origin' => 'https://news.example.test',
            'google' => ['client_id' => 'google-example', 'client_secret' => 'test'],
            'microsoft' => ['client_id' => 'microsoft-example', 'client_secret' => 'test'],
        ], JSON_THROW_ON_ERROR));
        $database = Database::connect($this->databasePath);
        Schema::migrate($database, dirname(__DIR__));
        Schema::seed($database);
        $this->accounts = new OAuthAccounts($database);
    }

    protected function tearDown(): void
    {
        $_SESSION = [];
        $_ENV = $this->previousEnvironment;
        unlink($this->databasePath);
        unlink($this->configPath);
    }

    public function test_an_invitation_requires_verified_google_and_is_claimed_once(): void
    {
        $email = 'admin@example.test';
        $this->accounts->inviteAdministrator($email);
        $microsoft = $this->accounts->signIn('microsoft', 'm1', 'Microsoft', $email, true);
        $unverified = $this->accounts->signIn('google', 'g1', 'Unverified', $email, false);
        $administrator = $this->accounts->signIn('google', 'g2', 'Administrator', $email, true);
        $otherGoogle = $this->accounts->signIn('google', 'g3', 'Other', $email, true);

        self::assertSame('reader', $microsoft['role']);
        self::assertSame('reader', $unverified['role']);
        self::assertSame('administrator', $administrator['role']);
        self::assertSame('reader', $otherGoogle['role']);
        self::assertFalse($this->accounts->changeRole($administrator['id'], 'reader'));
        self::assertSame($administrator['id'], $this->accounts->signIn('google', 'g2', 'Admin', 'new@example.test', true)['id']);
    }

    public function test_routes_enforce_fresh_roles_origin_and_oauth_state(): void
    {
        $app = AppFactory::create(dirname(__DIR__), $this->databasePath);
        $factory = new ServerRequestFactory();
        $request = static fn (string $method, string $path) => $factory->createServerRequest($method, 'https://news.example.test'.$path);
        $email = 'admin@example.test';
        $this->accounts->inviteAdministrator($email);
        $administrator = $this->accounts->signIn('google', 'admin', 'Administrator', $email, true);
        $reader = $this->accounts->signIn('microsoft', 'reader', 'Reader', null, false);

        $_SESSION = ['oauth_user_id' => $reader['id']];
        self::assertSame(403, $app->handle($request('GET', '/admin'))->getStatusCode());
        self::assertTrue($this->accounts->changeRole($reader['id'], 'editor'));
        self::assertSame(200, $app->handle($request('GET', '/admin'))->getStatusCode());
        self::assertSame(403, $app->handle($request('GET', '/admin/users'))->getStatusCode());

        $_SESSION = ['oauth_user_id' => $administrator['id']];
        // EN: The token is valid, so the first 403 below comes from the OAuth Origin check.
        // PT: O token é válido; por isso o primeiro 403 abaixo vem da verificação de Origin do OAuth.
        $manage = $request('POST', '/admin/users/'.$reader['id'].'/group')->withParsedBody(['group' => 'reader'])->withHeader(CsrfToken::HEADER, CsrfToken::current());
        self::assertSame(403, $app->handle($manage)->getStatusCode());
        self::assertSame(200, $app->handle($request('GET', '/admin/users'))->getStatusCode());
        self::assertSame(303, $app->handle($manage->withHeader('Origin', 'https://news.example.test'))->getStatusCode());

        $_SESSION = [];
        self::assertSame('/login', $app->handle($request('GET', '/admin'))->getHeaderLine('Location'));
        $login = $app->handle($request('GET', '/login'));
        self::assertStringContainsString('Entrar com Google', (string) $login->getBody());
        self::assertStringContainsString('Entrar com Microsoft', (string) $login->getBody());
        self::assertSame(405, $app->handle($request('POST', '/login'))->getStatusCode());
        $begin = $app->handle($request('GET', '/auth/google'));
        $beginUrl = $begin->getHeaderLine('Location');
        self::assertStringContainsString('redirect_uri=https%3A%2F%2Fnews.example.test%2Fauth%2Fgoogle%2Fcallback', $beginUrl);
        self::assertStringContainsString('code_challenge_method=S256', $beginUrl);
        self::assertSame(403, $app->handle($request('GET', '/auth/google/callback'))->getStatusCode());
    }

    public function test_comment_sign_in_keeps_only_a_local_article_destination(): void
    {
        // Exercise continuation through the real provider route without calling a remote identity service.
        // Exercita o retorno pela rota real do provedor sem chamar um serviço remoto de identidade.
        $_SESSION = [];
        $app = AppFactory::create(dirname(__DIR__), $this->databasePath);
        $factory = new ServerRequestFactory();
        $login = $factory->createServerRequest('GET', '/login');
        $provider = $factory->createServerRequest('GET', '/auth/google');
        $callback = $factory->createServerRequest('GET', '/auth/google/callback');
        $next = '/es/p/bienvenido#comments';
        $params = ['next' => $next];

        $form = $app->handle($login->withQueryParams($params));
        self::assertStringContainsString('/auth/google?next='.rawurlencode($next), (string) $form->getBody());
        // Changing either preference must keep the validated comment destination and the sign-in error.
        // Alterar qualquer preferência deve preservar o retorno validado ao comentário e o erro de login.
        $retryForm = (string) $app->handle($login->withQueryParams(['next' => $next, 'error' => 'oauth']))->getBody();
        $query = '?next='.rawurlencode($next).'&amp;error=oauth&amp;';
        self::assertStringContainsString('href="'.$query.'lang=es"', $retryForm);
        self::assertStringContainsString('href="'.$query.'theme=dark"', $retryForm);
        self::assertStringNotContainsString('class="reader-settings"', $retryForm);
        self::assertSame(302, $app->handle($provider->withQueryParams($params))->getStatusCode());
        self::assertSame($next, $_SESSION['oauth_pending']['google']['next']);
        $retry = $app->handle($callback->withQueryParams(['state' => $_SESSION['oauth_pending']['google']['state']]));
        self::assertSame('/login?error=oauth&next='.rawurlencode($next), $retry->getHeaderLine('Location'));

        $app->handle($provider->withQueryParams(['next' => '//example.test/es/p/bienvenido#comments']));
        self::assertNull($_SESSION['oauth_pending']['google']['next']);
    }
}
