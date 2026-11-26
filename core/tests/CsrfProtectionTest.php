<?php

declare(strict_types=1);

namespace UsinaDocs\Core\Tests;

use PHPUnit\Framework\TestCase;
use Slim\App;
use Slim\Psr7\Factory\ServerRequestFactory;
use UsinaDocs\Core\AppFactory;
use UsinaDocs\Core\Infrastructure\Database;
use UsinaDocs\Core\Infrastructure\Schema;
use UsinaDocs\Core\Security\CsrfToken;

/**
 * EN: Guards the application-wide CSRF middleware. The regression it prevents
 * is a state-changing route that accepts a POST from a signed-in browser
 * without proving that the form came from one of our own pages. Before this
 * middleware, routes such as page creation and publication had no such check.
 *
 * PT: Protege o middleware de CSRF de toda a aplicação. A regressão evitada é
 * uma rota que altera estado aceitar um POST de um navegador autenticado sem
 * provar que o formulário veio de uma das nossas páginas. Antes deste
 * middleware, rotas como criação e publicação de páginas não tinham essa
 * verificação.
 */
final class CsrfProtectionTest extends TestCase
{
    private string $databasePath;
    private App $app;

    protected function setUp(): void
    {
        $this->databasePath = sys_get_temp_dir().DIRECTORY_SEPARATOR.'usinadocs-csrf-'.bin2hex(random_bytes(8)).'.sqlite';
        $database = Database::connect($this->databasePath);
        Schema::migrate($database, dirname(__DIR__));
        Schema::seed($database);
        $this->app = AppFactory::create(dirname(__DIR__), $this->databasePath);
        $_SESSION = ['user' => ['role' => 'administrator', 'name' => 'Administrador']];
    }

    protected function tearDown(): void
    {
        $_SESSION = [];
        if (is_file($this->databasePath)) {
            @unlink($this->databasePath);
        }
    }

    public function test_state_changing_requests_need_the_session_token(): void
    {
        $page = ['title' => 'Página CSRF', 'slug' => 'pagina-csrf', 'summary' => '', 'content_type' => 'lesson', 'blocks_json' => '[]'];

        // EN: A forged form carries no token or someone else's token: both are refused before the route runs.
        // PT: Um formulário forjado não traz token ou traz o de outra pessoa: ambos são recusados antes da rota.
        self::assertSame(403, $this->post('/admin/pages', $page)->getStatusCode());
        self::assertSame(403, $this->post('/admin/pages', $page + ['csrf' => str_repeat('0', 64)])->getStatusCode());
        self::assertSame(0, $this->pagesWithSlug('pagina-csrf'));

        // EN: The token printed in our own forms is accepted, from the body field or from the header.
        // PT: O token impresso nos nossos formulários é aceito, pelo campo do corpo ou pelo cabeçalho.
        self::assertSame(302, $this->post('/admin/pages', $page + ['csrf' => CsrfToken::current()])->getStatusCode());
        self::assertSame(1, $this->pagesWithSlug('pagina-csrf'));
        $byHeader = ['slug' => 'pagina-csrf-2', 'title' => 'Outra'] + $page;
        self::assertSame(302, $this->post('/admin/pages', $byHeader, [CsrfToken::HEADER => CsrfToken::current()])->getStatusCode());
    }

    public function test_safe_methods_and_routing_errors_are_unchanged(): void
    {
        $factory = new ServerRequestFactory();

        // EN: Reading pages never needs a token.
        // PT: Ler páginas nunca exige token.
        self::assertSame(200, $this->app->handle($factory->createServerRequest('GET', '/admin'))->getStatusCode());

        // EN: Routing runs before the CSRF check, so unknown paths and wrong methods keep their own status.
        // PT: O roteamento roda antes da verificação de CSRF; caminhos inexistentes e métodos errados mantêm seu status.
        self::assertSame(404, $this->app->handle($factory->createServerRequest('POST', '/nao-existe'))->getStatusCode());
        self::assertSame(405, $this->app->handle($factory->createServerRequest('POST', '/admin/translations'))->getStatusCode());
    }

    public function test_every_post_form_in_the_dashboard_carries_the_token(): void
    {
        // EN: The dashboard holds the publish, revision, visibility and sign-out forms.
        // PT: O painel reúne os formulários de publicação, revisão, visibilidade e saída.
        $html = (string) $this->app->handle((new ServerRequestFactory())->createServerRequest('GET', '/admin'))->getBody();
        $forms = preg_match_all('/<form\b[^>]*method="post"[^>]*>/i', $html);
        $tokens = substr_count($html, 'name="csrf" value="'.CsrfToken::current().'"');

        self::assertGreaterThan(0, $forms);
        self::assertSame($forms, $tokens);
    }

    /** @param array<string, mixed> $body @param array<string, string> $headers */
    private function post(string $path, array $body, array $headers = []): \Psr\Http\Message\ResponseInterface
    {
        $request = (new ServerRequestFactory())->createServerRequest('POST', $path)->withParsedBody($body);
        foreach ($headers as $name => $value) {
            $request = $request->withHeader($name, $value);
        }

        return $this->app->handle($request);
    }

    private function pagesWithSlug(string $slug): int
    {
        $statement = Database::connect($this->databasePath)->prepare('SELECT COUNT(*) FROM page_localizations WHERE slug = :slug');
        $statement->execute(['slug' => $slug]);

        return (int) $statement->fetchColumn();
    }
}
