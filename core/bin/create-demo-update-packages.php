<?php

declare(strict_types=1);

use UsinaDocs\Core\Content\ContentPackageService;
use UsinaDocs\Core\Content\EditorialService;
use UsinaDocs\Core\Infrastructure\Database;
use UsinaDocs\Core\Infrastructure\Schema;

require dirname(__DIR__).'/vendor/autoload.php';

$root=dirname(__DIR__);
$databasePath=sys_get_temp_dir().'/usinadocs-update-demo-'.bin2hex(random_bytes(6)).'.sqlite';
$directory=$argv[1]??$root.'/storage';

try {
    $database=Database::connect($databasePath);
    Schema::migrate($database,$root);
    Schema::seed($database);
    $editorial=new EditorialService($database);
    $pageId=$editorial->create('Demonstração de atualização','demonstracao-atualizacao','Primeira versão do pacote.','','','','article');
    $editorial->replaceDraftBlocks($pageId,'pt',[
        ['type'=>'text','data'=>['body'=>'Esta é a primeira versão publicada da página.']],
    ]);
    $editorial->publish($pageId);

    $packages=new ContentPackageService($database);
    $initial=$packages->exportPublishedPage($pageId);
    if($initial===null)throw new RuntimeException('Não foi possível gerar o pacote inicial.');

    $editorial->createRevision($pageId);
    $editorial->updateDraft($pageId,'Demonstração de atualização — versão 2','Primeira versão do pacote.','Segunda versão importada como revisão.','');
    $editorial->replaceDraftBlocks($pageId,'pt',[
        ['type'=>'text','data'=>['body'=>'Esta é a segunda versão. A primeira continua preservada no histórico.']],
        ['type'=>'code','data'=>['language'=>'advpl','code'=>"User Function AtualizacaoDemo()\nReturn"]],
    ]);
    $editorial->publish($pageId);
    $updated=$packages->exportPublishedPage($pageId);
    if($updated===null)throw new RuntimeException('Não foi possível gerar o pacote de atualização.');

    if(!is_dir($directory)&&!mkdir($directory,0775,true)&&!is_dir($directory))throw new RuntimeException('Não foi possível criar a pasta de saída.');
    $initialPath=rtrim($directory,'/\\').'/usinadocs-update-demo-v1.zip';
    $updatedPath=rtrim($directory,'/\\').'/usinadocs-update-demo-v2.zip';
    if(file_put_contents($initialPath,$initial['content'])===false||file_put_contents($updatedPath,$updated['content'])===false)throw new RuntimeException('Não foi possível gravar os pacotes.');
    fwrite(STDOUT,$initialPath.PHP_EOL.$updatedPath.PHP_EOL);
} finally {
    @unlink($databasePath);
}
