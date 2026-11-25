<?php

declare(strict_types=1);

namespace UsinaDocs\Core\Tests;

use PHPUnit\Framework\TestCase;
use Slim\Psr7\Factory\ServerRequestFactory;
use UsinaDocs\Core\AppFactory;
use UsinaDocs\Core\Content\ContentPackageService;
use UsinaDocs\Core\Content\EditorialService;
use UsinaDocs\Core\Content\YouTubeVideo;
use UsinaDocs\Core\Infrastructure\Database;
use UsinaDocs\Core\Infrastructure\Schema;

final class YouTubeArticleTest extends TestCase
{
    public function test_imported_video_urls_only_embed_valid_youtube_identifiers(): void
    {
        // Exercise a real article and its portable package, including untrusted embedded URLs.
        // Exercita um artigo real e seu pacote portável, inclusive URLs de incorporação não confiáveis.
        $path = tempnam(sys_get_temp_dir(), 'usinadocs-video-');
        try {
            $root = dirname(__DIR__);
            $db = Database::connect($path);
            Schema::migrate($db, $root);
            Schema::seed($db);
            $editor = new EditorialService($db);
            $id = $editor->create('Vídeo publicado', 'video-publicado', '', 'Texto', '', '', 'article');
            self::assertSame('XcpsFpYcBSA', YouTubeVideo::id('https://youtu.be/XcpsFpYcBSA?t=10'));
            self::assertNull(YouTubeVideo::id('https://youtube.com.example.test/watch?v=XcpsFpYcBSA'));
            self::assertNull(YouTubeVideo::id('https://youtube.com/watch?v[]=XcpsFpYcBSA'));
            $editor->replaceDraftBlocks($id, 'pt', [
                ['type' => 'youtube', 'data' => ['url' => 'https://www.youtube.com/embed/XcpsFpYcBSA?si=tracking', 'title' => 'Debate']],
                ['type' => 'youtube', 'data' => ['url' => 'https://example.test/embed/XcpsFpYcBSA']],
                ['type' => 'image', 'data' => ['id' => 'media-test', 'alt' => 'Relógio de bancada', 'caption' => 'Ilustração técnica']],
            ]);
            $editor->publish($id);

            $app = AppFactory::create($root, $path);
            $request = (new ServerRequestFactory())->createServerRequest('GET', '/pt/p/video-publicado');
            $html = (string) $app->handle($request)->getBody();
            self::assertSame(1, substr_count($html, '<iframe '));
            self::assertStringContainsString('src="https://www.youtube-nocookie.com/embed/XcpsFpYcBSA"', $html);
            self::assertStringContainsString('https://www.youtube.com/watch?v=XcpsFpYcBSA', $html);
            self::assertStringNotContainsString('si=tracking', $html);
            self::assertStringContainsString('alt="Relógio de bancada"', $html);
            self::assertStringContainsString('<figcaption>Ilustração técnica</figcaption>', $html);

            $packages = new ContentPackageService($db);
            $package = $packages->exportPublishedPage($id);
            self::assertSame(0, $packages->preview($package['content'])['summary']['unknown_blocks']);
        } finally {
            unlink($path);
        }
    }
}
