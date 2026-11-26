<?php

declare(strict_types=1);

namespace UsinaDocs\Core\Tests;

use UsinaDocs\Core\Security\CsrfToken;
use PHPUnit\Framework\TestCase;
use Slim\Psr7\Factory\ServerRequestFactory;
use UsinaDocs\Core\AppFactory;
use UsinaDocs\Core\Content\ContentPackageService;
use UsinaDocs\Core\Content\EditorialService;
use UsinaDocs\Core\Content\PageCovers;
use UsinaDocs\Core\Content\PageAlerts;
use UsinaDocs\Core\Content\PublishedPageRepository;
use UsinaDocs\Core\Infrastructure\Database;
use UsinaDocs\Core\Infrastructure\Schema;
use UsinaDocs\Core\Media\MediaLibrary;

final class PublicationReleaseTest extends TestCase
{
    public function test_review_publish_and_transfer_an_illustrated_trilingual_article(): void
    {
        // Give the publishing workflow a real media file without touching production assets.
        // Fornece um arquivo real ao fluxo editorial sem alterar os ativos de produção.
        $root = sys_get_temp_dir().'/usinadocs-release-'.bin2hex(random_bytes(8));
        mkdir($root, 0700);
        $project = dirname(__DIR__);
        symlink($project.'/templates', $root.'/templates');
        try {
            $path = $root.'/source.sqlite';
            $db = Database::connect($path);
            Schema::migrate($db, $project);
            Schema::seed($db);
            $media = new MediaLibrary($db, $root.'/storage/media');
            $png = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+VmboAAAAASUVORK5CYII=');
            file_put_contents($root.'/image.png', $png);
            $image = $media->store($root.'/image.png', 'image.png', 'Diagrama');
            $editor = new EditorialService($db);
            $page = $editor->create('Original', 'original', '', 'Texto', '', '', 'article');
            $blocks = [
                ['type'=>'text','data'=>['body'=>'Conteúdo editorial']],
                ['type'=>'image','data'=>['id'=>$image,'alt'=>'Um diagrama']],
                ['type'=>'reference','data'=>['title'=>'Fonte','url'=>'https://example.test/source']],
            ];
            $editor->replaceDraftBlocks($page, 'pt', $blocks);
            self::assertNotNull($editor->createTranslation($page, 'en', 'english'));
            $editor->discardDraft($page, 'en');
            self::assertNotNull($editor->createTranslation($page, 'en', 'english'));
            $app = AppFactory::create($root, $path);
            $factory = new ServerRequestFactory();
            $request = static fn ($method, $url) => $factory->createServerRequest($method, 'https://docs.example.test'.$url);
            $release = '/admin/pages/'.$page.'/release';
            $_SESSION = [];
            self::assertSame(403, $app->handle($request('GET', $release))->getStatusCode());
            $_SESSION = ['user'=>['role'=>'editor']];
            $incomplete = (string) $app->handle($request('GET', $release))->getBody();
            self::assertStringNotContainsString('Publicar nos três idiomas</button>', $incomplete);
            self::assertNotNull($editor->createTranslation($page, 'es', 'espanol'));
            $editor->updateDraft($page, 'Português somente', '', '', '');
            $editor->replaceDraftBlocks($page, 'pt', $blocks);
            self::assertSame('Original', $editor->localizedDraft($page, 'es')['title']);

            $form = (string) $app->handle($request('GET', $release))->getBody();
            self::assertSame(3, substr_count($form, '<iframe '));
            preg_match('/name="snapshot" value="([0-9a-f]+)"/', $form, $snapshot);
            $preview = $app->handle($request('GET', '/admin/pages/'.$page.'/preview/pt'));
            self::assertStringContainsString('Conteúdo editorial', (string) $preview->getBody());
            self::assertSame('no-store', $preview->getHeaderLine('Cache-Control'));

            // The alert requires the release token and applies to the article as a whole.
            // O alerta exige o token de publicação e se aplica ao artigo inteiro.
            $alertPath = '/admin/pages/'.$page.'/critical-alert';
            $alerts = new PageAlerts($db);
            self::assertFalse($alerts->isCritical($page));
            self::assertSame(403, $app->handle($request('POST', $alertPath)->withParsedBody(['critical'=>'1']))->getStatusCode());
            foreach (['1', '1', '0'] as $choice) {
                $alert = $request('POST', $alertPath)->withParsedBody(['csrf'=>CsrfToken::current(), 'critical'=>$choice]);
                self::assertSame(303, $app->handle($alert)->getStatusCode());
                self::assertSame($choice === '1', $alerts->isCritical($page));
            }

            $coverRequest = $request('POST', '/admin/pages/'.$page.'/cover')->withParsedBody([
                'csrf'=>CsrfToken::current(), 'cover'=>$image,
            ]);
            self::assertSame(303, $app->handle($coverRequest)->getStatusCode());
            $publish = $request('POST', $release)->withParsedBody([
                'csrf'=>CsrfToken::current(), 'snapshot'=>$snapshot[1],
            ]);
            self::assertSame(409, $app->handle($publish)->getStatusCode());
            self::assertSame(0, (int) $db->query("SELECT COUNT(*) FROM page_localizations WHERE page_id=".$db->quote($page)." AND published_revision_id IS NOT NULL")->fetchColumn());
            preg_match('/name="snapshot" value="([0-9a-f]+)"/', (string) $app->handle($request('GET', $release))->getBody(), $fresh);
            self::assertSame(303, $app->handle($publish->withParsedBody(['csrf'=>CsrfToken::current(),'snapshot'=>$fresh[1]]))->getStatusCode());
            $published = $db->query("SELECT COUNT(*) AS editions, COUNT(DISTINCT published_at) AS stamps FROM page_revisions WHERE page_id=".$db->quote($page)." AND status='published'")->fetch();
            self::assertSame(['editions'=>3, 'stamps'=>1], $published);

            // One file is deliberately shared by all editions and by the cover.
            // Um único arquivo é compartilhado pelas edições e pela capa de forma intencional.
            $package = (new ContentPackageService($db, $media))->exportPublishedPage($page);
            self::assertSame(1, $package['manifest']['contents']['media']);
            $target = Database::connect($root.'/target.sqlite');
            Schema::migrate($target, $project);
            Schema::seed($target);
            $targetMedia = new MediaLibrary($target, $root.'/target-media');
            $transfer = new ContentPackageService($target, $targetMedia);
            self::assertTrue($transfer->import($package['content'])['imported']);
            self::assertSame('article', (new PublishedPageRepository($target))->findByLocalizedSlug('pt', 'original')['content_type']);
            self::assertSame($image, (new PageCovers($target, $targetMedia))->get($page));
            self::assertSame($png, file_get_contents($targetMedia->path($targetMedia->find($image))));
            self::assertTrue($transfer->import($package['content'], 'import_revision')['imported']);
            self::assertCount(1, $targetMedia->all());
        } finally {
            $_SESSION = [];
            foreach (new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($root, \FilesystemIterator::SKIP_DOTS), \RecursiveIteratorIterator::CHILD_FIRST) as $file) {
                if ($file->isDir() && !$file->isLink()) rmdir($file->getPathname());
                else unlink($file->getPathname());
            }
            rmdir($root);
        }
    }
}
