<?php

declare(strict_types=1);

namespace UsinaDocs\Core\Content;

use PDO;

final class ContentPackageService
{
    private const FORMAT='usinadocs-content-package';
    private const VERSION='0.1';
    private const LANGUAGES=['pt'=>'pt-BR','en'=>'en','es'=>'es'];

    public function __construct(private readonly PDO $db) {}

    public function exportPublishedPage(string $pageId):?array
    {
        $pageStatement=$this->db->prepare('SELECT p.id,p.site_id,p.created_at,p.updated_at,s.name AS site_name,s.slug AS site_slug FROM pages p JOIN sites s ON s.id=p.site_id WHERE p.id=:id');
        $pageStatement->execute(['id'=>$pageId]);$page=$pageStatement->fetch(PDO::FETCH_ASSOC);if($page===false)return null;
        $localizations=$this->db->prepare("SELECT pl.language_code,pl.slug,r.id AS revision_id,r.number,r.title,r.summary,r.created_at,r.published_at FROM page_localizations pl JOIN page_revisions r ON r.id=pl.published_revision_id AND r.status='published' WHERE pl.page_id=:page ORDER BY pl.language_code");
        $localizations->execute(['page'=>$pageId]);$published=$localizations->fetchAll(PDO::FETCH_ASSOC);if($published===[])return null;
        $folder=$this->safeFolder($pageId);$files=[];$pageLocalizations=[];
        foreach($published as $revision){$language=self::LANGUAGES[$revision['language_code']]??$revision['language_code'];$pageLocalizations[]=['language'=>$language,'slug'=>$revision['slug'],'published_revision_id'=>$revision['revision_id']];$blocks=$this->db->prepare('SELECT id,type,position,data FROM blocks WHERE page_revision_id=:revision ORDER BY position');$blocks->execute(['revision'=>$revision['revision_id']]);$blockRows=array_map(static fn(array $block):array=>['id'=>$block['id'],'type'=>$block['type'],'position'=>(int)$block['position'],'data'=>json_decode($block['data'],true,512,JSON_THROW_ON_ERROR)],$blocks->fetchAll(PDO::FETCH_ASSOC));$revisionDocument=['id'=>$revision['revision_id'],'page_id'=>$pageId,'language'=>$language,'number'=>(int)$revision['number'],'status'=>'published','title'=>$revision['title'],'summary'=>$revision['summary'],'created_at'=>$revision['created_at'],'published_at'=>$revision['published_at'],'blocks'=>$blockRows,'references'=>[]];$path=sprintf('pages/%s/revisions/%04d.%s.json',$folder,(int)$revision['number'],$language);$files[$path]=$this->json($revisionDocument);}
        $pagePath='pages/'.$folder.'/page.json';$files[$pagePath]=$this->json(['id'=>$pageId,'site_id'=>$page['site_id'],'created_at'=>$page['created_at'],'updated_at'=>$page['updated_at'],'localizations'=>$pageLocalizations]);ksort($files);
        $inventory=[];foreach($files as $path=>$content)$inventory[]=['path'=>$path,'sha256'=>hash('sha256',$content)];
        $manifest=['format'=>self::FORMAT,'format_version'=>self::VERSION,'package_id'=>$this->uuid(),'created_at'=>gmdate('Y-m-d\TH:i:s\Z'),'created_by'=>['name'=>'Usina Docs'],'site'=>['id'=>$page['site_id'],'name'=>$page['site_name'],'slug'=>$page['site_slug']],'default_language'=>'pt-BR','languages'=>array_values(array_unique(array_column($pageLocalizations,'language'))),'contents'=>['pages'=>1,'controlled_documents'=>0,'courses'=>0,'revisions'=>count($published),'media'=>0],'files'=>$inventory];
        $files=['manifest.json'=>$this->json($manifest)]+$files;$checksumLines=[];foreach($files as $path=>$content)$checksumLines[]=hash('sha256',$content).'  '.$path;$files['checksums.sha256']=implode("\n",$checksumLines)."\n";
        return ['filename'=>$folder.'-usinadocs-0.1.zip','content'=>$this->zip($files),'manifest'=>$manifest];
    }

