<?php
declare(strict_types=1);
namespace UsinaDocs\Core\Tests;
use PHPUnit\Framework\TestCase;
use Slim\Psr7\Factory\ServerRequestFactory;
use UsinaDocs\Core\AppFactory;
use UsinaDocs\Core\Content\EditorialService;
use UsinaDocs\Core\Infrastructure\Database;
use UsinaDocs\Core\Infrastructure\Schema;
final class ImageArtifactTest extends TestCase {
 private string $path;
 protected function setUp():void{$this->path=sys_get_temp_dir().DIRECTORY_SEPARATOR.'usinadocs-image-artifact-'.bin2hex(random_bytes(8)).'.sqlite';$db=Database::connect($this->path);Schema::migrate($db,dirname(__DIR__));Schema::seed($db);}
 protected function tearDown():void{if(is_file($this->path))@unlink($this->path);}
 public function test_image_can_be_deferred_until_the_visitor_requests_it():void{$db=Database::connect($this->path);$service=new EditorialService($db);$page=$service->create('Imagem','imagem','','','');$service->replaceDraftBlocks($page,'pt',[['type'=>'image','data'=>['id'=>'media-example','display'=>'on_demand','button_label'=>'Carregar exemplo']]]);$service->publish($page);$body=AppFactory::create(dirname(__DIR__),$this->path)->handle((new ServerRequestFactory())->createServerRequest('GET','/pt/p/imagem'))->getBody()->__toString();self::assertStringContainsString('class="image-trigger"',$body);self::assertStringContainsString('Carregar exemplo',$body);self::assertStringContainsString('data-image-src="/media/media-example"',$body);self::assertStringContainsString('class="image-modal"',$body);self::assertStringContainsString('max-width:none',$body);}
 public function test_existing_static_image_is_rendered_immediately():void{$db=Database::connect($this->path);$service=new EditorialService($db);$page=$service->create('Imagem estática','imagem-estatica','','','');$service->replaceDraftBlocks($page,'pt',[['type'=>'image','data'=>['id'=>'media-static']]]);$service->publish($page);$body=AppFactory::create(dirname(__DIR__),$this->path)->handle((new ServerRequestFactory())->createServerRequest('GET','/pt/p/imagem-estatica'))->getBody()->__toString();self::assertStringContainsString('src="/media/media-static"',$body);self::assertStringContainsString('class="content-image"',$body);}
}
