<?php

declare(strict_types=1);

namespace UsinaDocs\Core\Content;

use PDO;

final class EditorialMaintenance
{
    public function __construct(private readonly PDO $db)
    {
    }

    public function setHidden(string $pageId, bool $hidden): bool
    {
        // Use a separate marker so publishing a new revision cannot make a hidden article public.
        // Usa uma marcação separada para que publicar uma revisão nova não volte a exibir o artigo.
        $statement = $this->db->prepare($hidden
            ? "INSERT INTO hidden_articles (page_id)
               SELECT page_id FROM page_metadata WHERE page_id = ? AND content_type = 'article'
               ON CONFLICT(page_id) DO UPDATE SET page_id = excluded.page_id"
            : 'DELETE FROM hidden_articles WHERE page_id = ?');
        $statement->execute([$pageId]);

        return !$hidden || $statement->rowCount() > 0;
    }

    public function setArchived(string $pageId, string $locale, string $revisionId, bool $archived): bool
    {
        // Check the current publication pointer in the same write that archives the snapshot.
        // Confere a versão publicada atual na mesma gravação que arquiva a revisão.
        $statement = $this->db->prepare($archived
            ? "INSERT INTO archived_page_revisions (revision_id)
               SELECT r.id FROM page_revisions r
               JOIN page_localizations l ON l.page_id = r.page_id AND l.language_code = r.language_code
               WHERE r.page_id = ? AND r.language_code = ? AND r.id = ? AND r.status = 'published'
                 AND r.id IS NOT l.published_revision_id
               ON CONFLICT(revision_id) DO UPDATE SET revision_id = excluded.revision_id"
            : 'DELETE FROM archived_page_revisions WHERE revision_id IN (
                SELECT id FROM page_revisions WHERE page_id = ? AND language_code = ? AND id = ?
               )');
        $statement->execute([$pageId, $locale, $revisionId]);

        return $statement->rowCount() > 0;
    }
}