    public function preview(string $zip):array
    {
        $result=['valid'=>false,'errors'=>[],'warnings'=>[],'summary'=>['create'=>0,'update'=>0,'slug_conflicts'=>0,'revisions'=>0,'unknown_blocks'=>0],'pages'=>[]];
        try{$files=$this->unzip($zip);}catch(\Throwable $exception){$result['errors'][]=$exception->getMessage();return$result;}
        if(!isset($files['manifest.json'],$files['checksums.sha256'])){$result['errors'][]='O pacote deve conter manifest.json e checksums.sha256.';return$result;}
        try{$manifest=json_decode($files['manifest.json'],true,512,JSON_THROW_ON_ERROR);}catch(\Throwable){$result['errors'][]='O manifesto não contém JSON válido.';return$result;}
        if(($manifest['format']??null)!==self::FORMAT)$result['errors'][]='Formato de pacote incompatível.';
        if(($manifest['format_version']??null)!==self::VERSION)$result['errors'][]='Versão de pacote não suportada.';
        $listed=[];foreach((array)($manifest['files']??[])as$item){$path=(string)($item['path']??'');if(!$this->safePath($path)){$result['errors'][]='Caminho inseguro no manifesto: '.$path;continue;}if(isset($listed[$path])){$result['errors'][]='Arquivo duplicado no manifesto: '.$path;continue;}$listed[$path]=true;if(!isset($files[$path])){$result['errors'][]='Arquivo ausente: '.$path;continue;}if(!hash_equals((string)($item['sha256']??''),hash('sha256',$files[$path])))$result['errors'][]='Checksum inválido: '.$path;}
        foreach($files as $path=>$content)if(!in_array($path,['manifest.json','checksums.sha256'],true)&&!isset($listed[$path]))$result['errors'][]='Arquivo não listado no manifesto: '.$path;
        $checksumMap=[];foreach(preg_split('/\R/',trim($files['checksums.sha256']))?:[] as $line)if(preg_match('/^([a-f0-9]{64})  (.+)$/',$line,$match))$checksumMap[$match[2]]=$match[1];foreach($files as $path=>$content)if($path!=='checksums.sha256'&&(!isset($checksumMap[$path])||!hash_equals($checksumMap[$path],hash('sha256',$content))))$result['errors'][]='Checksum geral inválido: '.$path;
        foreach(array_keys($files)as$path)if(preg_match('#^pages/[^/]+/page\.json$#',$path)){$this->previewPage($files,$path,$result);}
        $result['valid']=$result['errors']===[];return$result;
    }

    public function import(string $zip):array
    {
        $preview=$this->preview($zip);$blocking=$preview['summary']['update']>0||$preview['summary']['slug_conflicts']>0||$preview['summary']['unknown_blocks']>0;
        if(!$preview['valid']||$blocking)return['imported'=>false,'pages'=>0,'revisions'=>0,'errors'=>array_merge($preview['errors'],$blocking?['A importação inicial aceita somente páginas novas, sem conflitos ou blocos desconhecidos.']:[])];
        $files=$this->unzip($zip);$pages=0;$revisions=0;$this->db->beginTransaction();
        try{foreach(array_keys($files)as$path)if(preg_match('#^pages/[^/]+/page\.json$#',$path)){$page=json_decode($files[$path],true,512,JSON_THROW_ON_ERROR);$this->importPage($files,$path,$page,$revisions);$pages++;}$this->db->commit();return['imported'=>true,'pages'=>$pages,'revisions'=>$revisions,'errors'=>[]];}catch(\Throwable $exception){$this->db->rollBack();return['imported'=>false,'pages'=>0,'revisions'=>0,'errors'=>['A transação foi cancelada: '.$exception->getMessage()]];}
    }

