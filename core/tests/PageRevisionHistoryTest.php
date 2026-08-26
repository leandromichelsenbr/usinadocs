<?php
declare(strict_types=1);
namespace UsinaDocs\Core\Tests;
use PHPUnit\Framework\TestCase;
use Slim\Psr7\Factory\ServerRequestFactory;
use UsinaDocs\Core\AppFactory;
use UsinaDocs\Core\Content\EditorialService;
use UsinaDocs\Core\Content\PublishedPageRepository;
use UsinaDocs\Core\Infrastructure\Database;
use UsinaDocs\Core\Infrastructure\Schema;
final class PageRevisionHistoryTest extends TestCase {
 private string $path;
 protected function setUp():void{$this->path=sys_get_temp_dir().DIRECTORY_SEPARATOR.'usinadocs-history-'.bin2hex(random_bytes(8)).'.sqlite';$db=Database::connect($this->path);Schema::migrate($db,dirname(__DIR__));Schema::seed($db);$_SESSION=['user'=>['role'=>'administrator']];}
 protected function tearDown():void{$_SESSION=[];if(is_file($this->path))@unlink($this->path);}
 public function test_history_comparison_and_restore_preserve_published_revisions():void{$db=Database::connect($this->path);$service=new EditorialService($db);$pageId=$service->create('Versão inicial','historico','','Primeiro texto','');$service->publish($pageId);$service->createRevision($pageId);$service->updateDraft($pageId,'Versão atual','','Segundo texto','');$service->publish($pageId);$history=$service->revisionHistory($pageId,'pt');self::assertCount(2,$history);self::assertTrue($history[0]['is_current']);self::assertSame(2,$history[0]['number']);$comparison=$service->compareRevisions($pageId,'pt',$history[1]['id'],$history[0]['id']);self::assertNotNull($comparison);self::assertTrue($comparison['blocks'][0]['changed']);self::assertSame('Primeiro texto',$comparison['from']['blocks'][0]['data']['body']);$restored=$service->restoreRevision($pageId,'pt',$history[1]['id']);self::assertNotNull($restored);$draft=$service->draft($pageId);self::assertSame('Versão inicial',$draft['title']);self::assertSame('Primeiro texto',$service->blocksForDraft($pageId,'pt')[0]['data']['body']);self::assertSame('Versão atual',(new PublishedPageRepository($db))->findByLocalizedSlug('pt','historico')['title']);}
 public function test_history_screen_is_available_to_administrators():void{$app=AppFactory::create(dirname(__DIR__),$this->path);$_SESSION=['user'=>['role'=>'administrator']];$response=$app->handle((new ServerRequestFactory())->createServerRequest('GET','/admin/pages/page-welcome/history/pt'));self::assertSame(200,$response->getStatusCode());self::assertStringContainsString('Histórico de revisões',(string)$response->getBody());}
}
