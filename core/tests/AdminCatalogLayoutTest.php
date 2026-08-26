<?php
declare(strict_types=1);
namespace UsinaDocs\Core\Tests;
use PHPUnit\Framework\TestCase;
use Slim\Psr7\Factory\ServerRequestFactory;
use UsinaDocs\Core\AppFactory;
use UsinaDocs\Core\Content\EditorialService;
use UsinaDocs\Core\Infrastructure\Database;
use UsinaDocs\Core\Infrastructure\Schema;
final class AdminCatalogLayoutTest extends TestCase {
 private string $path;
 protected function setUp():void{$this->path=sys_get_temp_dir().DIRECTORY_SEPARATOR.'usinadocs-admin-catalog-'.bin2hex(random_bytes(8)).'.sqlite';$db=Database::connect($this->path);Schema::migrate($db,dirname(__DIR__));Schema::seed($db);$_SESSION=['user'=>['role'=>'administrator','name'=>'Administrator']];}
 protected function tearDown():void{$_SESSION=[];if(is_file($this->path))@unlink($this->path);}
 public function test_all_page_actions_are_consolidated_in_the_catalog_table():void{$app=AppFactory::create(dirname(__DIR__),$this->path);$_SESSION=['user'=>['role'=>'administrator','name'=>'Administrator']];$response=$app->handle((new ServerRequestFactory())->createServerRequest('GET','/admin'));$body=(string)$response->getBody();self::assertSame(200,$response->getStatusCode());self::assertStringContainsString('Catálogo editorial',$body);self::assertStringContainsString('/admin/pages/page-welcome/history/pt',$body);self::assertStringContainsString('Criar revisão',$body);self::assertStringNotContainsString('Rascunhos em português',$body);self::assertStringNotContainsString('Publicadas em português',$body);}
 public function test_a_draft_action_appears_next_to_the_published_status():void{$service=new EditorialService(Database::connect($this->path));$service->createRevision('page-welcome');$app=AppFactory::create(dirname(__DIR__),$this->path);$_SESSION=['user'=>['role'=>'administrator','name'=>'Administrator']];$body=(string)$app->handle((new ServerRequestFactory())->createServerRequest('GET','/admin'))->getBody();self::assertStringContainsString('Publicado',$body);self::assertStringContainsString('Editar rascunho',$body);self::assertStringContainsString('/admin/pages/page-welcome/publish',$body);}
}
