<?php

declare(strict_types=1);

namespace UsinaDocs\Core\Content;

use InvalidArgumentException;
use PDO;

final class PageAlerts
{
    public function __construct(private readonly PDO $db)
    {
    }

    public function isCritical(string $pageId): bool
    {
        $query = $this->db->prepare('SELECT 1 FROM page_alerts WHERE page_id = ?');
        $query->execute([$pageId]);

        return (bool) $query->fetchColumn();
    }

    public function setCritical(string $pageId, bool $critical): void
    {
        // Set the marker only for articles. A repeated request leaves the same result.
        // Marca somente artigos. Repetir a solicitação mantém o mesmo resultado.
        if (!$critical) {
            $query = $this->db->prepare('DELETE FROM page_alerts WHERE page_id = ?');
            $query->execute([$pageId]);

            return;
        }

        $query = $this->db->prepare(
            "INSERT INTO page_alerts (page_id)
             SELECT page_id FROM page_metadata WHERE page_id = ? AND content_type = 'article'
             ON CONFLICT(page_id) DO UPDATE SET page_id = excluded.page_id"
        );
        $query->execute([$pageId]);

        if ($query->rowCount() === 0) {
            throw new InvalidArgumentException('O alerta crítico está disponível somente para artigos.');
        }
    }
}
