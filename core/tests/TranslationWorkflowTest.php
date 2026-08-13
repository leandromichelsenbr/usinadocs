<?php
declare(strict_types=1);
namespace UsinaDocs\Core\Tests;
use PHPUnit\Framework\TestCase;
use UsinaDocs\Core\Content\EditorialService;
use UsinaDocs\Core\Content\PublishedPageRepository;
use UsinaDocs\Core\Infrastructure\Database;
use UsinaDocs\Core\Infrastructure\Schema;
final class TranslationWorkflowTest extends TestCase {
 private string $path;
 protected function setUp():void{$this->path=sys_get_temp_dir().DIRECTORY_SEPARATOR.'usinadocs-translation-'.bin2hex(random_bytes(8)).'.sqlite';$db=Database::connect($this->path);Schema::migrate($db,dirname(__DIR__));Schema::seed($db);}
 protected function tearDown():void{if(is_file($this->path))@unlink($this->path);}
 public function test_translation_is_not_public_until_its_own_draft_is_published():void{$db=Database::connect($this->path);$service=new EditorialService($db);$page=$service->create('Olá','ola','Resumo','Português','');$service->publish($page);$service->createTranslation($page,'en','hello');$service->updateLocalizedDraft($page,'en','Hello','Summary','English','');$repo=new PublishedPageRepository($db);self::assertNull($repo->findByLocalizedSlug('en','hello'));$service->publishLocalized($page,'en');self::assertSame('Hello',$repo->findByLocalizedSlug('en','hello')['title']);$portuguese=$repo->findByLocalizedSlug('pt','ola');self::assertSame('Olá',$portuguese['title']);self::assertCount(2,$portuguese['translations']);self::assertSame('en',$portuguese['translations'][0]['language_code']);$service->createLocalizedRevision($page,'en');$service->updateLocalizedDraft($page,'en','Updated Hello','Summary','English','');self::assertSame('Hello',$repo->findByLocalizedSlug('en','hello')['title']);$service->publishLocalized($page,'en');self::assertSame('Updated Hello',$repo->findByLocalizedSlug('en','hello')['title']);}
}
