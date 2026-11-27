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
            // Hide every public edition while leaving the separate editorial preview available.
            // Oculta todas as edições públicas e mantém a prévia editorial em sua rota separada.
            .'WHERE pl.language_code = :locale AND pl.slug = :slug AND r.status = :status'
            .' AND NOT EXISTS (SELECT 1 FROM hidden_articles h WHERE h.page_id = p.id)'
        );
        $pageStatement->execute(['locale' => $locale, 'slug' => $slug, 'status' => 'published']);
        $page = $pageStatement->fetch(PDO::FETCH_ASSOC);

        if ($page === false) {
            return null;
        }

        return $this->withBlocks($page);
    }

    public function findForPreview(string $pageId, string $locale): ?array
    {
        // Keep draft resolution on a separate path used only by authenticated editorial routes.
        // Mantém a resolução de rascunhos em um caminho separado, usado apenas por rotas editoriais autenticadas.
        $query = $this->database->prepare(
            "SELECT p.id AS page_id, s.name AS site_name, l.code AS language_code, l.native_name, pl.slug,
                    COALESCE(m.content_type, 'reference') AS content_type, r.id AS revision_id,
                    r.number AS revision_number, r.title, r.summary, r.published_at, r.status
             FROM page_localizations pl JOIN pages p ON p.id = pl.page_id
             JOIN sites s ON s.id = p.site_id JOIN languages l ON l.code = pl.language_code
             LEFT JOIN page_metadata m ON m.page_id = p.id
             JOIN page_revisions r ON r.id = COALESCE(
                 (SELECT d.id FROM page_revisions d WHERE d.page_id = p.id AND d.language_code = pl.language_code AND d.status = 'draft'
                  ORDER BY d.number DESC LIMIT 1), pl.published_revision_id)
             WHERE p.id = :id AND pl.language_code = :locale"
        );
        $query->execute(['id' => $pageId, 'locale' => $locale]);
        $page = $query->fetch();

        return $page === false ? null : $this->withBlocks($page);
    }

    private function withBlocks(array $page): array
    {
        if ($page['content_type'] === 'article') {
            // Each edition keeps its first publication date as later revisions are released.
            // Cada edição mantém a data da primeira publicação quando saem revisões posteriores.
            $dates = $this->database->prepare(
                "SELECT published_at FROM page_revisions WHERE page_id=:page AND language_code=:locale
                 AND status='published' AND published_at IS NOT NULL ORDER BY julianday(published_at), number LIMIT 1"
            );
            $dates->execute(['page'=>$page['page_id'], 'locale'=>$page['language_code']]);
            $page['first_published_at'] = $dates->fetchColumn() ?: null;
            $page['authors'] = (new AuthorDirectory($this->database))->byline($page['page_id']);
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
                    if ($block['type'] === 'youtube') {
                        // Normalize at the public boundary too, since imported packages may bypass the page editor.
                        // Normaliza também na saída pública, pois pacotes importados podem passar fora do editor.
                        $data['video_id'] = YouTubeVideo::id($data['url'] ?? null);
                    }
                    if ($block['type'] === 'reference') {
                        $data = self::referenceData($data);
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

    private static function referenceData(array $data): array
    {
        // Old and imported blocks may lack metadata. Prepare display values without rewriting stored revisions.
        // Blocos antigos e importados podem não ter metadados. Prepara os dados de exibição sem regravar revisões.
        foreach (['title', 'url', 'author', 'publisher', 'published_on', 'accessed_on'] as $key) {
            $data[$key] = is_string($data[$key] ?? null) ? trim($data[$key]) : '';
        }

        // Only web URLs and single-slash local paths can become links in a bibliography.
        // Somente URLs web e caminhos locais com uma barra podem virar links na bibliografia.
        $url = $data['url'];
        $webUrl = preg_match('~\Ahttps?://~i', $url) && filter_var($url, FILTER_VALIDATE_URL);
        $localUrl = str_starts_with($url, '/') && !str_starts_with($url, '//')
            && !str_contains($url, chr(92)) && !preg_match('/[[:cntrl:]]/', $url);
        if (!$webUrl && !$localUrl) {
            $data['url'] = '';
        }

        // An absent or impossible date is omitted rather than interpreted as today's date.
        // Uma data ausente ou impossível é omitida para que não seja interpretada como a data atual.
        foreach (['published_on', 'accessed_on'] as $key) {
            if (!preg_match('/\A([0-9]{4})-([0-9]{2})-([0-9]{2})\z/', $data[$key], $parts)
                || !checkdate((int) $parts[2], (int) $parts[3], (int) $parts[1])) {
                $data[$key] = '';
            }
        }

        return $data;
    }

    private function publishedArtifact(string $id, string $revisionId='',string $language='pt'): ?array
    {
        if($revisionId!==''){$statement=$this->database->prepare("SELECT a.id,a.slug,a.artifact_type,r.number,r.title,r.data FROM reusable_artifacts a JOIN reusable_artifact_revisions r ON r.artifact_id=a.id WHERE a.id=:id AND r.id=:revision AND r.status='published'");$statement->execute(['id'=>$id,'revision'=>$revisionId]);$artifact=$statement->fetch(PDO::FETCH_ASSOC);if($artifact===false){$statement=$this->database->prepare("SELECT a.id,a.slug,a.artifact_type,r.number,r.title,r.data FROM reusable_artifacts a JOIN reusable_artifact_translation_revisions r ON r.artifact_id=a.id WHERE a.id=:id AND r.id=:revision AND r.status='published'");$statement->execute(['id'=>$id,'revision'=>$revisionId]);$artifact=$statement->fetch(PDO::FETCH_ASSOC);}}else{$artifact=false;if($language!=='pt'){$statement=$this->database->prepare("SELECT a.id,a.slug,a.artifact_type,r.number,r.title,r.data FROM reusable_artifacts a JOIN reusable_artifact_localizations l ON l.artifact_id=a.id AND l.language_code=:language JOIN reusable_artifact_translation_revisions r ON r.id=l.published_revision_id WHERE a.id=:id AND r.status='published'");$statement->execute(['id'=>$id,'language'=>$language]);$artifact=$statement->fetch(PDO::FETCH_ASSOC);}if($artifact===false){$statement=$this->database->prepare("SELECT a.id,a.slug,a.artifact_type,r.number,r.title,r.data FROM reusable_artifacts a JOIN reusable_artifact_revisions r ON r.id=a.published_revision_id WHERE a.id=:id AND r.status='published'");$statement->execute(['id'=>$id]);$artifact=$statement->fetch(PDO::FETCH_ASSOC);}}if($artifact===false)return null;
        return ['type'=>$artifact['artifact_type'],'data'=>json_decode($artifact['data'],true,512,JSON_THROW_ON_ERROR),'meta'=>['id'=>$artifact['id'],'slug'=>$artifact['slug'],'title'=>$artifact['title'],'revision'=>(int)$artifact['number']]];
    }
}
