<?php

declare(strict_types=1);

namespace UsinaDocs\Core\Tests;

use PHPUnit\Framework\TestCase;
use UsinaDocs\Core\Content\PublishedPageRepository;
use UsinaDocs\Core\Infrastructure\Database;
use UsinaDocs\Core\Infrastructure\Schema;

final class PublishedPageRepositoryTest extends TestCase
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
            unlink($this->databasePath);
        }
    }

    public function test_it_returns_a_published_page_with_ordered_blocks(): void
    {
        $page = (new PublishedPageRepository(Database::connect($this->databasePath)))
            ->findByLocalizedSlug('pt', 'bem-vindo');

        self::assertNotNull($page);
        self::assertSame('Bem-vindo ao Usina Docs', $page['title']);
        self::assertCount(2, $page['blocks']);
        self::assertSame('text', $page['blocks'][0]['type']);
        self::assertSame('code', $page['blocks'][1]['type']);
    }

    public function test_it_does_not_return_an_unknown_or_unpublished_route(): void
    {
        $page = (new PublishedPageRepository(Database::connect($this->databasePath)))
            ->findByLocalizedSlug('pt', 'nao-existe');

        self::assertNull($page);
    }
}
