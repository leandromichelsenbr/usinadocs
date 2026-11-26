<?php

declare(strict_types=1);

namespace UsinaDocs\Core\Tests;

use UsinaDocs\Core\Security\CsrfToken;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ServerRequestInterface;
use Slim\App;
use Slim\Psr7\Factory\ServerRequestFactory;
use Slim\Views\Twig;
use UsinaDocs\Core\AppFactory;
use UsinaDocs\Core\Content\ArticleEngagement;
use UsinaDocs\Core\Content\EditorialService;
use UsinaDocs\Core\Infrastructure\Database;
use UsinaDocs\Core\Infrastructure\Schema;

final class ArticleEngagementTest extends TestCase
{
    private const PT = '/pt/p/bem-vindo';
    private const EN = '/en/p/welcome';

    private string $databasePath;
    private App $app;
    private ArticleEngagement $engagement;
    private EditorialService $editorial;

    protected function setUp(): void
    {
        $this->databasePath = tempnam(sys_get_temp_dir(), 'usinadocs-engagement-');
        $db = Database::connect($this->databasePath);
        Schema::migrate($db, dirname(__DIR__));
        Schema::seed($db);
        $this->editorial = new EditorialService($db);
        $this->editorial->createTranslation('page-welcome', 'en', 'welcome');
        $this->editorial->publishLocalized('page-welcome', 'en');
        $this->engagement = new ArticleEngagement($db);
        $this->app = AppFactory::create(dirname(__DIR__), $this->databasePath);
        $_SESSION = [];
    }

    protected function tearDown(): void
    {
        $_SESSION = [];
        unlink($this->databasePath);
    }

    public function test_views_count_once_per_edition_and_preserve_historical_totals(): void
    {
        // Exclude non-reading requests first, then open the two published editions in one session.
        // Exclui primeiro as requisições sem leitura e depois abre as duas edições publicadas em uma sessão.
        $this->app->handle($this->request('HEAD', self::PT));
        $this->app->handle($this->request('GET', self::PT)->withHeader('User-Agent', 'ExampleBot'));
        $this->app->handle($this->request('GET', self::PT)->withHeader('Sec-Purpose', 'prefetch'));
        $this->app->handle($this->request('GET', self::PT)->withQueryParams(['lang' => 'en']));
        self::assertSame(0, $this->engagement->viewCount('page-welcome'));
        // Pre-migration views stay in the report without attribution. A reload must not increment the same edition.
        // Visualizações antigas ficam no relatório sem atribuição. Recarregar não pode incrementar a mesma edição.
        Database::connect($this->databasePath)->exec("INSERT INTO article_views (page_id, views) VALUES ('page-welcome', 7)");

        $first = $this->app->handle($this->request('GET', self::PT));
        self::assertStringContainsString('1 visualização</p>', (string) $first->getBody());
        self::assertSame('private, no-store', $first->getHeaderLine('Cache-Control'));
        $this->app->handle($this->request('GET', self::PT));
        $translation = $this->app->handle($this->request('GET', self::EN));
        self::assertStringContainsString('1 view</p>', (string) $translation->getBody());
        self::assertSame(1, $this->engagement->viewCount('page-welcome', 'pt'));
        self::assertSame(1, $this->engagement->viewCount('page-welcome', 'en'));
        self::assertSame(9, $this->engagement->viewCount('page-welcome'));

        $_SESSION = [];
        $this->app->handle($this->request('GET', self::EN));
        self::assertSame(2, $this->engagement->viewCount('page-welcome', 'en'));
        $_SESSION['user'] = ['id' => 'editor', 'role' => 'editor'];
        $ranking = (string) $this->app->handle($this->request('GET', '/admin/article-views'))->getBody();
        self::assertStringContainsString('Sem idioma', $ranking);
        self::assertStringContainsString('<td>1</td><td>2</td><td>0</td><td>7</td><td>10</td>', $ranking);
    }

    public function test_reader_submission_requires_csrf_and_editor_approval_and_stays_in_its_edition(): void
    {
        // Test the public/moderation boundary through the real routes, including escaped output.
        // Testa pelas rotas reais a separação entre área pública e moderação, incluindo a saída escapada.
        $comment = '<strong>Texto do leitor</strong>';
        $post = $this->request('POST', self::PT.'/comments')->withParsedBody(['body' => $comment]);
        $article = $this->request('GET', self::PT);
        self::assertSame(403, $this->app->handle($post)->getStatusCode());
        self::assertStringContainsString('/login?next=%2Fpt%2Fp%2Fbem-vindo%23comments', (string) $this->app->handle($article)->getBody());

        $_SESSION['user'] = ['id' => 'reader', 'name' => 'Leitor', 'email' => 'reader@example.test', 'role' => 'reader'];
        $this->app->handle($article);
        self::assertSame(403, $this->app->handle($post)->getStatusCode());
        $post = $post->withParsedBody(['body' => $comment, 'csrf' => CsrfToken::current()]);
        $sent = $this->app->handle($post);
        self::assertSame(303, $sent->getStatusCode());
        self::assertSame(self::PT.'#comments', $sent->getHeaderLine('Location'));
        $pending = (string) $this->app->handle($article)->getBody();
        self::assertStringContainsString('Comentário enviado.', $pending);
        self::assertStringNotContainsString('Texto do leitor', $pending);
        self::assertSame(403, $this->app->handle($this->request('GET', '/admin/comments'))->getStatusCode());

        $_SESSION['user'] = ['id' => 'editor', 'name' => 'Editor', 'role' => 'editor'];
        $queue = (string) $this->app->handle($this->request('GET', '/admin/comments'))->getBody();
        self::assertStringContainsString('&lt;strong&gt;Texto do leitor&lt;/strong&gt;', $queue);
        $decision = $this->request('POST', '/admin/comments/1')->withParsedBody([
            'csrf' => CsrfToken::current(), 'current' => 'pending', 'decision' => 'approved',
        ]);
        self::assertSame(303, $this->app->handle($decision)->getStatusCode());
        self::assertSame(409, $this->app->handle($decision)->getStatusCode());

        $_SESSION = [];
        $public = (string) $this->app->handle($article)->getBody();
        self::assertStringContainsString('&lt;strong&gt;Texto do leitor&lt;/strong&gt;', $public);
        self::assertStringNotContainsString('reader@example.test', $public);
        self::assertStringNotContainsString('Texto do leitor', (string) $this->app->handle($this->request('GET', self::EN))->getBody());
    }

