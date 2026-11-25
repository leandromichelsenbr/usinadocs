<?php

declare(strict_types=1);

namespace UsinaDocs\Core\Tests;

use PHPUnit\Framework\TestCase;
use Slim\Psr7\Factory\ServerRequestFactory;
use UsinaDocs\Core\AppFactory;
use UsinaDocs\Core\Infrastructure\Database;
use UsinaDocs\Core\Infrastructure\Schema;
use UsinaDocs\Core\Media\MediaLibrary;

final class SiteAppearanceTest extends TestCase
{
    public function test_administrators_can_publish_the_same_identity_to_public_pages_and_the_favicon(): void
    {
        // Isolate the database and media so the test cannot replace real branding.
        // Isola o banco e a mídia para que o teste não substitua a identidade real.
        $root = sys_get_temp_dir().'/usinadocs-appearance-'.bin2hex(random_bytes(8));
        mkdir($root.'/templates', 0700, true);
        $project = dirname(__DIR__);
        foreach (glob($project.'/templates/*.twig') as $template) {
            copy($template, $root.'/templates/'.basename($template));
        }
        try {
            $databasePath = $root.'/fixture.sqlite';
            $database = Database::connect($databasePath);
            Schema::migrate($database, $project);
            Schema::seed($database);
            $media = new MediaLibrary($database, $root.'/storage/media');
            $fixture = $root.'/pixel.png';
            file_put_contents($fixture, base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+VmboAAAAASUVORK5CYII='));
            $icon = $media->store($fixture, 'favicon.png', 'Ícone');
            $light = $media->store($fixture, 'bright.png', 'Marca para fundo claro');
            $dark = $media->store($fixture, 'dark.png', 'Marca para fundo escuro');
            $app = AppFactory::create($root, $databasePath);
            $factory = new ServerRequestFactory();
            $request = static fn (string $method, string $path) => $factory->createServerRequest($method, 'https://docs.example.test'.$path);

            $_SESSION = ['user' => ['role' => 'editor']];
            self::assertSame(403, $app->handle($request('GET', '/admin/appearance'))->getStatusCode());
            $_SESSION = ['user' => ['role' => 'administrator']];
            $form = $app->handle($request('GET', '/admin/appearance'));
            self::assertSame(200, $form->getStatusCode());
            self::assertStringContainsString('Marca para fundo escuro', (string) $form->getBody());
            $update = $request('POST', '/admin/appearance');
            $input = [
                'display_name' => 'Portal de teste',
                'favicon_id' => $icon,
                'logo_light_id' => $light,
                'logo_dark_id' => $dark,
                'accent_color' => '#12704a',
            ];
            self::assertSame(403, $app->handle($update->withParsedBody($input))->getStatusCode());
            $input['csrf'] = $_SESSION['appearance_csrf'];
            self::assertSame(303, $app->handle($update->withParsedBody($input))->getStatusCode());
            self::assertSame(422, $app->handle($update->withParsedBody(array_replace($input, [
                'display_name' => 'Mudança inválida',
                'logo_light_id' => 'media-missing',
            ])))->getStatusCode());

            // Read as a visitor and check that rejecting an invalid save preserved the previous identity.
            // Lê como visitante e verifica que rejeitar a gravação inválida preservou a identidade anterior.
            $_SESSION = [];
            $page = $app->handle($request('GET', '/pt/p/bem-vindo')->withQueryParams(['theme' => 'dark']));
            $html = (string) $page->getBody();
            self::assertStringContainsString('Portal de teste', $html);
            self::assertStringContainsString('class="site-logo-light" src="/media/'.$light.'"', $html);
            self::assertStringContainsString('class="site-logo-dark" src="/media/'.$dark.'"', $html);
            // Both theme images remain inside the homepage link.
            // As imagens dos dois temas ficam dentro do link para a página inicial.
            self::assertMatchesRegularExpression('~<a class="site-logo-link" href="/"[^>]*>[^<]*<span class="site-logo">.*?site-logo-light.*?site-logo-dark.*?</span>[^<]*</a>~s', $html);
            self::assertStringContainsString('rel="icon" href="/media/'.$icon.'"', $html);
            self::assertStringContainsString('#12704a', $html);
            $favicon = $app->handle($request('GET', '/favicon.ico'));
            self::assertSame('/media/'.$icon, $favicon->getHeaderLine('Location'));
            self::assertSame('image/png', $app->handle($request('GET', '/media/'.$icon))->getHeaderLine('Content-Type'));
            self::assertStringNotContainsString('Mudança inválida', $html);
        } finally {
            $_SESSION = [];
            foreach (new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($root, \FilesystemIterator::SKIP_DOTS), \RecursiveIteratorIterator::CHILD_FIRST) as $file) {
                if ($file->isDir()) {
                    rmdir($file->getPathname());
                } else {
                    unlink($file->getPathname());
                }
            }
            rmdir($root);
        }
    }
}
