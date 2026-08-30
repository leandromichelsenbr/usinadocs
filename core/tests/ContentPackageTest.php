<?php

declare(strict_types=1);

namespace UsinaDocs\Core\Tests;

use PHPUnit\Framework\TestCase;
use Slim\Psr7\Factory\ServerRequestFactory;
use UsinaDocs\Core\AppFactory;
use UsinaDocs\Core\Content\ContentPackageService;
use UsinaDocs\Core\Content\EditorialService;
use UsinaDocs\Core\Infrastructure\Database;
use UsinaDocs\Core\Infrastructure\Schema;

final class ContentPackageTest extends TestCase
{
    private string $path;
    protected function setUp():void{$this->path=sys_get_temp_dir().'/usinadocs-package-'.bin2hex(random_bytes(5)).'.sqlite';$db=Database::connect($this->path);Schema::migrate($db,dirname(__DIR__));Schema::seed($db);}
    protected function tearDown():void{$_SESSION=[];@unlink($this->path);}

    public function test_a_published_multilingual_page_exports_and_previews_without_writes():void
    {
        $db=Database::connect($this->path);$editorial=new EditorialService($db);
        $editorial->createTranslation('page-welcome','en','welcome');
        $editorial->updateLocalizedDraft('page-welcome','en','Welcome','English summary','English body','Return');
        $editorial->publishLocalized('page-welcome','en');
        $service=new ContentPackageService($db);$package=$service->exportPublishedPage('page-welcome');
        self::assertNotNull($package);self::assertStringEndsWith('.zip',$package['filename']);self::assertSame('usinadocs-content-package',$package['manifest']['format']);self::assertSame(2,$package['manifest']['contents']['revisions']);
        $before=(int)$db->query('SELECT COUNT(*) FROM pages')->fetchColumn();$preview=$service->preview($package['content']);$after=(int)$db->query('SELECT COUNT(*) FROM pages')->fetchColumn();
        self::assertTrue($preview['valid'],implode('; ',$preview['errors']));self::assertSame(1,$preview['summary']['update']);self::assertSame(2,$preview['summary']['revisions']);self::assertSame($before,$after);
    }

    public function test_preview_rejects_a_corrupted_package():void
    {
        $service=new ContentPackageService(Database::connect($this->path));$package=$service->exportPublishedPage('page-welcome');$corrupted=$package['content'];$corrupted[80]=chr(ord($corrupted[80])^1);$preview=$service->preview($corrupted);self::assertFalse($preview['valid']);self::assertNotEmpty($preview['errors']);
    }

    public function test_administrator_can_download_a_page_package():void
    {
        $app=AppFactory::create(dirname(__DIR__),$this->path);$_SESSION=['user'=>['role'=>'administrator','name'=>'Administrator']];$response=$app->handle((new ServerRequestFactory())->createServerRequest('GET','/admin/pages/page-welcome/export'));self::assertSame(200,$response->getStatusCode());self::assertSame('application/zip',$response->getHeaderLine('Content-Type'));self::assertStringContainsString('.zip',$response->getHeaderLine('Content-Disposition'));
    }

    public function test_a_new_page_is_imported_atomically_and_existing_ids_are_blocked():void
    {
        $sourcePath=sys_get_temp_dir().'/usinadocs-package-source-'.bin2hex(random_bytes(5)).'.sqlite';
        try{$sourceDb=Database::connect($sourcePath);Schema::migrate($sourceDb,dirname(__DIR__));Schema::seed($sourceDb);$sourceEditorial=new EditorialService($sourceDb);$id=$sourceEditorial->create('Página transportada','pagina-transportada','Resumo','','','','article');$sourceEditorial->replaceDraftBlocks($id,'pt',[['type'=>'text','data'=>['body'=>'Conteúdo transportado']],['type'=>'code','data'=>['language'=>'advpl','code'=>'Return']]]);$sourceEditorial->publish($id);$package=(new ContentPackageService($sourceDb))->exportPublishedPage($id);
            $targetDb=Database::connect($this->path);$target=new ContentPackageService($targetDb);$result=$target->import($package['content']);self::assertTrue($result['imported'],implode('; ',$result['errors']));self::assertSame(1,$result['pages']);self::assertSame(1,$result['revisions']);$page=$targetDb->prepare('SELECT pl.slug,r.title FROM page_localizations pl JOIN page_revisions r ON r.id=pl.published_revision_id WHERE pl.page_id=:id');$page->execute(['id'=>$id]);self::assertSame(['slug'=>'pagina-transportada','title'=>'Página transportada'],$page->fetch());$blocks=$targetDb->prepare('SELECT COUNT(*) FROM blocks b JOIN page_revisions r ON r.id=b.page_revision_id WHERE r.page_id=:id');$blocks->execute(['id'=>$id]);self::assertSame(2,(int)$blocks->fetchColumn());
            $before=(int)$targetDb->query('SELECT COUNT(*) FROM pages')->fetchColumn();$second=$target->import($package['content']);self::assertFalse($second['imported']);self::assertSame($before,(int)$targetDb->query('SELECT COUNT(*) FROM pages')->fetchColumn());
        }finally{@unlink($sourcePath);}
    }

    public function test_an_existing_page_can_be_kept_or_updated_as_an_immutable_revision():void
    {
        $sourcePath=sys_get_temp_dir().'/usinadocs-package-update-'.bin2hex(random_bytes(5)).'.sqlite';
        try{$sourceDb=Database::connect($sourcePath);Schema::migrate($sourceDb,dirname(__DIR__));Schema::seed($sourceDb);$editorial=new EditorialService($sourceDb);$id=$editorial->create('Versão um','pagina-atualizavel','','','','','article');$editorial->replaceDraftBlocks($id,'pt',[['type'=>'text','data'=>['body'=>'Primeira versão']]]);$editorial->publish($id);$sourcePackages=new ContentPackageService($sourceDb);$first=$sourcePackages->exportPublishedPage($id);
            $targetDb=Database::connect($this->path);$targetPackages=new ContentPackageService($targetDb);self::assertTrue($targetPackages->import($first['content'])['imported']);$editorial->createRevision($id);$editorial->updateDraft($id,'Versão dois','','Segunda versão','');$editorial->publish($id);$second=$sourcePackages->exportPublishedPage($id);
            $kept=$targetPackages->import($second['content'],'keep_local');self::assertTrue($kept['imported']);self::assertSame(1,$kept['skipped']);$title=$targetDb->prepare('SELECT r.title FROM page_localizations pl JOIN page_revisions r ON r.id=pl.published_revision_id WHERE pl.page_id=:page AND pl.language_code=\'pt\'');$title->execute(['page'=>$id]);self::assertSame('Versão um',$title->fetchColumn());
            $updated=$targetPackages->import($second['content'],'import_revision');self::assertTrue($updated['imported'],implode('; ',$updated['errors']));self::assertSame(1,$updated['updated']);self::assertSame(1,$updated['revisions']);$title->execute(['page'=>$id]);self::assertSame('Versão dois',$title->fetchColumn());$count=$targetDb->prepare('SELECT COUNT(*) FROM page_revisions WHERE page_id=:page');$count->execute(['page'=>$id]);self::assertSame(2,(int)$count->fetchColumn());
        }finally{@unlink($sourcePath);}
    }
}
