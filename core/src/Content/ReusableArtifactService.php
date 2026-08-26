<?php

declare(strict_types=1);

namespace UsinaDocs\Core\Content;

use PDO;

final class ReusableArtifactService
{
    private const TYPES = ['heading','text','code','table','diagram','quiz','image','reference','citation','notice'];

    public function __construct(private readonly PDO $db) {}

    public function catalog(): array
    {
        return $this->db->query("SELECT a.id,a.slug,a.artifact_type,COALESCE(d.title,p.title) AS title,CASE WHEN d.id IS NOT NULL THEN 'draft' ELSE 'published' END AS status,CASE WHEN endraft.id IS NOT NULL THEN 'draft' WHEN en.published_revision_id IS NOT NULL THEN 'published' ELSE 'absent' END AS en_status,CASE WHEN esdraft.id IS NOT NULL THEN 'draft' WHEN es.published_revision_id IS NOT NULL THEN 'published' ELSE 'absent' END AS es_status FROM reusable_artifacts a LEFT JOIN reusable_artifact_revisions d ON d.artifact_id=a.id AND d.status='draft' LEFT JOIN reusable_artifact_revisions p ON p.id=a.published_revision_id LEFT JOIN reusable_artifact_localizations en ON en.artifact_id=a.id AND en.language_code='en' LEFT JOIN reusable_artifact_translation_revisions endraft ON endraft.artifact_id=a.id AND endraft.language_code='en' AND endraft.status='draft' LEFT JOIN reusable_artifact_localizations es ON es.artifact_id=a.id AND es.language_code='es' LEFT JOIN reusable_artifact_translation_revisions esdraft ON esdraft.artifact_id=a.id AND esdraft.language_code='es' AND esdraft.status='draft' ORDER BY COALESCE(d.title,p.title)")->fetchAll(PDO::FETCH_ASSOC);
    }

    public function publishedCatalog(string $language='pt'): array
    {
        if($language!=='pt'){$statement=$this->db->prepare("SELECT a.id,a.slug,a.artifact_type,r.id AS revision_id,r.title,r.description,r.number AS revision_number FROM reusable_artifacts a JOIN reusable_artifact_localizations l ON l.artifact_id=a.id AND l.language_code=:language JOIN reusable_artifact_translation_revisions r ON r.id=l.published_revision_id WHERE r.status='published' ORDER BY r.title");$statement->execute(['language'=>$language]);return$statement->fetchAll(PDO::FETCH_ASSOC);}
        return $this->db->query("SELECT a.id,a.slug,a.artifact_type,r.id AS revision_id,r.title,r.description,r.number AS revision_number FROM reusable_artifacts a JOIN reusable_artifact_revisions r ON r.id=a.published_revision_id WHERE r.status='published' ORDER BY r.title")->fetchAll(PDO::FETCH_ASSOC);
    }

    public function createTranslation(string $artifactId,string $language):?string
    {
        if(!in_array($language,['en','es'],true))return null;$source=$this->db->prepare('SELECT r.* FROM reusable_artifacts a JOIN reusable_artifact_revisions r ON r.id=a.published_revision_id WHERE a.id=:id');$source->execute(['id'=>$artifactId]);$base=$source->fetch(PDO::FETCH_ASSOC);if($base===false)return null;$exists=$this->db->prepare('SELECT 1 FROM reusable_artifact_localizations WHERE artifact_id=:id AND language_code=:language');$exists->execute(['id'=>$artifactId,'language'=>$language]);if($exists->fetchColumn())return null;$revision='artifact-translation-revision-'.bin2hex(random_bytes(8));$now=gmdate('c');$this->db->beginTransaction();try{$this->db->prepare('INSERT INTO reusable_artifact_localizations (artifact_id,language_code) VALUES (:id,:language)')->execute(['id'=>$artifactId,'language'=>$language]);$this->db->prepare("INSERT INTO reusable_artifact_translation_revisions (id,artifact_id,language_code,number,status,source_revision_id,title,description,data,created_at) VALUES (:revision,:id,:language,1,'draft',:source,:title,:description,:data,:created)")->execute(['revision'=>$revision,'id'=>$artifactId,'language'=>$language,'source'=>$base['id'],'title'=>$base['title'],'description'=>$base['description'],'data'=>$base['data'],'created'=>$now]);$this->db->commit();return$revision;}catch(\Throwable$exception){$this->db->rollBack();throw$exception;}
    }

