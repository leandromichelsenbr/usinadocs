<?php
declare(strict_types=1);
namespace UsinaDocs\Core\Tests;
use PHPUnit\Framework\TestCase;
use Slim\Psr7\Factory\ServerRequestFactory;
use UsinaDocs\Core\AppFactory;
use UsinaDocs\Core\Content\EditorialService;
use UsinaDocs\Core\Infrastructure\Database;
use UsinaDocs\Core\Infrastructure\Schema;
final class QuizArtifactTest extends TestCase {
 private string $path;
 protected function setUp():void{$this->path=sys_get_temp_dir().DIRECTORY_SEPARATOR.'usinadocs-quiz-'.bin2hex(random_bytes(8)).'.sqlite';$db=Database::connect($this->path);Schema::migrate($db,dirname(__DIR__));Schema::seed($db);}
 protected function tearDown():void{if(is_file($this->path))@unlink($this->path);}
 public function test_quiz_renders_options_and_feedback_data():void{$db=Database::connect($this->path);$service=new EditorialService($db);$page=$service->create('Quiz','quiz','','','');$service->replaceDraftBlocks($page,'pt',[['type'=>'quiz','data'=>['question'=>'Qual comando repete um bloco?','options'=>['IF','FOR','RETURN'],'correct'=>1,'explanation'=>'FOR...NEXT executa um bloco repetidamente.']]]);$service->publish($page);$body=AppFactory::create(dirname(__DIR__),$this->path)->handle((new ServerRequestFactory())->createServerRequest('GET','/pt/p/quiz'))->getBody()->__toString();self::assertStringContainsString('class="quiz" data-answer="1"',$body);self::assertStringContainsString('FOR...NEXT executa um bloco repetidamente.',$body);self::assertStringContainsString('Resposta correta.',$body);}
}
