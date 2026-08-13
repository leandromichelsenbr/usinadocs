<?php
declare(strict_types=1);
namespace UsinaDocs\Core\Tests;
use PHPUnit\Framework\TestCase;
use UsinaDocs\Core\Content\EditorialService;
use UsinaDocs\Core\Content\PublishedPageRepository;
use UsinaDocs\Core\Infrastructure\Database;
use UsinaDocs\Core\Infrastructure\Schema;
final class StructuredBlocksTest extends TestCase {private string $path;protected function setUp():void{$this->path=sys_get_temp_dir().DIRECTORY_SEPARATOR.'usinadocs-blocks-'.bin2hex(random_bytes(8)).'.sqlite';$db=Database::connect($this->path);Schema::migrate($db,dirname(__DIR__));Schema::seed($db);}protected function tearDown():void{if(is_file($this->path))@unlink($this->path);}public function test_editorial_draft_preserves_ordered_structured_blocks():void{$db=Database::connect($this->path);$service=new EditorialService($db);$page=$service->create('Página','pagina','','','');$blocks=[['type'=>'heading','data'=>['text'=>'Seção']],['type'=>'notice','data'=>['body'=>'Atenção']],['type'=>'reference','data'=>['title'=>'TDN','url'=>'https://tdn.totvs.com']]];$service->replaceDraftBlocks($page,'pt',$blocks);self::assertSame($blocks,$service->blocksForDraft($page,'pt'));$service->publish($page);$published=(new PublishedPageRepository($db))->findByLocalizedSlug('pt','pagina');self::assertSame('heading',$published['blocks'][0]['type']);self::assertSame('reference',$published['blocks'][2]['type']);}}
