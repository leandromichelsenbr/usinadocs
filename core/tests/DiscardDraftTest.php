<?php
declare(strict_types=1);
namespace UsinaDocs\Core\Tests;
use PHPUnit\Framework\TestCase;
use UsinaDocs\Core\Content\EditorialService;
use UsinaDocs\Core\Content\PublishedPageRepository;
use UsinaDocs\Core\Infrastructure\Database;
use UsinaDocs\Core\Infrastructure\Schema;
final class DiscardDraftTest extends TestCase {
 private string $path;
 protected function setUp():void{$this->path=sys_get_temp_dir().DIRECTORY_SEPARATOR.'usinadocs-discard-'.bin2hex(random_bytes(8)).'.sqlite';$db=Database::connect($this->path);Schema::migrate($db,dirname(__DIR__));Schema::seed($db);}
 protected function tearDown():void{if(is_file($this->path))@unlink($this->path);}
 public function test_discarding_a_revision_preserves_the_published_page():void{$db=Database::connect($this->path);$service=new EditorialService($db);$page=$service->create('Página','pagina','','Texto','');$service->publish($page);$service->createRevision($page);$service->updateDraft($page,'Rascunho','', 'Novo texto','');$service->discardDraft($page,'pt');self::assertNull($service->draft($page));self::assertSame('Página',(new PublishedPageRepository($db))->findByLocalizedSlug('pt','pagina')['title']);}
 public function test_discarding_an_unpublished_page_removes_its_empty_container():void{$db=Database::connect($this->path);$service=new EditorialService($db);$page=$service->create('Rascunho','rascunho','','','');$service->discardDraft($page,'pt');self::assertNull($service->draft($page));self::assertSame(0,(int)$db->query("SELECT COUNT(*) FROM pages WHERE id=".$db->quote($page))->fetchColumn());}
}
