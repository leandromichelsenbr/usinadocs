<?php

declare(strict_types=1);

namespace UsinaDocs\Core\Tests;

use UsinaDocs\Core\Security\CsrfToken;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ServerRequestInterface;
use Slim\App;
use Slim\Psr7\Factory\ServerRequestFactory;
use UsinaDocs\Core\AppFactory;
use UsinaDocs\Core\Content\EditorialService;
use UsinaDocs\Core\Infrastructure\Database;
use UsinaDocs\Core\Infrastructure\Schema;

final class LegalDocumentTest extends TestCase
{
    private string $path;
    private App $app;

    protected function setUp(): void
    {
        $this->path = tempnam(sys_get_temp_dir(), 'usinadocs-legal-');
        $db = Database::connect($this->path);
        Schema::migrate($db, dirname(__DIR__));
        Schema::seed($db);
        $this->app = AppFactory::create(dirname(__DIR__), $this->path);
        $_SESSION = [];
    }

    protected function tearDown(): void
    {
        $_SESSION = [];
        unlink($this->path);
    }

    public function test_legal_editing_requires_an_administrator_and_a_session_token(): void
    {
        // Verify the navigation entry and both read/write endpoints enforce the same role.
        // Verifica se a opção do menu e as rotas de consulta e gravação exigem o mesmo papel.
        $route = '/admin/legal/privacidade';
        self::assertSame(403, $this->app->handle($this->request('GET', $route))->getStatusCode());
        $_SESSION['user'] = ['role' => 'editor', 'name' => 'Editor'];
        self::assertSame(403, $this->app->handle($this->request('GET', '/admin/legal'))->getStatusCode());
        self::assertStringNotContainsString('href="/admin/legal"', (string) $this->app->handle($this->request('GET', '/admin'))->getBody());
        self::assertSame(403, $this->app->handle($this->request('POST', $route))->getStatusCode());

        $_SESSION['user'] = ['role' => 'administrator', 'name' => 'Admin'];
        self::assertStringContainsString('href="/admin/legal"', (string) $this->app->handle($this->request('GET', '/admin'))->getBody());
        $form = $this->app->handle($this->request('GET', $route));
        self::assertSame(200, $form->getStatusCode());
        self::assertStringContainsString('name="editions[es][body]"', (string) $form->getBody());
        self::assertSame(403, $this->app->handle($this->request('POST', $route)->withParsedBody(['csrf' => 'wrong']))->getStatusCode());
    }

