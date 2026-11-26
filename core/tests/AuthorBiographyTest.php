<?php

declare(strict_types=1);

namespace UsinaDocs\Core\Tests;

use UsinaDocs\Core\Security\CsrfToken;
use PHPUnit\Framework\TestCase;
use Slim\Psr7\Factory\ServerRequestFactory;
use UsinaDocs\Core\AppFactory;
use UsinaDocs\Core\Content\EditorialService;
use UsinaDocs\Core\Infrastructure\Database;
use UsinaDocs\Core\Infrastructure\Schema;

final class AuthorBiographyTest extends TestCase
{
    public function test_public_biographies_and_a_shared_article_byline(): void
    {
        // Use the normal administrative and reader routes to exercise both permissions and public rendering.
        // Usa as rotas administrativas e públicas para verificar as permissões e a renderização.
        $path = tempnam(sys_get_temp_dir(), 'usinadocs-authors-');
        try {
            $root = dirname(__DIR__);
            $db = Database::connect($path);
            Schema::migrate($db, $root);
            Schema::seed($db);
            $editor = new EditorialService($db);
            $page = $editor->create('Reportagem', 'reportagem', '', 'Artigo original', '', '', 'article');
            $editor->publishLocalized($page, 'pt', '2026-11-23T15:00:00+00:00');
            $editor->createLocalizedRevision($page, 'pt');
            $editor->publishLocalized($page, 'pt', '2026-11-24T16:00:00+00:00');

            $app = AppFactory::create($root, $path);
            $factory = new ServerRequestFactory();
            $request = static fn ($method, $url) => $factory->createServerRequest($method, 'https://docs.example.test'.$url);
            $_SESSION = ['user'=>['role'=>'reader']];
            self::assertSame(403, $app->handle($request('GET', '/admin/authors'))->getStatusCode());
            $_SESSION = ['user'=>['role'=>'editor']];
            self::assertSame(200, $app->handle($request('GET', '/admin/authors'))->getStatusCode());
            $post = $request('POST', '/admin/authors');
            $data = ['slug'=>'bia-teste','name'=>'Bia Teste','bio_pt'=>'Biografia em português','bio_en'=>'English biography'];
            self::assertSame(403, $app->handle($post->withParsedBody($data))->getStatusCode());
            $data['csrf'] = CsrfToken::current();
            self::assertSame(303, $app->handle($post->withParsedBody($data))->getStatusCode());
            self::assertSame(422, $app->handle($post->withParsedBody($data))->getStatusCode());
            $byline = $request('POST', '/admin/pages/'.$page.'/authors');
            self::assertSame(303, $app->handle($byline->withParsedBody(['csrf'=>$data['csrf'],'authors'=>['bia-teste']]))->getStatusCode());
            self::assertSame(422, $app->handle($byline->withParsedBody(['csrf'=>$data['csrf'],'authors'=>['missing']]))->getStatusCode());

            $_SESSION = [];
            $article = (string) $app->handle($request('GET', '/pt/p/reportagem'))->getBody();
            self::assertStringContainsString('Publicado em <time datetime="2026-11-23T15:00:00+00:00"', $article);
            self::assertStringContainsString('Atualizado em <time datetime="2026-11-24T16:00:00+00:00"', $article);
            self::assertStringContainsString('rel="author" href="/pt/p/autor-bia-teste">Bia Teste', $article);
            $english = $app->handle($request('GET', '/en/p/autor-bia-teste')->withHeader('Accept-Language', 'en'));
            self::assertSame(200, $english->getStatusCode());
            $switch = $app->handle($request('GET', '/pt/p/autor-bia-teste')->withQueryParams(['lang'=>'en']));
            self::assertSame('/en/p/autor-bia-teste', $switch->getHeaderLine('Location'));
            self::assertStringContainsString('English biography', (string) $english->getBody());
            $spanish = (string) $app->handle($request('GET', '/es/p/autor-bia-teste')->withHeader('Accept-Language', 'es'))->getBody();
            self::assertStringContainsString('La biografía está disponible en portugués.', $spanish);
            self::assertStringContainsString('lang="pt">Biografia em português', $spanish);
            $directory = $app->handle($request('GET', '/en/p/autores'));
            self::assertSame(200, $directory->getStatusCode());
            self::assertStringContainsString('/en/p/autor-bia-teste', (string) $directory->getBody());
            // The directory switches to the directory; a profile switches to the same author.
            // O diretório aponta para o diretório. Um perfil aponta para o mesmo autor.
            self::assertStringContainsString('href="/es/p/autores?lang=es"', (string) $directory->getBody());
            self::assertStringContainsString('href="/es/p/autor-bia-teste?lang=es"', (string) $english->getBody());
        } finally {
            $_SESSION = [];
            unlink($path);
        }
    }
}
