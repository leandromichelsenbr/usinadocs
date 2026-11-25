<?php

declare(strict_types=1);

namespace UsinaDocs\Core\Tests;

use PHPUnit\Framework\TestCase;
use Slim\App;
use Slim\Psr7\Factory\ServerRequestFactory;
use UsinaDocs\Core\AppFactory;
use UsinaDocs\Core\Content\EditorialService;
use UsinaDocs\Core\Infrastructure\Database;
use UsinaDocs\Core\Infrastructure\Schema;

final class EditorialMaintenanceTest extends TestCase
{
    private string $database;
    private App $app;
    private EditorialService $editorial;
    private string $page;

    protected function setUp(): void
    {
        $this->database = tempnam(sys_get_temp_dir(), 'usinadocs-maintenance-');
        $db = Database::connect($this->database);
        $root = dirname(__DIR__);
        Schema::migrate($db, $root);
        Schema::seed($db);
        $this->editorial = new EditorialService($db);
        $this->page = $this->editorial->create('Manutenção editorial', 'manutencao-editorial', '', 'Conteúdo', '', '', 'article');
        $this->editorial->publish($this->page);
        $this->editorial->createTranslation($this->page, 'en', 'editorial-maintenance');
        $this->editorial->publishLocalized($this->page, 'en');
        $this->app = AppFactory::create($root, $this->database);
        $_SESSION = ['user' => ['role' => 'editor', 'name' => 'Editor']];
    }

    protected function tearDown(): void
    {
        $_SESSION = [];
        unlink($this->database);
    }

    public function test_hiding_survives_republication_and_keeps_editorial_preview(): void
    {
        // The visibility flag covers all translations and is independent of revision publication.
        // A visibilidade vale para todas as traduções e independe da publicação de revisões.
        $dashboard = (string) $this->request('GET', '/admin', query: ['lang' => 'en', 'theme' => 'dark', 'q' => 'Manutenção'])->getBody();
        self::assertStringContainsString('Editorial catalog', $dashboard);
        self::assertStringContainsString('data-theme="dark"', $dashboard);
        self::assertStringContainsString('q=Manuten%C3%A7%C3%A3o&amp;lang=es', $dashboard);
        self::assertStringContainsString('class="reader-theme"', $dashboard);
        $path = '/admin/pages/'.$this->page;
        $token = $_SESSION['editorial_maintenance_csrf'];
        self::assertSame(403, $this->request('POST', $path.'/visibility', ['hidden' => '1'])->getStatusCode());
        self::assertSame(303, $this->request('POST', $path.'/visibility', ['hidden' => '1', 'csrf' => $token])->getStatusCode());
        $public = ['/pt/p/manutencao-editorial', '/en/p/editorial-maintenance'];
        foreach ($public as $url) {
            self::assertSame(404, $this->request('GET', $url)->getStatusCode());
        }
        self::assertSame(200, $this->request('GET', $path.'/preview/pt')->getStatusCode());
        $this->editorial->createRevision($this->page);
        $this->editorial->publish($this->page);
        self::assertSame(404, $this->request('GET', $public[0])->getStatusCode());
        self::assertStringContainsString('Oculto do público', (string) $this->request('GET', '/admin')->getBody());
        self::assertSame(303, $this->request('POST', $path.'/visibility', ['hidden' => '0', 'csrf' => $token])->getStatusCode());
        foreach ($public as $url) {
            self::assertSame(200, $this->request('GET', $url)->getStatusCode());
        }
    }

    public function test_history_protects_current_revision_and_discards_only_the_confirmed_draft(): void
    {
        // Archive a superseded snapshot without deleting it, then reject a stale discard confirmation.
        // Arquiva uma revisão substituída sem apagá-la e rejeita uma confirmação de exclusão desatualizada.
        $old = $this->editorial->revisionHistory($this->page, 'pt')[0]['id'];
        $this->editorial->createRevision($this->page);
        $this->editorial->updateDraft($this->page, 'Edição atual', '', 'Conteúdo atualizado', '');
        $this->editorial->publish($this->page);
        $current = $this->editorial->revisionHistory($this->page, 'pt')[0]['id'];
        $path = '/admin/pages/'.$this->page;
        $history = $path.'/history/pt';
        $this->request('GET', $history);
        $body = ['archived' => '1', 'csrf' => $_SESSION['editorial_maintenance_csrf']];
        self::assertSame(409, $this->request('POST', $path.'/revisions/pt/'.$current.'/archive', $body)->getStatusCode());
        self::assertSame(303, $this->request('POST', $path.'/revisions/pt/'.$old.'/archive', $body)->getStatusCode());
        self::assertStringNotContainsString('Manutenção editorial', (string) $this->request('GET', $history)->getBody());
        self::assertStringContainsString('Manutenção editorial', (string) $this->request('GET', $history, query: ['archived' => '1'])->getBody());
        $body['archived'] = '0';
        self::assertSame(303, $this->request('POST', $path.'/revisions/pt/'.$old.'/archive', $body)->getStatusCode());

        $draft = $this->editorial->createRevision($this->page);
        $discard = $path.'/revisions/pt/discard';
        self::assertStringContainsString('Excluir rascunho', (string) $this->request('GET', '/admin')->getBody());
        self::assertStringContainsString('value="'.$draft.'"', (string) $this->request('GET', $discard)->getBody());
        $body['revision'] = $old;
        self::assertSame(409, $this->request('POST', $discard, $body)->getStatusCode());
        $body['revision'] = $draft;
        self::assertSame(303, $this->request('POST', $discard, $body)->getStatusCode());
        self::assertNull($this->editorial->draft($this->page));
        self::assertStringContainsString('Edição atual', (string) $this->request('GET', '/pt/p/manutencao-editorial')->getBody());
    }

    private function request(string $method, string $path, array $body = [], array $query = []): \Psr\Http\Message\ResponseInterface
    {
        $factory = new ServerRequestFactory();
        return $this->app->handle($factory->createServerRequest($method, $path)->withParsedBody($body)->withQueryParams($query));
    }
}