    public function translationDraft(string $artifactId,string $language):?array{$statement=$this->db->prepare("SELECT a.id,a.slug,a.artifact_type,r.id AS revision_id,r.number,r.title,r.description,r.data,r.source_revision_id FROM reusable_artifacts a JOIN reusable_artifact_translation_revisions r ON r.artifact_id=a.id AND r.language_code=:language AND r.status='draft' WHERE a.id=:id ORDER BY r.number DESC LIMIT 1");$statement->execute(['id'=>$artifactId,'language'=>$language]);$artifact=$statement->fetch(PDO::FETCH_ASSOC);if($artifact===false)return null;$artifact['language']=$language;$artifact['data']=json_decode($artifact['data'],true,512,JSON_THROW_ON_ERROR);return$artifact;}
    public function updateTranslationDraft(string $artifactId,string $language,string $title,string $description,array $data):void{$draft=$this->translationDraft($artifactId,$language);if($draft===null)return;$this->db->prepare('UPDATE reusable_artifact_translation_revisions SET title=:title,description=:description,data=:data WHERE id=:id')->execute(['title'=>$title,'description'=>$description,'data'=>$this->encode($data),'id'=>$draft['revision_id']]);}
    public function publishTranslation(string $artifactId,string $language):void{$draft=$this->translationDraft($artifactId,$language);if($draft===null)return;$now=gmdate('c');$this->db->beginTransaction();try{$this->db->prepare("UPDATE reusable_artifact_translation_revisions SET status='published',published_at=:published WHERE id=:id")->execute(['published'=>$now,'id'=>$draft['revision_id']]);$this->db->prepare('UPDATE reusable_artifact_localizations SET published_revision_id=:revision WHERE artifact_id=:id AND language_code=:language')->execute(['revision'=>$draft['revision_id'],'id'=>$artifactId,'language'=>$language]);$this->db->commit();}catch(\Throwable$exception){$this->db->rollBack();throw$exception;}}
    public function createTranslationRevision(string $artifactId,string $language):?string{$existing=$this->translationDraft($artifactId,$language);if($existing!==null)return$existing['revision_id'];$statement=$this->db->prepare('SELECT r.* FROM reusable_artifact_localizations l JOIN reusable_artifact_translation_revisions r ON r.id=l.published_revision_id WHERE l.artifact_id=:id AND l.language_code=:language');$statement->execute(['id'=>$artifactId,'language'=>$language]);$source=$statement->fetch(PDO::FETCH_ASSOC);if($source===false)return null;$revision='artifact-translation-revision-'.bin2hex(random_bytes(8));$this->db->prepare("INSERT INTO reusable_artifact_translation_revisions (id,artifact_id,language_code,number,status,source_revision_id,title,description,data,created_at) VALUES (:revision,:id,:language,:number,'draft',:source,:title,:description,:data,:created)")->execute(['revision'=>$revision,'id'=>$artifactId,'language'=>$language,'number'=>(int)$source['number']+1,'source'=>$source['source_revision_id'],'title'=>$source['title'],'description'=>$source['description'],'data'=>$source['data'],'created'=>gmdate('c')]);return$revision;}

    public function usages(string $artifactId): array
    {
        $statement=$this->db->prepare("SELECT p.id AS page_id,r.language_code,r.title AS page_title,r.number AS page_revision,r.status AS page_status,pl.slug,json_extract(b.data,'$.artifact_revision_id') AS pinned_revision_id,pinned.number AS pinned_revision_number,current.number AS current_revision_number,CASE WHEN json_extract(b.data,'$.artifact_revision_id') IS NULL AND r.status='published' THEN 'legacy_unpinned' WHEN json_extract(b.data,'$.artifact_revision_id') IS NULL THEN 'unpinned' WHEN json_extract(b.data,'$.artifact_revision_id')=a.published_revision_id THEN 'current' ELSE 'outdated' END AS artifact_status FROM blocks b JOIN page_revisions r ON r.id=b.page_revision_id JOIN pages p ON p.id=r.page_id JOIN page_localizations pl ON pl.page_id=p.id AND pl.language_code=r.language_code JOIN reusable_artifacts a ON a.id=json_extract(b.data,'$.artifact_id') LEFT JOIN reusable_artifact_revisions pinned ON pinned.id=json_extract(b.data,'$.artifact_revision_id') LEFT JOIN reusable_artifact_revisions current ON current.id=a.published_revision_id WHERE b.type='reusable_artifact' AND a.id=:artifact AND (r.status='draft' OR pl.published_revision_id=r.id) ORDER BY r.title,r.language_code,r.number DESC");
        $statement->execute(['artifact'=>$artifactId]);return$statement->fetchAll(PDO::FETCH_ASSOC);
    }

