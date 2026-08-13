<?php
declare(strict_types=1);
namespace UsinaDocs\Core\Tests;
use PHPUnit\Framework\TestCase;
use UsinaDocs\Core\Content\EditorialService;
use UsinaDocs\Core\Infrastructure\Database;
use UsinaDocs\Core\Infrastructure\Schema;
final class TranslationReviewTest extends TestCase {
 private string $path;
 protected function setUp():void{$this->path=sys_get_temp_dir().DIRECTORY_SEPARATOR.'usinadocs-review-'.bin2hex(random_bytes(8)).'.sqlite';$db=Database::connect($this->path);Schema::migrate($db,dirname(__DIR__));Schema::seed($db);}
 protected function tearDown():void{if(is_file($this->path))@unlink($this->path);}
 public function test_review_marks_translation_outdated_after_portuguese_revision():void{
  $service=new EditorialService(Database::connect($this->path));$page=$service->create('Página de exemplo','exemplo','Resumo','Texto','');$service->publish($page);
  $service->createTranslation($page,'en','example');$service->updateLocalizedDraft($page,'en','Example','Summary','Text','');$service->publishLocalized($page,'en');
  self::assertSame('published',$service->translationReview('Página')[0]['en_status']);
  $service->createRevision($page);$service->updateDraft($page,'Página revisada','Resumo','Texto revisado','');$service->publish($page);
  self::assertSame('outdated',$service->translationReview('Página')[0]['en_status']);
  self::assertNotNull($service->refreshTranslation($page,'en'));
  self::assertSame('draft',$service->translationReview('Página')[0]['en_status']);
 }
}
