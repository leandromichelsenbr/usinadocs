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
}
