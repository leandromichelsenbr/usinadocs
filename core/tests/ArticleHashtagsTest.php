<?php

declare(strict_types=1);

namespace UsinaDocs\Core\Tests;

use PHPUnit\Framework\TestCase;
use Slim\Psr7\Factory\ServerRequestFactory;
use UsinaDocs\Core\AppFactory;
use UsinaDocs\Core\Content\ArticleTags;
use UsinaDocs\Core\Content\EditorialService;
use UsinaDocs\Core\Infrastructure\Database;
use UsinaDocs\Core\Infrastructure\Schema;
use UsinaDocs\Core\Security\CsrfToken;

final class ArticleHashtagsTest extends TestCase
{
    public function test_the_editor_publishes_hashtags_and_the_archive_uses_one_set_for_all_editions(): void
    {
        $root = dirname(__DIR__);
        $path = tempnam(sys_get_temp_dir(), 'usinadocs-tags-');
        $env = $_ENV;
        try {
            $_ENV['USINADOCS_NEWS_HOME'] = '1';
            $db = Database::connect($path);
            Schema::migrate($db, $root);
            Schema::seed($db);
            $editorial = new EditorialService($db);
            $app = AppFactory::create($root, $path);
            $_SESSION = ['user' => ['role' => 'editor']];
            $factory = new ServerRequestFactory();
            $request = static fn ($method, $uri, $params = [], $body = []) => $factory
                ->createServerRequest($method, 'https://news.example.test'.$uri)
                ->withQueryParams($params)->withParsedBody($body);

            $data = [
                'slug' => 'windows-test', 'title' => 'Teste do Windows', 'content_type' => 'article',
                'blocks_json' => '[{"type":"text","data":{"body":"Texto da matéria"}}]',
                'article_tags' => '#Windows #windows, #Educação',
                'csrf' => CsrfToken::current(),
            ];
            $form = $app->handle($request('GET', '/admin/pages/new'));
            self::assertStringContainsString('name="article_tags"', (string) $form->getBody());
            $invalid = $app->handle($request('POST', '/admin/pages', [], array_replace($data, ['article_tags' => '#Windows/Server'])));
            self::assertSame(422, $invalid->getStatusCode());
            self::assertStringContainsString('Confira as hashtags', (string) $invalid->getBody());
            self::assertSame([], $editorial->catalog('Teste do Windows'));

            self::assertSame(302, $app->handle($request('POST', '/admin/pages', [], $data))->getStatusCode());
            $page = $editorial->catalog('Teste do Windows')[0]['id'];
            self::assertSame([
                ['key' => 'windows', 'label' => 'Windows'],
                ['key' => 'educação', 'label' => 'Educação'],
            ], ArticleTags::fromBlocks($editorial->blocksForDraft($page, 'pt')));

            // A draft cannot appear on the public archive until the original edition is published.
            // Um rascunho não pode aparecer no arquivo antes da publicação da edição original.
            $archive = $request('GET', '/', ['tag' => 'WINDOWS', 'lang' => 'en']);
            self::assertStringNotContainsString('Teste do Windows', (string) $app->handle($archive)->getBody());
            $editorial->publish($page);
            $editorial->createTranslation($page, 'en', 'windows-edition');
            $editorial->publishLocalized($page, 'en');
            $other = $editorial->create('Unrelated article', 'unrelated', '', '', '', '', 'article');
            $editorial->publish($other);
            $results = (string) $app->handle($archive)->getBody();
            self::assertStringContainsString('id="titulo-ultimas">#Windows</h2>', $results);
            self::assertStringContainsString('/en/p/windows-edition', $results);
            self::assertStringNotContainsString('Unrelated article', $results);
            self::assertStringContainsString('tag=windows&amp;page=1&amp;lang=es', $results);

            $edition = $request('GET', '/en/p/windows-edition', ['lang' => 'en']);
            $article = (string) $app->handle($edition)->getBody();
            self::assertStringContainsString('href="/?tag=educa%C3%A7%C3%A3o&amp;lang=en">#Educação</a>', $article);
            self::assertSame(1, substr_count($article, '>#Windows</a>'));

            // Saving a new draft leaves the published references to the old tag until that revision goes live.
            // Salvar outro rascunho mantém as referências públicas à hashtag anterior até publicar a revisão.
            $editorial->createLocalizedRevision($page, 'pt');
            $edit = (string) $app->handle($request('GET', '/admin/pages/'.$page.'/edit'))->getBody();
            self::assertStringContainsString('value="#Windows #Educação"', $edit);
            self::assertSame(302, $app->handle($request('POST', '/admin/pages/'.$page, [], array_replace($data, ['article_tags' => '#Microsoft'])))->getStatusCode());
            self::assertStringContainsString('>#Windows</a>', (string) $app->handle($edition)->getBody());
            $editorial->publish($page);
            self::assertStringContainsString('>#Microsoft</a>', (string) $app->handle($edition)->getBody());
            self::assertStringNotContainsString('/en/p/windows-edition', (string) $app->handle($archive)->getBody());

            $hide = $db->prepare('INSERT INTO hidden_articles (page_id) VALUES (?)');
            $hide->execute([$page]);
            self::assertStringNotContainsString('/en/p/windows-edition',
                (string) $app->handle($request('GET', '/', ['tag' => 'microsoft', 'lang' => 'en']))->getBody());
        } finally {
            $_SESSION = [];
            $_ENV = $env;
            unlink($path);
        }
    }
}
