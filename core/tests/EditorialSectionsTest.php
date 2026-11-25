<?php

declare(strict_types=1);

namespace UsinaDocs\Core\Tests;

use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;
use Slim\App;
use Slim\Psr7\Factory\ServerRequestFactory;
use UsinaDocs\Core\AppFactory;
use UsinaDocs\Core\Content\ArticleSections;
use UsinaDocs\Core\Content\AuthorDirectory;
use UsinaDocs\Core\Content\EditorialService;
use UsinaDocs\Core\Infrastructure\Database;
use UsinaDocs\Core\Infrastructure\Schema;

final class EditorialSectionsTest extends TestCase
{
    private string $database;
    private App $app;
    private ArticleSections $sections;
    private EditorialService $editorial;

    protected function setUp(): void
    {
        $this->database = tempnam(sys_get_temp_dir(), 'usinadocs-sections-');
        $db = Database::connect($this->database);
        $root = dirname(__DIR__);
        Schema::migrate($db, $root);
        Schema::seed($db);
        $this->sections = new ArticleSections($db);
        $this->editorial = new EditorialService($db);
        $this->app = AppFactory::create($root, $this->database);
        $_SESSION = ['user' => ['role' => 'editor']];
    }

    protected function tearDown(): void
    {
        $_SESSION = [];
        unlink($this->database);
    }

    public function test_blocking_preserves_assignments_and_prevents_new_ones(): void
    {
        // Exercise management and article classification through the actual form routes.
        // Exercita o cadastro e a classificação de artigos pelas rotas reais dos formulários.
        $path = '/admin/sections';
        $body = ['slug' => 'infra', 'title_pt' => 'Infraestrutura', 'title_en' => 'Infrastructure', 'title_es' => 'Infraestructura', 'blocked' => '0'];
        self::assertSame(403, $this->request('POST', $path, $body)->getStatusCode());
        self::assertSame(200, $this->request('GET', $path)->getStatusCode());
        $body['csrf'] = $_SESSION['section_csrf'];
        self::assertSame(303, $this->request('POST', $path, $body)->getStatusCode());
        self::assertSame(422, $this->request('POST', $path, $body)->getStatusCode());
        self::assertSame(422, $this->request('POST', $path, array_replace($body, ['slug'=>'other', 'title_en'=>'']))->getStatusCode());

        $article = ['title'=>'Artigo', 'slug'=>'artigo', 'summary'=>'Resumo', 'content_type'=>'article', 'article_section'=>'infra'];
        self::assertSame(302, $this->request('POST', '/admin/pages', $article)->getStatusCode());
        $page = $this->editorial->catalog('Artigo')[0]['id'];
        self::assertSame('infra', $this->sections->sectionFor($page));

        $body['blocked'] = '1';
        $body['title_en'] = 'Infrastructure and operations';
        self::assertSame(303, $this->request('POST', $path.'/infra', $body)->getStatusCode());
        self::assertSame([], $this->sections->availableFor());
        $edit = (string) $this->request('GET', '/admin/pages/'.$page.'/edit', query: ['lang'=>'en','theme'=>'dark'])->getBody();
        self::assertStringContainsString('value="infra" selected>Infrastructure and operations (Blocked)', $edit);
        self::assertSame(302, $this->request('POST', '/admin/pages/'.$page, $article)->getStatusCode());
        self::assertSame('infra', $this->sections->sectionFor($page));
        $invalid = $this->request('POST', '/admin/pages', array_replace($article, ['slug'=>'other-article', 'title'=>'Other article']));
        self::assertSame(422, $invalid->getStatusCode());
        self::assertStringContainsString('value="Other article"', (string) $invalid->getBody());
        self::assertSame([], $this->editorial->catalog('Other article'));

        $body['blocked'] = '0';
        self::assertSame(303, $this->request('POST', $path.'/infra', $body)->getStatusCode());
        self::assertSame('infra', $this->sections->availableFor()[0]['slug']);
    }

    public function test_the_shared_preferences_keep_article_edition_separate_from_editor_language(): void
    {
        // Switching the interface on an English draft must leave the save path pointing at that draft.
        // Trocar a interface no rascunho em inglês mantém a gravação apontada para esse rascunho.
        $page = $this->editorial->create('Artigo', 'artigo', '', '', '', '', 'article');
        $this->editorial->createTranslation($page, 'en', 'article');
        (new AuthorDirectory(Database::connect($this->database)))->save(null, ['slug'=>'autor-teste','name'=>'Autor de teste','bio_pt'=>'Texto']);
        $paths = [
            '/admin/pages/'.$page.'/en/edit' => 'Editar borrador EN',
            '/admin/authors/autor-teste' => 'Editar biografía',
            '/admin/media' => 'Biblioteca multimedia',
        ];
        foreach ($paths as $path => $heading) {
            $response = $this->request('GET', $path, query: ['lang'=>'es', 'theme'=>'dark']);
            self::assertSame(200, $response->getStatusCode());
            $html = (string) $response->getBody();
            self::assertStringContainsString($heading, $html);
            self::assertStringContainsString('href="?lang=pt"', $html);
            self::assertStringContainsString('class="reader-theme"', $html);
            self::assertStringContainsString('data-theme="dark"', $html);
            self::assertStringContainsString('data-warn-unsaved', $html);
        }
        self::assertStringContainsString('action="/admin/pages/'.$page.'/en"', (string) $this->request('GET', array_key_first($paths))->getBody());
    }

    private function request(string $method, string $path, array $body = [], array $query = []): ResponseInterface
    {
        $request = (new ServerRequestFactory())->createServerRequest($method, $path);
        return $this->app->handle($request->withParsedBody($body)->withQueryParams($query));
    }
}
