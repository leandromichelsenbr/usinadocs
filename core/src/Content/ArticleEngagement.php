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

    public function incrementViews(string $pageId): void
    {
        // An atomic upsert preserves both reads when separate sessions open the article at the same time.
        // Um upsert atômico preserva as duas leituras quando sessões diferentes abrem o artigo ao mesmo tempo.
        $statement = $this->db->prepare(
            'INSERT INTO article_views (page_id, views) VALUES (?, 1)
             ON CONFLICT(page_id) DO UPDATE SET views = article_views.views + 1'
        );
        $statement->execute([$pageId]);
    }

    public function viewCount(string $pageId): int
    {
        $statement = $this->db->prepare('SELECT views FROM article_views WHERE page_id = ?');
        $statement->execute([$pageId]);

        return (int) $statement->fetchColumn();
    }

    public function articleViews(): array
    {
        // Pick one published edition per article, preferring Portuguese when available.
        // Escolhe uma edição publicada por artigo, dando preferência ao português quando disponível.
        return $this->db->query(
            "WITH editions AS (
                SELECT l.page_id, l.language_code, l.slug, r.title,
                    ROW_NUMBER() OVER (PARTITION BY l.page_id ORDER BY l.language_code <> 'pt', l.language_code) AS preferred
                FROM page_metadata m
                JOIN page_localizations l ON l.page_id = m.page_id
                JOIN page_revisions r ON r.id = l.published_revision_id AND r.status = 'published'
                WHERE m.content_type = 'article'
             )
             SELECT e.language_code, e.slug, e.title, COALESCE(v.views, 0) AS views
             FROM editions e LEFT JOIN article_views v ON v.page_id = e.page_id
             WHERE e.preferred = 1
             ORDER BY views DESC, e.title, e.page_id"
        )->fetchAll();
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
