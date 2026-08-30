<?php

declare(strict_types=1);

namespace UsinaDocs\Core\Tests;

use PHPUnit\Framework\TestCase;
use Slim\Psr7\Factory\ServerRequestFactory;
use UsinaDocs\Core\AppFactory;
use UsinaDocs\Core\Content\EditorialService;
use UsinaDocs\Core\Infrastructure\Database;
use UsinaDocs\Core\Infrastructure\Schema;

final class SprintOneEditorialFlowTest extends TestCase
{
    private string $databasePath;

    protected function setUp(): void
    {
        $this->databasePath = sys_get_temp_dir().DIRECTORY_SEPARATOR.'usinadocs-sprint-one-'.bin2hex(random_bytes(8)).'.sqlite';
        $database = Database::connect($this->databasePath);
        Schema::migrate($database, dirname(__DIR__));
        Schema::seed($database);
        $_SESSION = ['user' => ['role' => 'administrator', 'name' => 'Editor Sprint 1']];
    }

    protected function tearDown(): void
    {
        $_SESSION = [];
        if (is_file($this->databasePath)) {
            @unlink($this->databasePath);
        }
    }

    public function test_editor_completes_the_sprint_one_page_lifecycle_without_losing_history(): void
    {
        $app = AppFactory::create(dirname(__DIR__), $this->databasePath);
        $_SESSION = ['user' => ['role' => 'administrator', 'name' => 'Editor Sprint 1']];
        $initialBlocks = [
            ['type' => 'heading', 'data' => ['text' => 'Primeiros passos']],
            ['type' => 'text', 'data' => ['body' => 'Conteúdo inicial da Sprint 1.']],
            ['type' => 'code', 'data' => ['code' => "User Function SprintUm()\nReturn"]],
        ];

        $create = (new ServerRequestFactory())
            ->createServerRequest('POST', '/admin/pages')
            ->withParsedBody([
                'title' => 'Fluxo editorial da Sprint 1',
                'slug' => 'fluxo-sprint-1',
                'summary' => 'Página simples criada no fluxo de aceite.',
                'content_type' => 'lesson',
                'blocks_json' => json_encode($initialBlocks, JSON_THROW_ON_ERROR),
            ]);
        $response = $app->handle($create);
        self::assertSame(302, $response->getStatusCode());
        self::assertSame('/admin', $response->getHeaderLine('Location'));

        $database = Database::connect($this->databasePath);
        $pageId = (string) $database->query("SELECT page_id FROM page_localizations WHERE slug='fluxo-sprint-1' AND language_code='pt'")->fetchColumn();
        self::assertNotSame('', $pageId);
        $editorial = new EditorialService($database);

        $edit = $app->handle((new ServerRequestFactory())->createServerRequest('GET', '/admin/pages/'.$pageId.'/edit'));
        $editBody = (string) $edit->getBody();
        self::assertSame(200, $edit->getStatusCode());
        self::assertStringContainsString('Fluxo editorial da Sprint 1', $editBody);
        $reopenedBlocks = $editorial->blocksForDraft($pageId, 'pt');
        self::assertSame(['heading', 'text', 'code'], array_column($reopenedBlocks, 'type'));
        self::assertSame('Primeiros passos', $reopenedBlocks[0]['data']['text']);
        self::assertSame('Conteúdo inicial da Sprint 1.', $reopenedBlocks[1]['data']['body']);
        self::assertSame("User Function SprintUm()\nReturn", $reopenedBlocks[2]['data']['code']);

        $publish = $app->handle((new ServerRequestFactory())->createServerRequest('POST', '/admin/pages/'.$pageId.'/publish'));
        self::assertSame('/admin', $publish->getHeaderLine('Location'));
        $public = $app->handle((new ServerRequestFactory())->createServerRequest('GET', '/pt/p/fluxo-sprint-1'));
        $publicBody = (string) $public->getBody();
        self::assertSame(200, $public->getStatusCode());
        self::assertStringContainsString('Fluxo editorial da Sprint 1', $publicBody);
        self::assertStringContainsString('Primeiros passos', $publicBody);
        self::assertStringContainsString('Conteúdo inicial da Sprint 1.', $publicBody);
        self::assertStringContainsString('User Function SprintUm()', $publicBody);

        $revision = $app->handle((new ServerRequestFactory())->createServerRequest('POST', '/admin/pages/'.$pageId.'/revisions/pt'));
        self::assertSame('/admin/pages/'.$pageId.'/edit', $revision->getHeaderLine('Location'));
        $updatedBlocks = $initialBlocks;
        $updatedBlocks[1]['data']['body'] = 'Conteúdo atualizado na revisão 2.';
        $updatedBlocks = [$updatedBlocks[2], $updatedBlocks[0], $updatedBlocks[1]];
        $saveRevision = (new ServerRequestFactory())
            ->createServerRequest('POST', '/admin/pages/'.$pageId)
            ->withParsedBody([
                'title' => 'Fluxo editorial da Sprint 1 — revisão 2',
                'summary' => 'Segunda revisão da página simples.',
                'content_type' => 'lesson',
                'blocks_json' => json_encode($updatedBlocks, JSON_THROW_ON_ERROR),
            ]);
        self::assertSame('/admin', $app->handle($saveRevision)->getHeaderLine('Location'));

        $publicBeforeSecondPublish = (string) $app->handle((new ServerRequestFactory())->createServerRequest('GET', '/pt/p/fluxo-sprint-1'))->getBody();
        self::assertStringContainsString('Conteúdo inicial da Sprint 1.', $publicBeforeSecondPublish);
        self::assertStringNotContainsString('Conteúdo atualizado na revisão 2.', $publicBeforeSecondPublish);

        $draftBlocks = $editorial->blocksForDraft($pageId, 'pt');
        self::assertCount(3, $draftBlocks);
        self::assertSame(['code', 'heading', 'text'], array_column($draftBlocks, 'type'));
        self::assertSame('Conteúdo atualizado na revisão 2.', $draftBlocks[2]['data']['body']);

        $blocksWithoutRequiredText = [$updatedBlocks[0], $updatedBlocks[1]];
        $saveInvalidRevision = (new ServerRequestFactory())
            ->createServerRequest('POST', '/admin/pages/'.$pageId)
            ->withParsedBody([
                'title' => 'Fluxo editorial da Sprint 1 — revisão incompleta',
                'summary' => 'Tentativa sem o bloco obrigatório de texto.',
                'content_type' => 'lesson',
                'blocks_json' => json_encode($blocksWithoutRequiredText, JSON_THROW_ON_ERROR),
            ]);
        self::assertSame('/admin', $app->handle($saveInvalidRevision)->getHeaderLine('Location'));
        self::assertSame(['code', 'heading'], array_column($editorial->blocksForDraft($pageId, 'pt'), 'type'));

        $blockedPublish = $app->handle((new ServerRequestFactory())->createServerRequest('POST', '/admin/pages/'.$pageId.'/publish'));
        self::assertSame(302, $blockedPublish->getStatusCode());
        self::assertSame('/admin/pages/'.$pageId.'/edit?missing=Explica%C3%A7%C3%A3o', $blockedPublish->getHeaderLine('Location'));
        $validationScreen = $app->handle(
            (new ServerRequestFactory())
                ->createServerRequest('GET', '/admin/pages/'.$pageId.'/edit')
                ->withQueryParams(['missing' => 'Explicação'])
        );
        self::assertSame(200, $validationScreen->getStatusCode());
        self::assertStringContainsString('Publicação bloqueada.', (string) $validationScreen->getBody());
        self::assertStringContainsString('Explicação', (string) $validationScreen->getBody());
        $publicAfterBlockedPublish = (string) $app->handle((new ServerRequestFactory())->createServerRequest('GET', '/pt/p/fluxo-sprint-1'))->getBody();
        self::assertStringContainsString('Conteúdo inicial da Sprint 1.', $publicAfterBlockedPublish);
        self::assertStringNotContainsString('revisão incompleta', $publicAfterBlockedPublish);

        $restoreValidRevision = (new ServerRequestFactory())
            ->createServerRequest('POST', '/admin/pages/'.$pageId)
            ->withParsedBody([
                'title' => 'Fluxo editorial da Sprint 1 — revisão 2',
                'summary' => 'Segunda revisão da página simples.',
                'content_type' => 'lesson',
                'blocks_json' => json_encode($updatedBlocks, JSON_THROW_ON_ERROR),
            ]);
        self::assertSame('/admin', $app->handle($restoreValidRevision)->getHeaderLine('Location'));

        $app->handle((new ServerRequestFactory())->createServerRequest('POST', '/admin/pages/'.$pageId.'/publish'));
        $history = $editorial->revisionHistory($pageId, 'pt');
        self::assertCount(2, $history);
        self::assertSame([2, 1], array_column($history, 'number'));
        self::assertTrue($history[0]['is_current']);
        self::assertFalse($history[1]['is_current']);

        $historyScreen = $app->handle((new ServerRequestFactory())->createServerRequest('GET', '/admin/pages/'.$pageId.'/history/pt'));
        self::assertSame(200, $historyScreen->getStatusCode());
        self::assertStringContainsString('Histórico de revisões', (string) $historyScreen->getBody());
        $publicAfterSecondPublish = (string) $app->handle((new ServerRequestFactory())->createServerRequest('GET', '/pt/p/fluxo-sprint-1'))->getBody();
        self::assertStringContainsString('Fluxo editorial da Sprint 1 — revisão 2', $publicAfterSecondPublish);
        self::assertStringContainsString('Conteúdo atualizado na revisão 2.', $publicAfterSecondPublish);
        self::assertLessThan(
            strpos($publicAfterSecondPublish, 'Conteúdo atualizado na revisão 2.'),
            strpos($publicAfterSecondPublish, 'User Function SprintUm()')
        );
    }
}