    public function create(string $slug, string $type, string $title, string $description, array $data): string
    {
        $this->assertType($type);
        if (!preg_match('/^[a-z0-9]+(?:-[a-z0-9]+)*$/', $slug)) throw new \InvalidArgumentException('Invalid slug.');
        $id='artifact-'.bin2hex(random_bytes(8));$revision='artifact-revision-'.bin2hex(random_bytes(8));$now=gmdate('c');
        $this->db->beginTransaction();
        try {
            $this->db->prepare("INSERT INTO reusable_artifacts (id,site_id,slug,artifact_type,created_at,updated_at) VALUES (:id,'site-usinadocs',:slug,:type,:created,:updated)")->execute(['id'=>$id,'slug'=>$slug,'type'=>$type,'created'=>$now,'updated'=>$now]);
            $this->db->prepare("INSERT INTO reusable_artifact_revisions (id,artifact_id,number,status,title,description,data,created_at) VALUES (:id,:artifact,1,'draft',:title,:description,:data,:created)")->execute(['id'=>$revision,'artifact'=>$id,'title'=>$title,'description'=>$description,'data'=>$this->encode($data),'created'=>$now]);
            $this->db->commit();return$id;
        } catch (\Throwable $exception) {$this->db->rollBack();throw$exception;}
    }

    public function draft(string $id): ?array
    {
        $statement=$this->db->prepare("SELECT a.id,a.slug,a.artifact_type,r.id AS revision_id,r.number,r.title,r.description,r.data FROM reusable_artifacts a JOIN reusable_artifact_revisions r ON r.artifact_id=a.id AND r.status='draft' WHERE a.id=:id ORDER BY r.number DESC LIMIT 1");$statement->execute(['id'=>$id]);$artifact=$statement->fetch(PDO::FETCH_ASSOC);if($artifact===false)return null;$artifact['data']=json_decode($artifact['data'],true,512,JSON_THROW_ON_ERROR);return$artifact;
    }

    public function updateDraft(string $id, string $title, string $description, array $data): void
    {
        $draft=$this->draft($id);if($draft===null)return;$this->db->prepare('UPDATE reusable_artifact_revisions SET title=:title,description=:description,data=:data WHERE id=:id')->execute(['title'=>$title,'description'=>$description,'data'=>$this->encode($data),'id'=>$draft['revision_id']]);
    }

    public function publish(string $id): void
    {
        $draft=$this->draft($id);if($draft===null)return;$now=gmdate('c');$this->db->beginTransaction();try{$this->db->prepare("UPDATE reusable_artifact_revisions SET status='published',published_at=:published WHERE id=:id")->execute(['published'=>$now,'id'=>$draft['revision_id']]);$this->db->prepare('UPDATE reusable_artifacts SET published_revision_id=:revision,updated_at=:updated WHERE id=:id')->execute(['revision'=>$draft['revision_id'],'updated'=>$now,'id'=>$id]);$this->db->commit();}catch(\Throwable$exception){$this->db->rollBack();throw$exception;}
    }

