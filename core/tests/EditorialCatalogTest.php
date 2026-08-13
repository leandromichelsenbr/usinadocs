<?php
declare(strict_types=1);
namespace UsinaDocs\Core\Tests;
use PHPUnit\Framework\TestCase;
use UsinaDocs\Core\Content\EditorialService;
use UsinaDocs\Core\Infrastructure\Database;
use UsinaDocs\Core\Infrastructure\Schema;
final class EditorialCatalogTest extends TestCase {private string $path;protected function setUp():void{$this->path=sys_get_temp_dir().DIRECTORY_SEPARATOR.'usinadocs-catalog-'.bin2hex(random_bytes(8)).'.sqlite';$db=Database::connect($this->path);Schema::migrate($db,dirname(__DIR__));Schema::seed($db);}protected function tearDown():void{if(is_file($this->path))@unlink($this->path);}public function test_catalog_reports_translation_states_and_searches_titles():void{$service=new EditorialService(Database::connect($this->path));$page=$service->create('Catálogo exemplo','catalogo-exemplo','','','');$service->publish($page);$service->createTranslation($page,'en','example-catalog');$items=$service->catalog('Catálogo');self::assertCount(1,$items);self::assertSame('published',$items[0]['pt_status']);self::assertSame('draft',$items[0]['en_status']);self::assertSame('absent',$items[0]['es_status']);}}
