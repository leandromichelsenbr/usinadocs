<?php

declare(strict_types=1);

namespace UsinaDocs\Core\Tests;

use PHPUnit\Framework\TestCase;
use Slim\Psr7\Factory\ServerRequestFactory;
use UsinaDocs\Core\AppFactory;
use UsinaDocs\Core\Content\EditorialService;
use UsinaDocs\Core\Content\ReusableArtifactService;
use UsinaDocs\Core\Infrastructure\Database;
use UsinaDocs\Core\Infrastructure\Schema;

final class ReferenceBibliographyTest extends TestCase
{
    public function test_public_bibliography_groups_legacy_and_reusable_references(): void
    {
        $root = dirname(__DIR__);
        $path = tempnam(sys_get_temp_dir(), 'usinadocs-references-');
        try {
            $db = Database::connect($path);
            Schema::migrate($db, $root);
            Schema::seed($db);
            $editor = new EditorialService($db);
            $artifacts = new ReusableArtifactService($db);
            // Mix old and reusable entries with body blocks to verify grouping and preserved editorial order.
            // Mistura entradas antigas e reutilizáveis com blocos do corpo para conferir agrupamento e ordem editorial.
            $source = $artifacts->create('source', 'reference', 'Source', '', [
                'title' => 'Relatório público',
                'url' => 'https://example.test/report',
                'author' => 'Ana Exemplo',
                'publisher' => 'Instituto Exemplo',
                'published_on' => '2026-11-01',
                'accessed_on' => '2026-11-27',
            ]);
            $artifacts->publish($source);
            $id = $editor->create('Article', 'article', '', '', '', '', 'article');
            $editor->replaceDraftBlocks($id, 'pt', [
                ['type' => 'reference', 'data' => ['title' => 'Fonte anterior', 'url' => 'https://example.test/legacy']],
                ['type' => 'text', 'data' => ['body' => 'Texto principal']],
                ['type' => 'reusable_artifact', 'data' => ['artifact_id' => $source]],
                ['type' => 'reference', 'data' => ['title' => 'Fonte incompleta', 'url' => 'javascript:alert(1)', 'published_on' => '2026-02-30']],
            ]);
            $editor->publish($id);
            $editor->createTranslation($id, 'en', 'article');
            $editor->publishLocalized($id, 'en');
            $editor->createTranslation($id, 'es', 'articulo');
            $editor->publishLocalized($id, 'es');

            $app = AppFactory::create($root, $path);
            $_SESSION = [];
            $factory = new ServerRequestFactory();
            foreach ([
                'pt/p/article' => ['Referências', 'Acesso em', '27/11/2026'],
                'en/p/article' => ['References', 'Accessed', 'Nov 27, 2026'],
                'es/p/articulo' => ['Referencias', 'Consultado el', '27/11/2026'],
            ] as $edition => [$heading, $accessLabel, $date]) {
                $response = $app->handle($factory->createServerRequest('GET', 'https://docs.example.test/'.$edition));
                $body = (string) $response->getBody();
                self::assertSame(200, $response->getStatusCode());
                self::assertSame(1, substr_count($body, 'id="references-heading"'));
                self::assertStringContainsString('>'.$heading.'</h2>', $body);
                self::assertStringContainsString('<a href="https://example.test/legacy">Fonte anterior</a>', $body);
                self::assertStringContainsString('Ana Exemplo.', $body);
                self::assertStringContainsString('Instituto Exemplo,', $body);
                self::assertStringContainsString('<time datetime="2026-11-01">', $body);
                self::assertStringContainsString($accessLabel.' <time datetime="2026-11-27">'.$date.'</time>', $body);
                self::assertStringContainsString('Fonte incompleta', $body);
                self::assertStringNotContainsString('javascript:alert', $body);
                self::assertStringNotContainsString('2026-02-30', $body);
                self::assertLessThan(strpos($body, 'Fonte anterior'), strpos($body, 'Texto principal'));
                self::assertLessThan(strpos($body, 'Relatório público'), strpos($body, 'Fonte anterior'));
            }
        } finally {
            $_SESSION = [];
            unlink($path);
        }
    }
}
