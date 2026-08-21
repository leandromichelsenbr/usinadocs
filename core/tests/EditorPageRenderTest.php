<?php
declare(strict_types=1);
namespace UsinaDocs\Core\Tests;
use PHPUnit\Framework\TestCase;
use Slim\Psr7\Factory\ServerRequestFactory;
use UsinaDocs\Core\AppFactory;
use UsinaDocs\Core\Infrastructure\Database;
use UsinaDocs\Core\Infrastructure\Schema;
final class EditorPageRenderTest extends TestCase {
 private string $path;
 protected function setUp():void{$this->path=sys_get_temp_dir().DIRECTORY_SEPARATOR.'usinadocs-editor-'.bin2hex(random_bytes(8)).'.sqlite';$db=Database::connect($this->path);Schema::migrate($db,dirname(__DIR__));Schema::seed($db);$_SESSION=['user'=>['role'=>'administrator']];}
 protected function tearDown():void{$_SESSION=[];if(is_file($this->path))@unlink($this->path);}
 public function test_new_page_editor_uses_the_selected_editorial_model():void{$app=AppFactory::create(dirname(__DIR__),$this->path);$_SESSION=['user'=>['role'=>'administrator']];$response=$app->handle((new ServerRequestFactory())->createServerRequest('GET','/admin/pages/new'));self::assertSame(200,$response->getStatusCode());$body=(string)$response->getBody();self::assertStringContainsString('Modelo da página',$body);self::assertStringContainsString('id="editorial-models"',$body);self::assertStringContainsString('model.artifacts.forEach',$body);self::assertStringContainsString("block.type==='table'",$body);}
}
