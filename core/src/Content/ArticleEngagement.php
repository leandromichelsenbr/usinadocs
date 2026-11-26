<?php

declare(strict_types=1);

namespace UsinaDocs\Core\Content;

use PDO;

final class ArticleEngagement
{
    public const COMMENT_STATUSES = ['pending', 'approved', 'rejected'];

    public function __construct(private readonly PDO $db)
    {
    }

    public function incrementViews(string $pageId, string $locale): void
    {
        // An atomic upsert counts concurrent reads of the same edition without storing visitor identifiers.
        // Um upsert atômico conta leituras simultâneas da mesma edição sem guardar identificadores de visitantes.
        $statement = $this->db->prepare(
            'INSERT INTO article_edition_views (page_id, language_code, views) VALUES (?, ?, 1)
             ON CONFLICT(page_id, language_code) DO UPDATE SET views = article_edition_views.views + 1'
        );
        $statement->execute([$pageId, $locale]);
    }

    public function viewCount(string $pageId, ?string $locale = null): int
    {
        // The complete article total includes historical views. An edition only gets attributed views.
        // O total completo do artigo inclui as visualizações históricas. Uma edição recebe apenas as atribuídas.
        if ($locale !== null) {
            $statement = $this->db->prepare('SELECT views FROM article_edition_views WHERE page_id = ? AND language_code = ?');
            $statement->execute([$pageId, $locale]);
        } else {
            $statement = $this->db->prepare('SELECT COALESCE((SELECT views FROM article_views WHERE page_id = :page), 0)
                + (SELECT COALESCE(SUM(views), 0) FROM article_edition_views WHERE page_id = :page)');
            $statement->execute(['page' => $pageId]);
        }

        return (int) $statement->fetchColumn();
    }

    public function articleViews(): array
    {
        // One row per article with all edition counts and the old total shown independently.
        // Uma linha por artigo com a contagem de cada edição e o total antigo mostrado separadamente.
        return $this->db->query(
            "WITH editions AS (
                SELECT l.page_id, l.language_code, l.slug, r.title,
                    ROW_NUMBER() OVER (PARTITION BY l.page_id ORDER BY l.language_code <> 'pt', l.language_code) AS preferred
                FROM page_metadata m
                JOIN page_localizations l ON l.page_id = m.page_id
                JOIN page_revisions r ON r.id = l.published_revision_id AND r.status = 'published'
                WHERE m.content_type = 'article'
             )
             SELECT e.language_code, e.slug, e.title,
                    pt.views AS pt, en.views AS en, es.views AS es, COALESCE(h.views, 0) AS historical,
                    COALESCE(pt.views, 0) + COALESCE(en.views, 0) + COALESCE(es.views, 0) + COALESCE(h.views, 0) AS views
             FROM editions e
             LEFT JOIN article_views h ON h.page_id = e.page_id
             LEFT JOIN article_edition_views pt ON pt.page_id = e.page_id AND pt.language_code = 'pt'
             LEFT JOIN article_edition_views en ON en.page_id = e.page_id AND en.language_code = 'en'
             LEFT JOIN article_edition_views es ON es.page_id = e.page_id AND es.language_code = 'es'
             WHERE e.preferred = 1
             ORDER BY views DESC, e.title, e.page_id"
        )->fetchAll();
    }

    /**
     * @param list<string> $pageIds
     * @return array<string, array<string, array{views: int, approved_comments: int}>>
     */
    public function countsForArticles(array $pageIds): array
    {
        // Featured and section lists can repeat a page, so look up all displayed articles in one query.
        // O destaque e as editorias podem repetir uma página, então consulta todos os artigos exibidos de uma vez.
        $pageIds = array_values(array_unique($pageIds));
        if ($pageIds === []) return [];

        // Count views and approved comments for the published edition linked by each card.
        // Conta visualizações e comentários aprovados da edição publicada vinculada pela chamada.
        $placeholders = implode(', ', array_fill(0, count($pageIds), '?'));
        $statement = $this->db->prepare(
            "SELECT l.page_id, l.language_code, COALESCE(v.views, 0) AS views, COUNT(c.id) AS approved_comments
             FROM page_localizations l
             LEFT JOIN article_edition_views v ON v.page_id = l.page_id AND v.language_code = l.language_code
             LEFT JOIN article_comments c ON c.page_id = l.page_id AND c.language_code = l.language_code AND c.status = 'approved'
             WHERE l.published_revision_id IS NOT NULL AND l.page_id IN ($placeholders)
             GROUP BY l.page_id, l.language_code, v.views"
        );
        $statement->execute($pageIds);

        $counts = [];
        foreach ($statement as $edition) {
            $counts[$edition['page_id']][$edition['language_code']] = [
                'views' => (int) $edition['views'],
                'approved_comments' => (int) $edition['approved_comments'],
            ];
        }

        return $counts;
    }

    public function approvedComments(string $pageId, string $locale): array
    {
        // The public query returns display fields only and never includes the account identifier.
        // A consulta pública retorna apenas os campos de exibição e omite o identificador da conta.
        $statement = $this->db->prepare(
            "SELECT author_name, body, created_at FROM (
                SELECT id, author_name, body, created_at FROM article_comments
                WHERE page_id = ? AND language_code = ? AND status = 'approved'
                ORDER BY id DESC LIMIT 200
            ) ORDER BY id"
        );
        $statement->execute([$pageId, $locale]);

        return $statement->fetchAll();
    }

    public function submitComment(string $pageId, string $locale, array $user, string $body): bool
    {
        // SQLite evaluates the account limit and writes the pending comment in one statement.
        // O SQLite avalia o limite da conta e grava o comentário pendente em uma só instrução.
        $statement = $this->db->prepare(
            'INSERT INTO article_comments (page_id, language_code, author_id, author_name, body, created_at)
             SELECT ?, ?, ?, ?, ?, ?
             WHERE (SELECT COUNT(*) FROM article_comments WHERE author_id = ? AND created_at >= ?) < 5'
        );
        $statement->execute([
            $pageId, $locale, $user['id'], $user['name'], $body,
            gmdate('c'), $user['id'], gmdate('c', time() - 3600),
        ]);

        return $statement->rowCount() === 1;
    }

    public function moderationQueue(string $status): array
    {
        // Keep the locale and title visible so editors can review each comment in context.
        // Mostra o idioma e o título para que editores possam revisar cada comentário em contexto.
        $direction = $status === 'pending' ? 'ASC' : 'DESC';
        $statement = $this->db->prepare(
            'SELECT c.id, c.language_code, c.author_name, c.body, c.created_at, l.slug, r.title
             FROM article_comments c
             JOIN page_localizations l ON l.page_id = c.page_id AND l.language_code = c.language_code
             LEFT JOIN page_revisions r ON r.id = l.published_revision_id
             WHERE c.status = ?
             ORDER BY c.id '.$direction.' LIMIT 200'
        );
        $statement->execute([$status]);

        return $statement->fetchAll();
    }

    public function moderate(int $id, string $current, string $decision, string $moderator): bool
    {
        // Match the status seen by the editor so a stale form cannot undo another editor's decision.
        // Confere o status visto pelo editor para impedir que um formulário antigo desfaça a decisão de outro editor.
        $statement = $this->db->prepare(
            'UPDATE article_comments SET status = ?, moderated_by = ?, moderated_at = ?
             WHERE id = ? AND status = ?'
        );
        $statement->execute([$decision, $moderator, gmdate('c'), $id, $current]);

        return $statement->rowCount() === 1;
    }
}
