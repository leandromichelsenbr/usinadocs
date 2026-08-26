<?php

declare(strict_types=1);

use UsinaDocs\Core\Content\ContentPackageService;
use UsinaDocs\Core\Content\EditorialService;
use UsinaDocs\Core\Infrastructure\Database;
use UsinaDocs\Core\Infrastructure\Schema;

require dirname(__DIR__).'/vendor/autoload.php';

$root=dirname(__DIR__);
$databasePath=sys_get_temp_dir().'/usinadocs-demo-'.bin2hex(random_bytes(6)).'.sqlite';
$output=$argv[1]??$root.'/storage/usinadocs-import-demo.zip';

try {
    $database=Database::connect($databasePath);
    Schema::migrate($database,$root);
    Schema::seed($database);
    $editorial=new EditorialService($database);
    $pageId=$editorial->create('Página importada de demonstração','pagina-importada-demo','Pacote para validar a importação.','','','','article');
    $editorial->replaceDraftBlocks($pageId,'pt',[
        ['type'=>'text','data'=>['body'=>'Conteúdo criado pelo pacote de demonstração.']],
        ['type'=>'code','data'=>['language'=>'advpl','code'=>"User Function Demo()\nReturn"]],
    ]);
    $editorial->publish($pageId);
    $package=(new ContentPackageService($database))->exportPublishedPage($pageId);
    if($package===null)throw new RuntimeException('Não foi possível gerar o pacote.');
    $directory=dirname($output);if(!is_dir($directory))mkdir($directory,0775,true);
    if(file_put_contents($output,$package['content'])===false)throw new RuntimeException('Não foi possível gravar o pacote.');
    fwrite(STDOUT,$output.PHP_EOL);
} finally {
    @unlink($databasePath);
}
