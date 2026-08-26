<?php

declare(strict_types=1);

namespace UsinaDocs\Core\Tests;

use PHPUnit\Framework\TestCase;
use Slim\Psr7\Factory\ServerRequestFactory;
use UsinaDocs\Core\AppFactory;
use UsinaDocs\Core\Content\ReusableArtifactService;
use UsinaDocs\Core\Infrastructure\Database;
use UsinaDocs\Core\Infrastructure\Schema;

final class ArtifactTranslationReviewTest extends TestCase
{
    private string $path;
    protected function setUp():void{$this->path=sys_get_temp_dir().'/usinadocs-artifact-review-'.bin2hex(random_bytes(5)).'.sqlite';$db=Database::connect($this->path);Schema::migrate($db,dirname(__DIR__));Schema::seed($db);}
    protected function tearDown():void{$_SESSION=[];@unlink($this->path);}

    public function test_translation_becomes_outdated_when_portuguese_source_changes():void
    {
        $service=new ReusableArtifactService(Database::connect($this->path));
        $id=$service->create('aviso-traduzido','notice','Aviso','',['body'=>'Original']);
        $service->publish($id);
        $service->createTranslation($id,'en');
        $service->updateTranslationDraft($id,'en','Notice','',['body'=>'Translated']);
        $service->publishTranslation($id,'en');
        self::assertSame('published',$service->translationReview('Aviso')[0]['en_status']);
        $service->createRevision($id);
        $service->updateDraft($id,'Aviso atualizado','',['body'=>'Fonte atualizada']);
        $service->publish($id);
        self::assertSame('outdated',$service->translationReview('Aviso')[0]['en_status']);
        self::assertNotNull($service->refreshTranslation($id,'en'));
        self::assertSame('draft',$service->translationReview('Aviso')[0]['en_status']);
        self::assertSame('Translated',$service->translationDraft($id,'en')['data']['body']);
    }

    public function test_translation_review_screen_lists_reusable_artifacts():void
    {
        $app=AppFactory::create(dirname(__DIR__),$this->path);
        $_SESSION=['user'=>['role'=>'administrator']];
        $response=$app->handle((new ServerRequestFactory())->createServerRequest('GET','/admin/translations'));
        self::assertSame(200,$response->getStatusCode());
        self::assertStringContainsString('Artefatos reutilizáveis',(string)$response->getBody());
    }
}