    private function previewPage(array $files,string $path,array &$result):void
    {
        try{$page=json_decode($files[$path],true,512,JSON_THROW_ON_ERROR);}catch(\Throwable){$result['errors'][]='Página com JSON inválido: '.$path;return;}$id=(string)($page['id']??'');if($id===''){$result['errors'][]='Página sem identificador: '.$path;return;}$exists=$this->db->prepare('SELECT 1 FROM pages WHERE id=:id');$exists->execute(['id'=>$id]);$action=$exists->fetchColumn()?'update':'create';$result['summary'][$action]++;
        $slugs=[];foreach((array)($page['localizations']??[])as$localization){$language=$this->databaseLanguage((string)($localization['language']??''));$slug=(string)($localization['slug']??'');$conflict=$this->db->prepare('SELECT page_id FROM page_localizations WHERE language_code=:language AND slug=:slug AND page_id<>:page');$conflict->execute(['language'=>$language,'slug'=>$slug,'page'=>$id]);if($conflict->fetchColumn()){$result['summary']['slug_conflicts']++;$result['warnings'][]='Conflito de slug '.$language.': '.$slug;}$slugs[$language]=$slug;}
        $prefix=dirname($path).'/revisions/';$revisions=0;foreach($files as $revisionPath=>$content)if(str_starts_with($revisionPath,$prefix)&&str_ends_with($revisionPath,'.json')){try{$revision=json_decode($content,true,512,JSON_THROW_ON_ERROR);}catch(\Throwable){$result['errors'][]='Revisão com JSON inválido: '.$revisionPath;continue;}$revisions++;foreach((array)($revision['blocks']??[])as$block)if(!in_array((string)($block['type']??''),['heading','text','code','table','diagram','quiz','image','media','reference','citation','notice','callout','procedure_step','checklist','assessment','reusable_artifact'],true)){$result['summary']['unknown_blocks']++;$result['warnings'][]='Tipo de bloco desconhecido: '.(string)($block['type']??'');}}
        $result['summary']['revisions']+=$revisions;$result['pages'][]=['id'=>$id,'action'=>$action,'localizations'=>$slugs,'revisions'=>$revisions];
    }

    private function importPage(array $files,string $path,array $page,int &$revisionCount):void
    {
        $id=(string)$page['id'];$site=(string)$this->db->query('SELECT id FROM sites ORDER BY id LIMIT 1')->fetchColumn();if($site==='')throw new \RuntimeException('A instalação não possui site de destino.');$created=(string)($page['created_at']??gmdate('c'));$updated=(string)($page['updated_at']??$created);$this->db->prepare('INSERT INTO pages (id,site_id,created_at,updated_at) VALUES (:id,:site,:created,:updated)')->execute(['id'=>$id,'site'=>$site,'created'=>$created,'updated'=>$updated]);$this->db->prepare("INSERT INTO page_metadata (page_id,content_type,updated_at) VALUES (:page,'reference',:updated)")->execute(['page'=>$id,'updated'=>$updated]);
        $localizations=[];foreach((array)$page['localizations']as$localization){$language=$this->databaseLanguage((string)$localization['language']);if(!in_array($language,['pt','en','es'],true))throw new \RuntimeException('Idioma não suportado: '.$language);$localizations[$language]=['slug'=>(string)$localization['slug'],'published_revision_id'=>(string)$localization['published_revision_id']];$this->db->prepare('INSERT INTO page_localizations (page_id,language_code,slug) VALUES (:page,:language,:slug)')->execute(['page'=>$id,'language'=>$language,'slug'=>$localization['slug']]);}
        $prefix=dirname($path).'/revisions/';foreach($files as $revisionPath=>$content)if(str_starts_with($revisionPath,$prefix)&&str_ends_with($revisionPath,'.json')){$revision=json_decode($content,true,512,JSON_THROW_ON_ERROR);if((string)($revision['page_id']??'')!==$id)throw new \RuntimeException('Revisão associada à página incorreta.');$language=$this->databaseLanguage((string)$revision['language']);if(!isset($localizations[$language]))throw new \RuntimeException('Revisão sem localização correspondente.');$revisionId=(string)$revision['id'];$this->db->prepare("INSERT INTO page_revisions (id,page_id,language_code,number,status,title,summary,created_at,published_at) VALUES (:id,:page,:language,:number,'published',:title,:summary,:created,:published)")->execute(['id'=>$revisionId,'page'=>$id,'language'=>$language,'number'=>(int)$revision['number'],'title'=>(string)$revision['title'],'summary'=>(string)($revision['summary']??''),'created'=>(string)$revision['created_at'],'published'=>(string)$revision['published_at']]);$positions=[];foreach((array)($revision['blocks']??[])as$block){$position=(int)($block['position']??0);if($position<1||isset($positions[$position]))throw new \RuntimeException('Posição de bloco inválida ou duplicada.');$positions[$position]=true;$this->db->prepare('INSERT INTO blocks (id,page_revision_id,type,position,data) VALUES (:id,:revision,:type,:position,:data)')->execute(['id'=>(string)$block['id'],'revision'=>$revisionId,'type'=>(string)$block['type'],'position'=>$position,'data'=>json_encode((array)$block['data'],JSON_THROW_ON_ERROR|JSON_UNESCAPED_UNICODE)]);}$revisionCount++;}
        foreach($localizations as $language=>$localization){$exists=$this->db->prepare('SELECT 1 FROM page_revisions WHERE id=:revision AND page_id=:page AND language_code=:language');$exists->execute(['revision'=>$localization['published_revision_id'],'page'=>$id,'language'=>$language]);if(!$exists->fetchColumn())throw new \RuntimeException('Revisão publicada ausente para '.$language.'.');$this->db->prepare('UPDATE page_localizations SET published_revision_id=:revision WHERE page_id=:page AND language_code=:language')->execute(['revision'=>$localization['published_revision_id'],'page'=>$id,'language'=>$language]);}
    }

