<?php

declare(strict_types=1);

namespace UsinaDocs\Core\Tests;

use PHPUnit\Framework\TestCase;
use Slim\Psr7\Factory\ServerRequestFactory;
use UsinaDocs\Core\AppFactory;
use UsinaDocs\Core\Content\EditorialService;
use UsinaDocs\Core\Content\ReusableArtifactService;
use UsinaDocs\Core\Infrastructure\Database;
use UsinaDocs\Core\Infrastructure\Schema;

final class ModelAndArtifactRevisionHistoryTest extends TestCase
{
    private string $path;

    protected function setUp(): void
    {
        $this->path=sys_get_temp_dir().'/usinadocs-history-'.bin2hex(random_bytes(5)).'.sqlite';
        $database=Database::connect($this->path);
        Schema::migrate($database,dirname(__DIR__));
        Schema::seed($database);
    }

    protected function tearDown(): void { @unlink($this->path); }

    public function test_model_history_can_be_compared_and_restored_as_a_new_draft():void
    {
        $service=new EditorialService(Database::connect($this->path));
        $id=$service->createEditorialModel('guide','Guia','Primeira versão.',[['type'=>'text','label'=>'Texto','required'=>true]]);
        $service->publishEditorialModel($id);
        $service->createEditorialModelRevision($id);
        $service->updateEditorialModelDraft($id,'Guia atualizado','Segunda versão.',[['type'=>'heading','label'=>'Seção','required'=>true]]);
        $service->publishEditorialModel($id);
        $history=$service->editorialModelHistory($id);
        self::assertCount(2,$history);
        self::assertTrue($history[0]['is_current']);
        $comparison=$service->compareEditorialModelRevisions($id,$history[1]['id'],$history[0]['id']);
        self::assertSame('Guia',$comparison['from']['label']);
        self::assertSame('Guia atualizado',$comparison['to']['label']);
        self::assertNotNull($service->restoreEditorialModelRevision($id,$history[1]['id']));
        self::assertSame('Guia',$service->editorialModelDraft($id)['label']);
        self::assertSame(3,$service->editorialModelDraft($id)['number']);
    }

    public function test_artifact_history_can_be_compared_and_restored_as_a_new_draft():void
    {
        $service=new ReusableArtifactService(Database::connect($this->path));
        $id=$service->create('aviso-historico','notice','Aviso','Primeira versão.',['body'=>'Primeiro']);
        $service->publish($id);
        $service->createRevision($id);
        $service->updateDraft($id,'Aviso atualizado','Segunda versão.',['body'=>'Segundo']);
        $service->publish($id);
        $history=$service->history($id);
        self::assertCount(2,$history);
        self::assertTrue($history[0]['is_current']);
        $comparison=$service->compareRevisions($id,'pt',$history[1]['id'],$history[0]['id']);
        self::assertSame('Primeiro',$comparison['from']['data']['body']);
        self::assertSame('Segundo',$comparison['to']['data']['body']);
        self::assertNotNull($service->restoreRevision($id,'pt',$history[1]['id']));
        self::assertSame('Primeiro',$service->draft($id)['data']['body']);
        self::assertSame(3,$service->draft($id)['number']);
    }

    public function test_model_and_artifact_history_screens_are_available():void
    {
        $artifact=new ReusableArtifactService(Database::connect($this->path));
        $artifactId=$artifact->create('historico-tela','text','Texto','',['body'=>'Conteúdo']);
        $artifact->publish($artifactId);
        $app=AppFactory::create(dirname(__DIR__),$this->path);
        $_SESSION=['user'=>['role'=>'administrator']];
        $factory=new ServerRequestFactory();
        $model=$app->handle($factory->createServerRequest('GET','/admin/models/model-article/history'));
        $artifactResponse=$app->handle($factory->createServerRequest('GET','/admin/artifacts/'.$artifactId.'/history/pt'));
        self::assertSame(200,$model->getStatusCode());
        self::assertStringContainsString('Histórico do modelo',(string)$model->getBody());
        self::assertSame(200,$artifactResponse->getStatusCode());
        self::assertStringContainsString('Histórico do artefato',(string)$artifactResponse->getBody());
    }
}
