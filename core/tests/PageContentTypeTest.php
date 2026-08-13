<?php
declare(strict_types=1);
namespace UsinaDocs\Core\Tests;
use PHPUnit\Framework\TestCase;
use UsinaDocs\Core\Content\EditorialService;
use UsinaDocs\Core\Content\PublishedPageRepository;
use UsinaDocs\Core\Infrastructure\Database;
use UsinaDocs\Core\Infrastructure\Schema;
final class PageContentTypeTest extends TestCase {
 private string $path;
 protected function setUp():void{$this->path=sys_get_temp_dir().DIRECTORY_SEPARATOR.'usinadocs-type-'.bin2hex(random_bytes(8)).'.sqlite';$db=Database::connect($this->path);Schema::migrate($db,dirname(__DIR__));Schema::seed($db);}
 protected function tearDown():void{if(is_file($this->path))@unlink($this->path);}
 public function test_page_type_is_saved_and_available_on_the_published_page():void{$db=Database::connect($this->path);$service=new EditorialService($db);$page=$service->create('For Next','for-next','Aula inicial','Texto','', '', 'lesson');$service->publish($page);self::assertSame('lesson',(new PublishedPageRepository($db))->findByLocalizedSlug('pt','for-next')['content_type']);$service->setContentType($page,'article');$service->createRevision($page);self::assertSame('article',$service->draft($page)['content_type']);}
}
