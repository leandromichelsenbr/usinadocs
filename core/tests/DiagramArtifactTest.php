<?php
declare(strict_types=1);
namespace UsinaDocs\Core\Tests;
use PHPUnit\Framework\TestCase;
use Slim\Psr7\Factory\ServerRequestFactory;
use UsinaDocs\Core\AppFactory;
use UsinaDocs\Core\Content\EditorialService;
use UsinaDocs\Core\Infrastructure\Database;
use UsinaDocs\Core\Infrastructure\Schema;
final class DiagramArtifactTest extends TestCase {
 private string $path;
 protected function setUp():void{$this->path=sys_get_temp_dir().DIRECTORY_SEPARATOR.'usinadocs-diagram-'.bin2hex(random_bytes(8)).'.sqlite';$db=Database::connect($this->path);Schema::migrate($db,dirname(__DIR__));Schema::seed($db);}
 protected function tearDown():void{if(is_file($this->path))@unlink($this->path);}
 public function test_diagram_is_rendered_as_a_lightweight_flow():void{$db=Database::connect($this->path);$service=new EditorialService($db);$page=$service->create('Fluxo','fluxo','','','');$service->replaceDraftBlocks($page,'pt',[['type'=>'diagram','data'=>['title'=>'Fluxo de validação','nodes'=>['Início','Validar','Processar'],'edges'=>['Início -> Validar','Validar -> Processar']]]]);$service->publish($page);$body=AppFactory::create(dirname(__DIR__),$this->path)->handle((new ServerRequestFactory())->createServerRequest('GET','/pt/p/fluxo'))->getBody()->__toString();self::assertStringContainsString('class="diagram"',$body);self::assertStringContainsString('Fluxo de validação',$body);self::assertStringContainsString('Início -&gt; Validar',$body);}
}
