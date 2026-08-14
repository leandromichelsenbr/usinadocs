<?php
declare(strict_types=1);
namespace UsinaDocs\Core\Tests;
use PHPUnit\Framework\TestCase;
use Slim\Psr7\Factory\ServerRequestFactory;
use UsinaDocs\Core\AppFactory;
use UsinaDocs\Core\Content\EditorialService;
use UsinaDocs\Core\Infrastructure\Database;
use UsinaDocs\Core\Infrastructure\Schema;
final class CitationArtifactTest extends TestCase {
 private string $path;
 protected function setUp():void{$this->path=sys_get_temp_dir().DIRECTORY_SEPARATOR.'usinadocs-citation-'.bin2hex(random_bytes(8)).'.sqlite';$db=Database::connect($this->path);Schema::migrate($db,dirname(__DIR__));Schema::seed($db);}
 protected function tearDown():void{if(is_file($this->path))@unlink($this->path);}
 public function test_citation_is_rendered_with_its_bibliographic_information():void{$db=Database::connect($this->path);$service=new EditorialService($db);$page=$service->create('Artigo','artigo','','','');$service->replaceDraftBlocks($page,'pt',[['type'=>'citation','data'=>['text'=>'Conhecimento deve ser compartilhado.','author'=>'Autor Exemplo','year'=>'2026','work'=>'Obra de referência','page'=>'12','url'=>'https://example.test/fonte']]]);$service->publish($page);$body=AppFactory::create(dirname(__DIR__),$this->path)->handle((new ServerRequestFactory())->createServerRequest('GET','/pt/p/artigo'))->getBody()->__toString();self::assertStringContainsString('class="citation"',$body);self::assertStringContainsString('Autor Exemplo (2026)',$body);self::assertStringContainsString('Obra de referência',$body);}
}
