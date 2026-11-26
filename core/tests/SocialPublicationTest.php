<?php

declare(strict_types=1);

namespace UsinaDocs\Core\Tests;

use PHPUnit\Framework\TestCase;
use Slim\Psr7\Factory\ServerRequestFactory;
use UsinaDocs\Core\AppFactory;
use UsinaDocs\Core\Content\EditorialService;
use UsinaDocs\Core\Infrastructure\Database;
use UsinaDocs\Core\Infrastructure\Schema;
use UsinaDocs\Core\Security\CsrfToken;
use UsinaDocs\Core\Social\SocialPublication;
use UsinaDocs\Core\Social\SocialPublisher;

final class SocialPublicationTest extends TestCase
{
    public function test_only_first_eligible_publication_queues_and_outbound_requests_are_claimed_once(): void
    {
        // Test the database transition and both provider payloads without making external posts.
        // Testa a transição no banco e os dados dos dois provedores sem fazer publicações externas.
        $db = Database::connect(':memory:');
        Schema::migrate($db, dirname(__DIR__));
        Schema::seed($db);
        $editor = new EditorialService($db);
        $social = new SocialPublication($db);
        $social->saveChannel('linkedin', 'pt', true);
        $social->saveChannel('facebook', 'es', true);
        $page = $editor->create('Teste #1', 'artigo', 'Resumo', 'Texto', '', '', 'article');
        $editor->createTranslation($page, 'en', 'article');
        $editor->createTranslation($page, 'es', 'articulo');
        $editor->publishLocalized($page, 'en');
        self::assertSame([], $social->recent());
        $db->beginTransaction();
        $editor->publish($page);
        $editor->publishLocalized($page, 'es');
        $db->rollBack();
        self::assertSame([], $social->recent());
        $editor->publish($page);
        $editor->publishLocalized($page, 'es');
        $editor->createLocalizedRevision($page, 'pt');
        $editor->publish($page);
        $reference = $editor->create('Referência', 'referencia', '', '', '');
        $editor->publish($reference);
        self::assertCount(2, $social->recent());

        $publisher = new SocialPublisher($social);
        $requests = [];
        $transport = static function (string $endpoint, array $headers, string $body) use (&$requests): int {
            $requests[] = [$endpoint, $headers, $body];
            return str_contains($endpoint, 'linkedin.com') ? 201 : 403;
        };
        $credentials = [
            'linkedin' => ['id' => '100', 'token' => 'fake-token', 'version' => '202609'],
            'facebook' => ['id' => '200', 'token' => 'fake-token', 'version' => 'v27.0'],
        ];

        $hide = $db->prepare('INSERT INTO hidden_articles (page_id) VALUES (?)');
        $hide->execute([$page]);
        $publisher->run('https://news.example.test', $credentials, $transport);
        self::assertSame([], $requests);
        $db->exec('DELETE FROM hidden_articles');
        $publisher->run('https://news.example.test', [], $transport);
        self::assertSame([], $requests);
        $publisher->run('https://news.example.test', $credentials, $transport);
        $publisher->run('https://news.example.test', $credentials, $transport);

        self::assertCount(2, $requests);
        self::assertSame('https://api.linkedin.com/rest/posts', $requests[0][0]);
        self::assertContains('LinkedIn-Version: 202609', $requests[0][1]);
        $linkedin = json_decode($requests[0][2], true, 512, JSON_THROW_ON_ERROR);
        self::assertSame('urn:li:organization:100', $linkedin['author']);
        self::assertSame('Teste \\#1', $linkedin['commentary']);
        self::assertSame('https://news.example.test/pt/p/artigo', $linkedin['content']['article']['source']);
        self::assertSame('https://graph.facebook.com/v27.0/200/feed', $requests[1][0]);
        parse_str($requests[1][2], $facebook);
        self::assertSame('https://news.example.test/es/p/articulo', $facebook['link']);
        self::assertSame(['facebook' => 'rejected', 'linkedin' => 'sent'], array_column($social->recent(), 'state', 'network'));
        $social->retryRejected($page, 'facebook');
        $publisher->run('https://news.example.test', $credentials, static fn (): int => 0);
        $social->retryRejected($page, 'facebook');
        self::assertSame('unconfirmed', $social->recent()[0]['state']);
    }

    public function test_public_articles_share_localized_permalinks_and_channel_settings_require_an_administrator(): void
    {
        // Verify the public HTML and administrator boundary with the same router and CSRF middleware as production.
        // Verifica o HTML público e o limite de administração com as mesmas rotas e middleware CSRF de produção.
        $path = tempnam(sys_get_temp_dir(), 'usinadocs-social-');
        try {
            $db = Database::connect($path);
            Schema::migrate($db, dirname(__DIR__));
            Schema::seed($db);
            $editor = new EditorialService($db);
            $editor->createTranslation('page-welcome', 'en', 'welcome');
            $editor->publishLocalized('page-welcome', 'en');
            $editor->createTranslation('page-welcome', 'es', 'bienvenido');
            $editor->publishLocalized('page-welcome', 'es');
            $app = AppFactory::create(dirname(__DIR__), $path);
            $factory = new ServerRequestFactory();
            $request = static fn ($method, $url) => $factory->createServerRequest($method, 'https://news.example.test'.$url);
            $_SESSION = [];

            foreach (['/pt/p/bem-vindo' => 'Copiar link', '/en/p/welcome' => 'Copy link', '/es/p/bienvenido' => 'Copiar enlace'] as $url => $label) {
                $response = $app->handle($request('GET', $url.'?utm_source=example'));
                self::assertSame(200, $response->getStatusCode());
                $html = (string) $response->getBody();
                self::assertStringContainsString('rel="canonical" href="https://news.example.test'.$url.'"', $html);
                self::assertStringContainsString('>'.$label.'</button>', $html);
                self::assertStringContainsString('https://wa.me/?text=', $html);
                self::assertStringContainsString('https://www.linkedin.com/sharing/share-offsite/', $html);
                self::assertStringContainsString('https://www.facebook.com/sharer/sharer.php', $html);
            }

            $settings = $request('GET', '/admin/social');
            self::assertSame(403, $app->handle($settings)->getStatusCode());
            $_SESSION['user'] = ['role' => 'editor'];
            self::assertSame(403, $app->handle($settings)->getStatusCode());
            $post = $request('POST', '/admin/social/linkedin');
            self::assertSame(403, $app->handle($post->withParsedBody([
                'csrf' => CsrfToken::current(), 'enabled' => '1', 'language' => 'en',
            ]))->getStatusCode());
            $_SESSION['user'] = ['role' => 'administrator'];
            $response = $app->handle($settings);
            self::assertSame(200, $response->getStatusCode());
            self::assertStringContainsString('Redes sociais', (string) $response->getBody());
            self::assertSame(403, $app->handle($post->withParsedBody(['enabled' => '1', 'language' => 'en']))->getStatusCode());
            self::assertSame(303, $app->handle($post->withParsedBody([
                'csrf' => CsrfToken::current(), 'enabled' => '1', 'language' => 'en',
            ]))->getStatusCode());
            self::assertSame(['language_code' => 'en', 'enabled' => 1], (new SocialPublication($db))->channels()['linkedin']);
        } finally {
            $_SESSION = [];
            unlink($path);
        }
    }
}