    public function test_rate_limit_applies_to_an_account_across_editions(): void
    {
        // Switching editions must not reset the account's submission limit.
        // Trocar de edição não deve reiniciar o limite de envios da conta.
        $user = ['id' => 'reader', 'name' => 'Leitor'];
        for ($index = 0; $index < 5; $index++) {
            self::assertTrue($this->engagement->submitComment('page-welcome', 'pt', $user, 'Comentário válido.'));
        }
        self::assertFalse($this->engagement->submitComment('page-welcome', 'en', $user, 'A sixth comment.'));
    }

    public function test_card_counts_views_and_approved_comments_in_each_edition(): void
    {
        // Seed a repeated article and a second publication to verify the bulk results stay separate.
        // Prepara um artigo repetido e outra publicação para verificar que a consulta em lote mantém os resultados separados.
        $empty = $this->editorial->create('Outro artigo', 'outro-artigo', '', '', '', '', 'article');
        $this->editorial->publish($empty);
        $this->engagement->incrementViews('page-welcome', 'pt');
        $reader = ['id' => 'reader', 'name' => 'Leitor'];
        foreach (['pt', 'pt', 'pt', 'en', 'en'] as $locale) {
            $this->engagement->submitComment('page-welcome', $locale, $reader, 'Comentário de teste.');
        }
        $this->engagement->moderate(1, 'pending', 'approved', 'editor');
        $this->engagement->moderate(2, 'pending', 'rejected', 'editor');
        $this->engagement->moderate(4, 'pending', 'approved', 'editor');
        $this->engagement->moderate(5, 'pending', 'approved', 'editor');

        self::assertSame([], $this->engagement->countsForArticles([]));
        $counts = $this->engagement->countsForArticles(['page-welcome', $empty, 'page-welcome']);
        self::assertCount(2, $counts);
        self::assertSame(['views' => 1, 'approved_comments' => 1], $counts['page-welcome']['pt']);
        self::assertSame(['views' => 0, 'approved_comments' => 2], $counts['page-welcome']['en']);
        self::assertSame(['views' => 0, 'approved_comments' => 0], $counts[$empty]['pt']);

        $this->engagement->moderate(1, 'approved', 'rejected', 'editor');
        self::assertSame(0, $this->engagement->countsForArticles(['page-welcome'])['page-welcome']['pt']['approved_comments']);
    }

    public function test_card_labels_use_local_number_format_and_plural_forms(): void
    {
        // The card uses the interface language even when its data comes from a fallback edition.
        // A chamada usa o idioma da interface, mesmo quando os dados vêm de uma edição alternativa.
        $twig = Twig::create(dirname(__DIR__).'/templates');
        foreach ([
            'pt' => ['1.234 visualizações', '1 comentário aprovado'],
            'en' => ['1,234 views', '1 approved comment'],
            'es' => ['1.234 visualizaciones', '1 comentario aprobado'],
        ] as $locale => [$views, $comment]) {
            $body = $twig->fetch('article-card-counts.twig', [
                'locale' => $locale, 'counts' => ['views' => 1234, 'approved_comments' => 1],
            ]);
            self::assertStringContainsString($views, $body);
            self::assertStringContainsString($comment, $body);
        }
    }

    public function test_non_articles_do_not_accept_comments_or_register_views(): void
    {
        // A published reference shares the reader route but must remain outside article engagement.
        // Uma referência publicada usa a mesma rota pública, mas permanece fora da interação de artigos.
        $this->editorial->setContentType('page-welcome', 'reference');
        $body = (string) $this->app->handle($this->request('GET', self::PT))->getBody();
        self::assertStringNotContainsString('id="comments"', $body);
        self::assertSame(0, $this->engagement->viewCount('page-welcome'));
        self::assertSame(404, $this->app->handle($this->request('POST', self::PT.'/comments')->withHeader(CsrfToken::HEADER, CsrfToken::current()))->getStatusCode());
    }

    private function request(string $method, string $path): ServerRequestInterface
    {
        return (new ServerRequestFactory())->createServerRequest($method, $path)->withHeader('User-Agent', 'Example Browser');
    }
}
