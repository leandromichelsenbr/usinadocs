<?php
declare(strict_types=1);
namespace UsinaDocs\Core\Tests;
use PHPUnit\Framework\TestCase;
use UsinaDocs\Core\Content\EditorialService;
use UsinaDocs\Core\Content\PublishedPageRepository;
use UsinaDocs\Core\Infrastructure\Database;
use UsinaDocs\Core\Infrastructure\Schema;
final class TableArtifactTest extends TestCase {
 private string $path;
 protected function setUp():void{$this->path=sys_get_temp_dir().DIRECTORY_SEPARATOR.'usinadocs-table-'.bin2hex(random_bytes(8)).'.sqlite';$db=Database::connect($this->path);Schema::migrate($db,dirname(__DIR__));Schema::seed($db);}
 protected function tearDown():void{if(is_file($this->path))@unlink($this->path);}
 public function test_table_block_is_preserved_when_published():void{$db=Database::connect($this->path);$service=new EditorialService($db);$page=$service->create('Tabela','tabela','','','');$service->replaceDraftBlocks($page,'pt',[['type'=>'table','data'=>['headers'=>['Função','Uso'],'rows'=>[['Len','Tamanho'],['Empty','Validação']]]]]);$service->publish($page);$blocks=(new PublishedPageRepository($db))->findByLocalizedSlug('pt','tabela')['blocks'];self::assertSame('table',$blocks[0]['type']);self::assertSame('Função',$blocks[0]['data']['headers'][0]);self::assertSame('Validação',$blocks[0]['data']['rows'][1][1]);}
}