    public function test_draft_stays_private_until_the_three_editions_are_published_together(): void
    {
        // An incomplete or revised draft must never change the published document or its footer label.
        // Um rascunho incompleto ou revisado nunca deve alterar o documento publicado nem seu rótulo no rodapé.
        $_SESSION['user'] = ['role' => 'administrator', 'name' => 'Admin'];
        $route = '/admin/legal/privacidade';
        $this->app->handle($this->request('GET', $route));
        $post = $this->request('POST', $route);
        $page = $this->request('GET', '/pt/p/bem-vindo');
        $editions = [
            'pt' => ['title' => 'Privacidade', 'body' => "Primeira linha\n<p>Segunda linha</p>"],
            'en' => ['title' => 'Privacy', 'body' => 'English body'],
            'es' => ['title' => 'Privacidad', 'body' => 'Texto en español'],
        ];
        $partial = ['csrf' => CsrfToken::current(), 'editions' => ['pt' => $editions['pt']]];
        self::assertSame(422, $this->app->handle($post->withParsedBody($partial + ['action' => 'publish']))->getStatusCode());
        self::assertSame(303, $this->app->handle($post->withParsedBody($partial + ['action' => 'draft']))->getStatusCode());
        self::assertSame(404, $this->app->handle($this->request('GET', '/pt/p/legal-privacidade'))->getStatusCode());
        self::assertStringNotContainsString('href="/pt/p/legal-privacidade"', (string) $this->app->handle($page)->getBody());

        $published = $this->app->handle($post->withParsedBody([
            'csrf' => CsrfToken::current(), 'editions' => $editions, 'action' => 'publish',
        ]));
        self::assertSame(303, $published->getStatusCode());
        foreach ($editions as $locale => $edition) {
            $response = $this->app->handle($this->request('GET', "/$locale/p/legal-privacidade"));
            self::assertSame(200, $response->getStatusCode());
            self::assertStringContainsString('<h1>'.$edition['title'].'</h1>', (string) $response->getBody());
            self::assertStringContainsString(htmlspecialchars($edition['body']), (string) $response->getBody());
            // The shared switcher retains the document identity across languages.
            // O seletor compartilhado mantém a identidade do documento ao trocar de idioma.
            self::assertStringContainsString('href="/es/p/legal-privacidade?lang=es"', (string) $response->getBody());
            self::assertStringContainsString('class="reader-theme"', (string) $response->getBody());
        }
        $switch = $this->request('GET', '/pt/p/legal-privacidade')->withQueryParams(['lang' => 'en']);
        self::assertSame('/en/p/legal-privacidade', $this->app->handle($switch)->getHeaderLine('Location'));

        $editions['pt']['title'] = 'Rascunho de alteração';
        self::assertSame(303, $this->app->handle($post->withParsedBody([
            'csrf' => CsrfToken::current(), 'editions' => $editions, 'action' => 'draft',
        ]))->getStatusCode());
        $body = (string) $this->app->handle($page)->getBody();
        self::assertStringContainsString('href="/pt/p/legal-privacidade">Privacidade</a>', $body);
        self::assertStringContainsString('<h1>Privacidade</h1>', (string) $this->app->handle($this->request('GET', '/pt/p/legal-privacidade'))->getBody());
        self::assertStringContainsString('Publicado com rascunho atualizado', (string) $this->app->handle($this->request('GET', $route))->getBody());
    }

    public function test_an_additional_legal_document_can_be_created_without_overwriting_an_existing_one(): void
    {
        // Creating a custom document leaves a previously saved document with the same address intact.
        // Criar um documento personalizado preserva um documento já salvo com o mesmo endereço.
        $_SESSION['user'] = ['role' => 'administrator'];
        self::assertSame(200, $this->app->handle($this->request('GET', '/admin/legal/new'))->getStatusCode());
        $new = $this->request('POST', '/admin/legal')->withParsedBody([
            'csrf' => CsrfToken::current(), 'slug' => 'direitos-autorais', 'action' => 'draft',
            'editions' => ['pt' => ['title' => 'Direitos autorais']],
        ]);
        self::assertSame('/admin/legal/direitos-autorais?saved=draft', $this->app->handle($new)->getHeaderLine('Location'));
        self::assertSame(422, $this->app->handle($new)->getStatusCode());
        self::assertSame(200, $this->app->handle($this->request('GET', '/admin/legal/direitos-autorais'))->getStatusCode());
    }

    public function test_legal_publication_preserves_existing_editorial_urls(): void
    {
        // An editorial page published before the feature keeps its address and blocks a conflicting legal publication.
        // Uma página editorial publicada antes do recurso conserva seu endereço e impede uma publicação legal conflitante.
        $editorial = new EditorialService(Database::connect($this->path));
        $page = $editorial->create('Página editorial', 'legal-privacidade', '', '', '', '', 'reference');
        $editorial->publish($page);
        $_SESSION['user'] = ['role' => 'administrator'];
        $this->app->handle($this->request('GET', '/admin/legal/privacidade'));
        $publication = $this->request('POST', '/admin/legal/privacidade')->withParsedBody([
            'csrf' => CsrfToken::current(), 'action' => 'publish',
            'editions' => array_fill_keys(['pt', 'en', 'es'], ['title' => 'Documento legal', 'body' => 'Texto trilíngue']),
        ]);

        self::assertSame(422, $this->app->handle($publication)->getStatusCode());
        $public = $this->app->handle($this->request('GET', '/pt/p/legal-privacidade'));
        self::assertSame(200, $public->getStatusCode());
        self::assertStringContainsString('<h1>Página editorial</h1>', (string) $public->getBody());
    }

    private function request(string $method, string $path): ServerRequestInterface
    {
        return (new ServerRequestFactory())->createServerRequest($method, $path);
    }
}