    private function zip(array $files):string{$body='';$central='';$offset=0;$count=0;foreach($files as $name=>$content){$crc=(int)sprintf('%u',crc32($content));$size=strlen($content);$local=pack('VvvvvvVVVvv',0x04034b50,20,0,0,0,0,$crc,$size,$size,strlen($name),0).$name.$content;$body.=$local;$central.=pack('VvvvvvvVVVvvvvvVV',0x02014b50,20,20,0,0,0,0,$crc,$size,$size,strlen($name),0,0,0,0,0,$offset).$name;$offset+=strlen($local);$count++;}return$body.$central.pack('VvvvvVVv',0x06054b50,0,0,$count,$count,strlen($central),strlen($body),0);}
    private function unzip(string $zip):array{$files=[];$offset=0;$length=strlen($zip);while($offset+4<=$length){$signature=unpack('V',substr($zip,$offset,4))[1];if($signature===0x02014b50||$signature===0x06054b50)break;if($signature!==0x04034b50)throw new \InvalidArgumentException('Arquivo ZIP inválido.');$header=unpack('vversion/vflags/vmethod/vtime/vdate/Vcrc/Vcompressed/Vsize/vname/vextra',substr($zip,$offset+4,26));if($header['flags']!==0||$header['method']!==0)throw new \InvalidArgumentException('O ZIP deve usar arquivos armazenados sem compressão.');$name=substr($zip,$offset+30,$header['name']);if(!$this->safePath($name)||isset($files[$name]))throw new \InvalidArgumentException('Caminho ZIP inseguro ou duplicado.');$start=$offset+30+$header['name']+$header['extra'];$content=substr($zip,$start,$header['compressed']);if(strlen($content)!==$header['size']||(int)sprintf('%u',crc32($content))!==(int)sprintf('%u',$header['crc']))throw new \InvalidArgumentException('Conteúdo ZIP corrompido.');$files[$name]=$content;$offset=$start+$header['compressed'];}return$files;}
    private function safePath(string $path):bool{return$path!==''&&!str_starts_with($path,'/')&&!str_starts_with($path,'\\')&&!preg_match('/^[A-Za-z]:/',$path)&&!in_array('..',explode('/',str_replace('\\','/',$path)),true);}
    private function safeFolder(string $id):string{$folder=preg_replace('/[^a-zA-Z0-9_-]+/','-',$id)??'page';return trim($folder,'-')?:'page';}
    private function databaseLanguage(string $language):string{return$language==='pt-BR'?'pt':$language;}
    private function json(array $value):string{return json_encode($value,JSON_THROW_ON_ERROR|JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES)."\n";}
    private function uuid():string{$bytes=random_bytes(16);$bytes[6]=chr((ord($bytes[6])&0x0f)|0x40);$bytes[8]=chr((ord($bytes[8])&0x3f)|0x80);$hex=bin2hex($bytes);return substr($hex,0,8).'-'.substr($hex,8,4).'-'.substr($hex,12,4).'-'.substr($hex,16,4).'-'.substr($hex,20);}
}
