<?php
declare(strict_types=1);
namespace UsinaDocs\Core\Tests;
use PHPUnit\Framework\TestCase;
use UsinaDocs\Core\Content\EditorialService;
use UsinaDocs\Core\Content\PublishedPageRepository;
use UsinaDocs\Core\Infrastructure\Database;
use UsinaDocs\Core\Infrastructure\Schema;
final class EditorialRevisionTest extends TestCase {
 private string $path;
 protected function setUp(): void {$this->path=sys_get_temp_dir().DIRECTORY_SEPARATOR.'usinadocs-revision-'.bin2hex(random_bytes(8)).'.sqlite';$db=Database::connect($this->path);Schema::migrate($db,dirname(__DIR__));Schema::seed($db);}
 protected function tearDown(): void {if(is_file($this->path))@unlink($this->path);}
 public function test_draft_revision_does_not_replace_the_published_content_until_published(): void {$db=Database::connect($this->path);$service=new EditorialService($db);$page=$service->create('Original','original','Resumo','Versão 1','');$service->publish($page);$before=(new PublishedPageRepository($db))->findByLocalizedSlug('pt','original');$service->createRevision($page);$service->updateDraft($page,'Atualizada','Novo resumo','Versão 2','');$during=(new PublishedPageRepository($db))->findByLocalizedSlug('pt','original');$service->publish($page);$after=(new PublishedPageRepository($db))->findByLocalizedSlug('pt','original');self::assertSame('Original',$before['title']);self::assertSame('Original',$during['title']);self::assertSame('Atualizada',$after['title']);self::assertSame('Versão 2',$after['blocks'][0]['data']['body']);}
}
