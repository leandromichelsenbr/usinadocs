<?php

declare(strict_types=1);

namespace UsinaDocs\Core\Content;

use InvalidArgumentException;
use PDO;

final class ArticleSections
{
    public function __construct(private readonly PDO $db)
    {
    }

    public function all(): array
    {
        return $this->db->query('SELECT * FROM editorial_sections ORDER BY position, title_pt, slug')->fetchAll();
    }

    public function find(string $slug): ?array
    {
        $query = $this->db->prepare('SELECT * FROM editorial_sections WHERE slug = ?');
        $query->execute([$slug]);

        return $query->fetch() ?: null;
    }

    public function save(?string $currentSlug, array $input): string
    {
        // Normalize new identifiers so capitalized names can be pasted without creating case-only duplicates.
        // Normaliza novos identificadores para aceitar nomes com maiúsculas sem criar duplicatas por diferença de caixa.
        $slug = $currentSlug ?? (is_string($input['slug'] ?? null) ? strtolower(trim($input['slug'])) : '');
        if (strlen($slug) > 64 || !preg_match('/\A[a-z0-9]+(?:-[a-z0-9]+)*\z/', $slug)) {
            throw new InvalidArgumentException('invalid_slug');
        }
        $titles = [];
        foreach (['pt', 'en', 'es'] as $locale) {
            $field = 'title_'.$locale;
            $title = is_string($input[$field] ?? null) ? trim($input[$field]) : '';
            if ($title === '' || !mb_check_encoding($title, 'UTF-8') || mb_strlen($title) > 120) {
                throw new InvalidArgumentException('invalid_titles');
            }
            $titles[$field] = $title;
        }
        $blocked = $input['blocked'] ?? '0';
        if (!in_array($blocked, ['0', '1'], true)) throw new InvalidArgumentException('invalid_state');

        // Keep the original identifier and assignments when editing titles or availability.
        // Mantém o identificador original e os vínculos ao alterar títulos ou disponibilidade.
        $statement = $this->db->prepare($currentSlug === null
            ? 'INSERT INTO editorial_sections (slug, title_pt, title_en, title_es, blocked, position)
               SELECT :slug, :title_pt, :title_en, :title_es, :blocked, COALESCE(MAX(position) + 1, 0)
               FROM editorial_sections WHERE true ON CONFLICT(slug) DO NOTHING'
            : 'UPDATE editorial_sections SET title_pt = :title_pt, title_en = :title_en, title_es = :title_es,
               blocked = :blocked WHERE slug = :slug');
        $statement->execute(['slug' => $slug, 'blocked' => $blocked] + $titles);
        if ($currentSlug === null && $statement->rowCount() !== 1) throw new InvalidArgumentException('duplicate_slug');

        return $slug;
    }

    public function sectionFor(string $pageId): ?string
    {
        $query = $this->db->prepare('SELECT section_slug FROM article_section_assignments WHERE page_id = ?');
        $query->execute([$pageId]);

        return $query->fetchColumn() ?: null;
    }

    public function availableFor(?string $pageId = null): array
    {
        // A blocked section may be retained on an existing article but cannot be selected for another.
        // A editoria bloqueada pode ser mantida em um artigo existente, mas não selecionada em outro.
        $current = $pageId === null ? null : $this->sectionFor($pageId);

        return array_values(array_filter($this->all(), static fn (array $section): bool =>
            !$section['blocked'] || $section['slug'] === $current
        ));
    }

    public function canAssign(?string $pageId, string $slug): bool
    {
        if ($slug === '') return true;
        $section = $this->find($slug);

        return $section !== null && (!$section['blocked'] || ($pageId !== null && $this->sectionFor($pageId) === $slug));
    }

    public function assign(string $pageId, string $slug): void
    {
        if ($slug === '') {
            $query = $this->db->prepare('DELETE FROM article_section_assignments WHERE page_id = ?');
            $query->execute([$pageId]);
            return;
        }

        // Recheck availability during the write so a simultaneous block cannot allow a new assignment.
        // Confere a disponibilidade na gravação para que um bloqueio simultâneo não permita um vínculo novo.
        $query = $this->db->prepare(
            "INSERT INTO article_section_assignments (page_id, section_slug)
             SELECT m.page_id, s.slug FROM page_metadata m JOIN editorial_sections s ON s.slug = :slug
             LEFT JOIN article_section_assignments a ON a.page_id = m.page_id
             WHERE m.page_id = :page AND m.content_type = 'article' AND (s.blocked = 0 OR a.section_slug = s.slug)
             ON CONFLICT(page_id) DO UPDATE SET section_slug = excluded.section_slug"
        );
        $query->execute(['page' => $pageId, 'slug' => $slug]);
        if ($query->rowCount() !== 1) throw new InvalidArgumentException('section_unavailable');
    }
}
