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
                static function (array $block): array {
                    $data = json_decode($block['data'], true, 512, JSON_THROW_ON_ERROR);
                    if ($block['type'] === 'table') {
                        $data['headers'] = is_array($data['headers'] ?? null) ? $data['headers'] : array_values(array_filter(array_map('trim', explode('|', (string) ($data['headers'] ?? '')))));
                        $data['rows'] = is_array($data['rows'] ?? null) ? $data['rows'] : array_map(static fn (string $row): array => array_map('trim', explode('|', $row)), array_filter(preg_split('/\R/', (string) ($data['rows'] ?? '')) ?: []));
                    }
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
}
