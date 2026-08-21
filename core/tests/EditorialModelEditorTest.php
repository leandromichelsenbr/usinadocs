<?php
declare(strict_types=1);
namespace UsinaDocs\Core\Tests;
use PHPUnit\Framework\TestCase;
use Slim\Psr7\Factory\ServerRequestFactory;
use UsinaDocs\Core\AppFactory;
use UsinaDocs\Core\Content\EditorialService;
use UsinaDocs\Core\Infrastructure\Database;
use UsinaDocs\Core\Infrastructure\Schema;
final class EditorialModelEditorTest extends TestCase {
 private string $path;
 protected function setUp():void{$this->path=sys_get_temp_dir().DIRECTORY_SEPARATOR.'usinadocs-model-editor-'.bin2hex(random_bytes(8)).'.sqlite';$db=Database::connect($this->path);Schema::migrate($db,dirname(__DIR__));Schema::seed($db);$_SESSION=['user'=>['role'=>'administrator']];}
 protected function tearDown():void{$_SESSION=[];if(is_file($this->path))@unlink($this->path);}
 public function test_model_changes_are_drafts_until_published():void{$service=new EditorialService(Database::connect($this->path));$id=$service->createEditorialModel('procedure','Procedimento','Instrução controlada.',[['type'=>'heading','label'=>'Etapa','required'=>true],['type'=>'notice','label'=>'Cuidado','required'=>false]]);$catalog=array_values(array_filter($service->editorialModels(),static fn(array $model):bool=>$model['id']===$id))[0];self::assertTrue($catalog['has_draft']);self::assertSame('Procedimento',$catalog['label']);self::assertSame([], $catalog['artifacts']);$service->updateEditorialModelDraft($id,'Procedimento operacional','Fluxo de execução.',[['type'=>'heading','label'=>'Etapa','required'=>true]]);$service->publishEditorialModel($id);$catalog=array_values(array_filter($service->editorialModels(),static fn(array $model):bool=>$model['id']===$id))[0];self::assertFalse($catalog['has_draft']);self::assertSame('Procedimento operacional',$catalog['label']);self::assertSame([['type'=>'heading','label'=>'Etapa','required'=>true]],$catalog['artifacts']);}
 public function test_model_editor_uses_the_content_editor_category():void{$app=AppFactory::create(dirname(__DIR__),$this->path);$_SESSION=['user'=>['role'=>'administrator']];$response=$app->handle((new ServerRequestFactory())->createServerRequest('GET','/admin/models/new'));self::assertSame(200,$response->getStatusCode());$body=(string)$response->getBody();self::assertStringContainsString('Editor de conteúdo',$body);self::assertStringContainsString('<option>Modelo</option>',$body);self::assertStringContainsString('Artefatos permitidos',$body);}
 public function test_required_model_artifacts_are_reported_before_publication():void{$service=new EditorialService(Database::connect($this->path));$id=$service->create('Página incompleta','pagina-incompleta','','','','','reference');self::assertSame(['Referência'],$service->missingRequiredArtifacts($id));$service->replaceDraftBlocks($id,'pt',[['type'=>'text','data'=>['body'=>'Conteúdo']],['type'=>'reference','data'=>['title'=>'Fonte','url'=>'https://example.test']]]);self::assertSame([],$service->missingRequiredArtifacts($id));}
 public function test_translated_drafts_are_not_listed_as_portuguese_drafts():void{$service=new EditorialService(Database::connect($this->path));$service->createTranslation('page-welcome','en','welcome');self::assertSame([], $service->drafts());self::assertSame(['Referência'],$service->missingRequiredArtifacts('page-welcome','en'));}
}
