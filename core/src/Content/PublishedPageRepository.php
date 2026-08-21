<?php

declare(strict_types=1);

namespace UsinaDocs\Core\Content;

use JsonException;
use PDO;

final class PublishedPageRepository
{
    public function __construct(private readonly PDO $database)
    {
    }

    /** @return array<string, mixed>|null */
    public function findByLocalizedSlug(string $locale, string $slug): ?array
    {
        $pageStatement = $this->database->prepare(
            'SELECT p.id AS page_id, s.name AS site_name, l.code AS language_code, l.native_name, '
            .'pl.slug, COALESCE(m.content_type, \'reference\') AS content_type, r.id AS revision_id, r.number AS revision_number, r.title, r.summary, r.published_at '
            .'FROM page_localizations pl '
            .'JOIN pages p ON p.id = pl.page_id '
            .'LEFT JOIN page_metadata m ON m.page_id = p.id '
            .'JOIN sites s ON s.id = p.site_id '
            .'JOIN languages l ON l.code = pl.language_code '
            .'JOIN page_revisions r ON r.id = pl.published_revision_id '
            .'WHERE pl.language_code = :locale AND pl.slug = :slug AND r.status = :status'
        );
        $pageStatement->execute(['locale' => $locale, 'slug' => $slug, 'status' => 'published']);
        $page = $pageStatement->fetch(PDO::FETCH_ASSOC);

        if ($page === false) {
            return null;
        }

        $blocksStatement = $this->database->prepare(
            'SELECT id, type, position, data FROM blocks WHERE page_revision_id = :revision ORDER BY position ASC'
        );
        $blocksStatement->execute(['revision' => $page['revision_id']]);

        try {
            $page['blocks'] = array_map(
                function (array $block) use ($page): array {
                    $data = json_decode($block['data'], true, 512, JSON_THROW_ON_ERROR);
                    if ($block['type'] === 'reusable_artifact') {
                        $artifact = $this->publishedArtifact((string) ($data['artifact_id'] ?? ''), (string) ($data['artifact_revision_id'] ?? ''), (string)$page['language_code']);
                        if ($artifact === null) return ['id'=>$block['id'],'type'=>'missing_artifact','position'=>(int)$block['position'],'data'=>['title'=>'Artefato indisponível']];
                        $block['type']=$artifact['type'];$data=$artifact['data'];$data['_artifact']=$artifact['meta'];
                    }
                    if ($block['type'] === 'table') {
                        $data['headers'] = is_array($data['headers'] ?? null) ? $data['headers'] : array_values(array_filter(array_map('trim', explode('|', (string) ($data['headers'] ?? $data['headers_text'] ?? '')))));
                        $data['rows'] = is_array($data['rows'] ?? null) ? $data['rows'] : array_map(static fn (string $row): array => array_map('trim', explode('|', $row)), array_filter(preg_split('/\R/', (string) ($data['rows'] ?? $data['rows_text'] ?? '')) ?: []));
                    }
                    if($block['type']==='diagram'){$data['nodes']=is_array($data['nodes']??null)?$data['nodes']:array_values(array_filter(preg_split('/\R/',(string)($data['nodes_text']??''))?:[]));$data['edges']=is_array($data['edges']??null)?$data['edges']:array_values(array_filter(preg_split('/\R/',(string)($data['edges_text']??''))?:[]));}
                    if($block['type']==='quiz'&&!isset($data['options'])&&isset($data['options_text']))$data['options']=array_values(array_filter(preg_split('/\R/',(string)$data['options_text'])?:[]));
                    return ['id'=>$block['id'],'type'=>$block['type'],'position'=>(int)$block['position'],'data'=>$data];
                },
                $blocksStatement->fetchAll(PDO::FETCH_ASSOC)
            );
        } catch (JsonException $exception) {
            throw new \RuntimeException('A published block contains invalid JSON.', 0, $exception);
        }

        $translationStatement = $this->database->prepare(
            'SELECT pl.language_code, l.native_name, pl.slug '
            .'FROM page_localizations pl '
            .'JOIN languages l ON l.code = pl.language_code '
            .'JOIN page_revisions r ON r.id = pl.published_revision_id '
            .'WHERE pl.page_id = :page AND r.status = :status ORDER BY pl.language_code'
        );
        $translationStatement->execute(['page' => $page['page_id'], 'status' => 'published']);
        $page['translations'] = $translationStatement->fetchAll(PDO::FETCH_ASSOC);

        return $page;
    }

    private function publishedArtifact(string $id, string $revisionId='',string $language='pt'): ?array
    {
        if($revisionId!==''){$statement=$this->database->prepare("SELECT a.id,a.slug,a.artifact_type,r.number,r.title,r.data FROM reusable_artifacts a JOIN reusable_artifact_revisions r ON r.artifact_id=a.id WHERE a.id=:id AND r.id=:revision AND r.status='published'");$statement->execute(['id'=>$id,'revision'=>$revisionId]);$artifact=$statement->fetch(PDO::FETCH_ASSOC);if($artifact===false){$statement=$this->database->prepare("SELECT a.id,a.slug,a.artifact_type,r.number,r.title,r.data FROM reusable_artifacts a JOIN reusable_artifact_translation_revisions r ON r.artifact_id=a.id WHERE a.id=:id AND r.id=:revision AND r.status='published'");$statement->execute(['id'=>$id,'revision'=>$revisionId]);$artifact=$statement->fetch(PDO::FETCH_ASSOC);}}else{$artifact=false;if($language!=='pt'){$statement=$this->database->prepare("SELECT a.id,a.slug,a.artifact_type,r.number,r.title,r.data FROM reusable_artifacts a JOIN reusable_artifact_localizations l ON l.artifact_id=a.id AND l.language_code=:language JOIN reusable_artifact_translation_revisions r ON r.id=l.published_revision_id WHERE a.id=:id AND r.status='published'");$statement->execute(['id'=>$id,'language'=>$language]);$artifact=$statement->fetch(PDO::FETCH_ASSOC);}if($artifact===false){$statement=$this->database->prepare("SELECT a.id,a.slug,a.artifact_type,r.number,r.title,r.data FROM reusable_artifacts a JOIN reusable_artifact_revisions r ON r.id=a.published_revision_id WHERE a.id=:id AND r.status='published'");$statement->execute(['id'=>$id]);$artifact=$statement->fetch(PDO::FETCH_ASSOC);}}if($artifact===false)return null;
        return ['type'=>$artifact['artifact_type'],'data'=>json_decode($artifact['data'],true,512,JSON_THROW_ON_ERROR),'meta'=>['id'=>$artifact['id'],'slug'=>$artifact['slug'],'title'=>$artifact['title'],'revision'=>(int)$artifact['number']]];
    }
}
