<?php

declare(strict_types=1);

namespace UsinaDocs\Core\Tests;

use PHPUnit\Framework\TestCase;
use Slim\Psr7\Factory\ServerRequestFactory;
use UsinaDocs\Core\AppFactory;
use UsinaDocs\Core\Infrastructure\Database;
use UsinaDocs\Core\Infrastructure\Schema;

final class PublicPageRouteTest extends TestCase
{
    private string $databasePath;

    protected function setUp(): void
    {
        $this->databasePath = sys_get_temp_dir().DIRECTORY_SEPARATOR.'usinadocs-core-'.bin2hex(random_bytes(8)).'.sqlite';

        $database = Database::connect($this->databasePath);
        Schema::migrate($database, dirname(__DIR__));
        Schema::seed($database);
    }

    protected function tearDown(): void
    {
        if (is_file($this->databasePath)) {
            $databasePath = $this->databasePath;
            register_shutdown_function(static function () use ($databasePath): void {
                @unlink($databasePath);
            });
        }
    }

    public function test_the_published_page_route_renders_a_page(): void
    {
        $app = AppFactory::create(dirname(__DIR__), $this->databasePath);
        $request = (new ServerRequestFactory())->createServerRequest('GET', '/pt/p/bem-vindo');
        $response = $app->handle($request);

        self::assertSame(200, $response->getStatusCode());
        self::assertStringContainsString('Bem-vindo ao Usina Docs', (string) $response->getBody());
        self::assertStringContainsString('Página → Revisão → Blocos → Traduções', (string) $response->getBody());
    }

    public function test_an_unknown_localized_slug_is_not_public(): void
    {
        $app = AppFactory::create(dirname(__DIR__), $this->databasePath);
        $request = (new ServerRequestFactory())->createServerRequest('GET', '/pt/p/nao-existe');

        self::assertSame(404, $app->handle($request)->getStatusCode());
    }
}
