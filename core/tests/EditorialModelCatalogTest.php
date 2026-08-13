<?php
declare(strict_types=1);
namespace UsinaDocs\Core\Tests;
use PHPUnit\Framework\TestCase;
use UsinaDocs\Core\Content\EditorialService;
use UsinaDocs\Core\Infrastructure\Database;
use UsinaDocs\Core\Infrastructure\Schema;
final class EditorialModelCatalogTest extends TestCase {
 private string $path;
 protected function setUp():void{$this->path=sys_get_temp_dir().DIRECTORY_SEPARATOR.'usinadocs-models-'.bin2hex(random_bytes(8)).'.sqlite';$db=Database::connect($this->path);Schema::migrate($db,dirname(__DIR__));Schema::seed($db);}
 protected function tearDown():void{if(is_file($this->path))@unlink($this->path);}
 public function test_system_models_expose_their_editorial_artifacts():void{$models=(new EditorialService(Database::connect($this->path)))->editorialModels();self::assertCount(5,$models);$lesson=array_values(array_filter($models,static fn(array $model):bool=>$model['content_type']==='lesson'))[0];self::assertSame('Aula',$lesson['label']);self::assertContains(['type'=>'code','label'=>'Exemplo de código','required'=>false],$lesson['artifacts']);}
}
