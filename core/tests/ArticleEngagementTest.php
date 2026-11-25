<?php

declare(strict_types=1);

namespace UsinaDocs\Core\Tests;

use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ServerRequestInterface;
use Slim\App;
use Slim\Psr7\Factory\ServerRequestFactory;
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

    public function test_translations_share_a_view_total_and_only_count_reader_gets_once_per_session(): void
    {
        // Exclude non-reading requests first, then open the two published editions in one session.
        // Exclui primeiro as requisições sem leitura e depois abre as duas edições publicadas em uma sessão.
        $this->app->handle($this->request('HEAD', self::PT));
        $this->app->handle($this->request('GET', self::PT)->withHeader('User-Agent', 'ExampleBot'));
        $this->app->handle($this->request('GET', self::PT)->withHeader('Sec-Purpose', 'prefetch'));
        $this->app->handle($this->request('GET', self::PT)->withQueryParams(['lang' => 'en']));
        self::assertSame(0, $this->engagement->viewCount('page-welcome'));

        $first = $this->app->handle($this->request('GET', self::PT));
        self::assertStringContainsString('1 visualização</p>', (string) $first->getBody());
        self::assertSame('private, no-store', $first->getHeaderLine('Cache-Control'));
        $translation = $this->app->handle($this->request('GET', self::EN));
        self::assertStringContainsString('1 view</p>', (string) $translation->getBody());
        self::assertSame(1, $this->engagement->viewCount('page-welcome'));

        $_SESSION = [];
        $this->app->handle($this->request('GET', self::EN));
        self::assertSame(2, $this->engagement->viewCount('page-welcome'));
        $_SESSION['user'] = ['id' => 'editor', 'role' => 'editor'];
        $ranking = (string) $this->app->handle($this->request('GET', '/admin/article-views'))->getBody();
        self::assertStringContainsString('<td>2</td>', $ranking);
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
        $post = $post->withParsedBody(['body' => $comment, 'csrf' => $_SESSION['article_comment_csrf']]);
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
            'csrf' => $_SESSION['comment_moderation_csrf'], 'current' => 'pending', 'decision' => 'approved',
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

    public function test_non_articles_do_not_accept_comments_or_register_views(): void
    {
        // A published reference shares the reader route but must remain outside article engagement.
        // Uma referência publicada usa a mesma rota pública, mas permanece fora da interação de artigos.
        $this->editorial->setContentType('page-welcome', 'reference');
        $body = (string) $this->app->handle($this->request('GET', self::PT))->getBody();
        self::assertStringNotContainsString('id="comments"', $body);
        self::assertSame(0, $this->engagement->viewCount('page-welcome'));
        self::assertSame(404, $this->app->handle($this->request('POST', self::PT.'/comments'))->getStatusCode());
    }

    private function request(string $method, string $path): ServerRequestInterface
    {
        return (new ServerRequestFactory())->createServerRequest($method, $path)->withHeader('User-Agent', 'Example Browser');
    }
}
