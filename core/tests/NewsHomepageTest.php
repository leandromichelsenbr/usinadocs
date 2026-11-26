<?php

declare(strict_types=1);

namespace UsinaDocs\Core\Tests;

use PHPUnit\Framework\TestCase;
use Slim\Psr7\Factory\ServerRequestFactory;
use UsinaDocs\Core\AppFactory;
use UsinaDocs\Core\Content\ArticleSections;
use UsinaDocs\Core\Content\EditorialService;
use UsinaDocs\Core\Infrastructure\Database;
use UsinaDocs\Core\Infrastructure\Schema;

/**
 * EN: Guards the optional news homepage. It must stay off by default, so
 * documentation installations keep their homepage, and when enabled it must
 * show only published articles, grouped by unblocked editorial sections.
 *
 * PT: Protege a página inicial de notícias opcional. Ela deve ficar desligada
 * por padrão, para que instalações de documentação mantenham sua página
 * inicial, e, quando ligada, deve mostrar apenas artigos publicados, agrupados
 * pelas editorias não bloqueadas.
 */
final class NewsHomepageTest extends TestCase
{
    private string $databasePath;

    protected function setUp(): void
    {
        $this->databasePath = sys_get_temp_dir().DIRECTORY_SEPARATOR.'usinadocs-news-'.bin2hex(random_bytes(8)).'.sqlite';
        $database = Database::connect($this->databasePath);
        Schema::migrate($database, dirname(__DIR__));
        Schema::seed($database);
        $_SESSION = [];
    }

    protected function tearDown(): void
    {
        unset($_ENV['USINADOCS_NEWS_HOME'], $_ENV['USINADOCS_SITE_NAME']);
        $_SESSION = [];
        if (is_file($this->databasePath)) {
            @unlink($this->databasePath);
        }
    }

    public function test_documentation_installations_keep_the_welcome_redirect(): void
    {
        $response = AppFactory::create(dirname(__DIR__), $this->databasePath)
            ->handle((new ServerRequestFactory())->createServerRequest('GET', '/'));

        self::assertSame(302, $response->getStatusCode());
        self::assertSame('/pt/p/bem-vindo', $response->getHeaderLine('Location'));
    }

    public function test_news_homepage_lists_published_articles_by_section(): void
    {
        $database = Database::connect($this->databasePath);
        $editorial = new EditorialService($database);
        $sections = new ArticleSections($database);
        $sections->save(null, ['slug' => 'ia-automacao', 'title_pt' => 'IA e automação', 'title_en' => 'AI and automation', 'title_es' => 'IA y automatización']);

        $published = $editorial->create('Matéria publicada', 'materia-publicada', 'Resumo da matéria.', 'Texto.', '', '', 'article');
        $sections->assign($published, 'ia-automacao');
        $editorial->publish($published);
        // EN: A draft must never reach readers, even on the homepage.
        // PT: Um rascunho nunca deve chegar aos leitores, nem pela página inicial.
        $editorial->create('Rascunho secreto', 'rascunho-secreto', '', 'Texto.', '', '', 'article');

        $_ENV['USINADOCS_NEWS_HOME'] = '1';
        $_ENV['USINADOCS_SITE_NAME'] = 'Jornal de Teste';
        $app = AppFactory::create(dirname(__DIR__), $this->databasePath);

        foreach (['/', '/pt/p/inicio'] as $path) {
            $response = $app->handle((new ServerRequestFactory())->createServerRequest('GET', $path));
            $html = (string) $response->getBody();

            self::assertSame(200, $response->getStatusCode(), $path);
            self::assertStringContainsString('Matéria publicada', $html);
            self::assertStringContainsString('IA e automação', $html);
            self::assertStringContainsString('Jornal de Teste', $html);
            self::assertStringNotContainsString('Rascunho secreto', $html);
        }
    }
}