    public function createRevision(string $id): ?string
    {
        $existing=$this->draft($id);if($existing!==null)return$existing['revision_id'];$statement=$this->db->prepare('SELECT r.* FROM reusable_artifacts a JOIN reusable_artifact_revisions r ON r.id=a.published_revision_id WHERE a.id=:id');$statement->execute(['id'=>$id]);$source=$statement->fetch(PDO::FETCH_ASSOC);if($source===false)return null;$revision='artifact-revision-'.bin2hex(random_bytes(8));$this->db->prepare("INSERT INTO reusable_artifact_revisions (id,artifact_id,number,status,title,description,data,created_at) VALUES (:id,:artifact,:number,'draft',:title,:description,:data,:created)")->execute(['id'=>$revision,'artifact'=>$id,'number'=>(int)$source['number']+1,'title'=>$source['title'],'description'=>$source['description'],'data'=>$source['data'],'created'=>gmdate('c')]);return$revision;
    }

    public function history(string $id,string $language='pt'):array
    {
        if($language==='pt'){$statement=$this->db->prepare("SELECT r.id,r.number,r.status,r.title,r.description,r.data,r.created_at,r.published_at,CASE WHEN a.published_revision_id=r.id THEN 1 ELSE 0 END AS is_current FROM reusable_artifact_revisions r JOIN reusable_artifacts a ON a.id=r.artifact_id WHERE r.artifact_id=:id ORDER BY r.number DESC");$statement->execute(['id'=>$id]);}
        else{$statement=$this->db->prepare("SELECT r.id,r.number,r.status,r.title,r.description,r.data,r.created_at,r.published_at,CASE WHEN l.published_revision_id=r.id THEN 1 ELSE 0 END AS is_current FROM reusable_artifact_translation_revisions r JOIN reusable_artifact_localizations l ON l.artifact_id=r.artifact_id AND l.language_code=r.language_code WHERE r.artifact_id=:id AND r.language_code=:language ORDER BY r.number DESC");$statement->execute(['id'=>$id,'language'=>$language]);}
        return array_map(static function(array $row):array{$row['number']=(int)$row['number'];$row['is_current']=(bool)$row['is_current'];$row['data']=json_decode($row['data'],true,512,JSON_THROW_ON_ERROR);return$row;},$statement->fetchAll(PDO::FETCH_ASSOC));
    }

    public function compareRevisions(string $id,string $language,string $fromId,string $toId):?array{$byId=[];foreach($this->history($id,$language)as$revision)$byId[$revision['id']]=$revision;if(!isset($byId[$fromId],$byId[$toId]))return null;return['from'=>$byId[$fromId],'to'=>$byId[$toId]];}
    public function restoreRevision(string $id,string $language,string $revisionId):?string{$history=$this->history($id,$language);$source=null;$number=0;foreach($history as $revision){$number=max($number,$revision['number']);if($revision['id']===$revisionId)$source=$revision;}if($source===null)return null;if($language==='pt'){if($this->draft($id)!==null)return null;$new='artifact-revision-'.bin2hex(random_bytes(8));$this->db->prepare("INSERT INTO reusable_artifact_revisions (id,artifact_id,number,status,title,description,data,created_at) VALUES (:revision,:id,:number,'draft',:title,:description,:data,:created)")->execute(['revision'=>$new,'id'=>$id,'number'=>$number+1,'title'=>$source['title'],'description'=>$source['description'],'data'=>$this->encode($source['data']),'created'=>gmdate('c')]);return$new;}if(!in_array($language,['en','es'],true)||$this->translationDraft($id,$language)!==null)return null;$new='artifact-translation-revision-'.bin2hex(random_bytes(8));$base=$this->db->prepare('SELECT published_revision_id FROM reusable_artifacts WHERE id=:id');$base->execute(['id'=>$id]);$this->db->prepare("INSERT INTO reusable_artifact_translation_revisions (id,artifact_id,language_code,number,status,source_revision_id,title,description,data,created_at) VALUES (:revision,:id,:language,:number,'draft',:source,:title,:description,:data,:created)")->execute(['revision'=>$new,'id'=>$id,'language'=>$language,'number'=>$number+1,'source'=>$base->fetchColumn(),'title'=>$source['title'],'description'=>$source['description'],'data'=>$this->encode($source['data']),'created'=>gmdate('c')]);return$new;}

    private function assertType(string $type): void {if(!in_array($type,self::TYPES,true))throw new \InvalidArgumentException('Invalid artifact type.');}
    private function encode(array $data): string {return json_encode($data,JSON_THROW_ON_ERROR|JSON_UNESCAPED_UNICODE);}
}
