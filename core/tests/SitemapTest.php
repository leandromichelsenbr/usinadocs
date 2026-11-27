<?php

declare(strict_types=1);

namespace UsinaDocs\Core\Tests;

use PHPUnit\Framework\TestCase;
use Slim\Psr7\Factory\ServerRequestFactory;
use UsinaDocs\Core\AppFactory;
use UsinaDocs\Core\Content\EditorialService;
use UsinaDocs\Core\Content\LegalDocuments;
use UsinaDocs\Core\Infrastructure\Database;
use UsinaDocs\Core\Infrastructure\Schema;
use UsinaDocs\Core\Security\CsrfToken;

final class SitemapTest extends TestCase
{
    public function test_sitemap_tracks_public_editions_and_supports_an_explicit_manual_snapshot(): void
    {
        $root = dirname(__DIR__);
        $path = tempnam(sys_get_temp_dir(), 'usinadocs-sitemap-');
        $env = $_ENV;
        try {
            $_ENV['USINADOCS_PUBLIC_ORIGIN'] = 'https://news.example.test';
            $_ENV['USINADOCS_NEWS_HOME'] = '1';
            $db = Database::connect($path);
            Schema::migrate($db, $root);
            Schema::seed($db);
            $editorial = new EditorialService($db);
            $page = $editorial->create('Publicação', 'noticia', '', '', '', '', 'article');
            $editorial->createTranslation($page, 'en', 'news');
            $editorial->publishLocalized($page, 'pt', '2026-11-27T12:00:00+00:00');
            $app = AppFactory::create($root, $path);
            $_SESSION = [];
            $factory = new ServerRequestFactory();
            $request = static fn ($method, $url) => $factory->createServerRequest($method, 'https://incoming.example.test'.$url);

            // Drafts are excluded and the configured origin wins even when the request host differs.
            // Rascunhos ficam de fora e a origem configurada prevalece mesmo quando o host da requisição é diferente.
            $sitemap = $request('GET', '/sitemap.xml');
            $response = $app->handle($sitemap);
            $xml = (string) $response->getBody();
            self::assertSame(200, $response->getStatusCode());
            self::assertSame('application/xml; charset=utf-8', $response->getHeaderLine('Content-Type'));
            self::assertSame('no-store', $response->getHeaderLine('Cache-Control'));
            self::assertStringContainsString('<loc>https://news.example.test/</loc>', $xml);
            self::assertStringContainsString('<loc>https://news.example.test/pt/p/noticia</loc>', $xml);
            self::assertStringContainsString('<lastmod>2026-11-27T12:00:00Z</lastmod>', $xml);
            self::assertStringNotContainsString('/en/p/news', $xml);
            self::assertStringNotContainsString('incoming.example.test', $xml);
            self::assertStringNotContainsString('/pt/p/inicio', $xml);
            self::assertStringNotContainsString('/pt/p/bem-vindo', $xml);
            self::assertSame('', (string) $app->handle($request('HEAD', '/sitemap.xml'))->getBody());
            self::assertStringContainsString('Sitemap: https://news.example.test/sitemap.xml', (string) $app->handle($request('GET', '/robots.txt'))->getBody());

            $admin = $request('POST', '/admin/sitemap');
            self::assertSame(403, $app->handle($request('GET', '/admin/sitemap'))->getStatusCode());
            $_SESSION['user'] = ['role' => 'administrator'];
            self::assertSame(403, $app->handle($admin->withParsedBody(['mode' => 'manual']))->getStatusCode());
            $setMode = static fn (string $mode) => $admin->withParsedBody(['csrf' => CsrfToken::current(), 'mode' => $mode]);
            self::assertSame(303, $app->handle($setMode('manual'))->getStatusCode());

            // Switching off freezes the index until the administrator saves a new snapshot or enables live updates.
            // Desativar congela o índice até o administrador salvar outra cópia ou habilitar a atualização automática.
            $editorial->publishLocalized($page, 'en', '2026-11-27T15:00:00+00:00');
            $legalText = array_fill_keys(['pt', 'en', 'es'], ['title' => 'Terms', 'body' => 'Published terms.']);
            (new LegalDocuments($db))->save('custom-terms', $legalText, true, true);
            self::assertSame($xml, (string) $app->handle($sitemap)->getBody());
            self::assertSame(303, $app->handle($setMode('manual'))->getStatusCode());
            $updated = (string) $app->handle($sitemap)->getBody();
            self::assertStringContainsString('<loc>https://news.example.test/en/p/news</loc>', $updated);
            self::assertStringContainsString('hreflang="en" href="https://news.example.test/en/p/news"', $updated);
            self::assertStringContainsString('<loc>https://news.example.test/es/p/legal-custom-terms</loc>', $updated);
            self::assertSame(303, $app->handle($setMode('automatic'))->getStatusCode());

            $hide = $db->prepare('INSERT INTO hidden_articles (page_id) VALUES (?)');
            $hide->execute([$page]);
            $live = (string) $app->handle($sitemap)->getBody();
            self::assertStringNotContainsString('/pt/p/noticia', $live);
            self::assertStringNotContainsString('/en/p/news', $live);
            self::assertStringContainsString('Automática', (string) $app->handle($request('GET', '/admin/sitemap'))->getBody());
        } finally {
            $_SESSION = [];
            $_ENV = $env;
            unlink($path);
        }
    }
}
