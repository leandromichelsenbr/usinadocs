<?php

declare(strict_types=1);

namespace UsinaDocs\Core\Social;

use PDO;

final class SocialPublication
{
    public const NETWORKS = ['linkedin' => 'LinkedIn', 'facebook' => 'Facebook'];

    public function __construct(private readonly PDO $db)
    {
    }

    public function channels(): array
    {
        return $this->db->query('SELECT c.network, c.language_code, c.enabled, d.destination_id
            FROM social_channels c LEFT JOIN social_destinations d ON d.network = c.network')
            ->fetchAll(PDO::FETCH_UNIQUE | PDO::FETCH_ASSOC);
    }

    public function saveChannel(string $network, string $language, bool $enabled, ?string $destination = null): void
    {
        // Change the enable flag and its destination together so the worker sees one consistent setting.
        // Altera a ativação e o destino juntos para que o processo de envio veja uma configuração consistente.
        $this->db->beginTransaction();
        try {
            $save = $this->db->prepare('INSERT INTO social_channels (network, language_code, enabled)
                VALUES (:network, :language, :enabled) ON CONFLICT(network) DO UPDATE
                SET language_code = excluded.language_code, enabled = excluded.enabled');
            $save->execute(['network' => $network, 'language' => $language, 'enabled' => (int) $enabled]);
            if ($destination !== null) {
                $saveDestination = $this->db->prepare('INSERT INTO social_destinations (network, destination_id)
                    VALUES (?, ?) ON CONFLICT(network) DO UPDATE SET destination_id = excluded.destination_id');
                $saveDestination->execute([$network, $destination]);
            }
            $this->db->commit();
        } catch (\Throwable $error) {
            $this->db->rollBack();
            throw $error;
        }
    }

    public function recent(): array
    {
        return $this->db->query('SELECT d.*, COALESCE(r.title, l.slug, d.page_id) AS title
            FROM social_deliveries d
            LEFT JOIN page_localizations l ON l.page_id = d.page_id AND l.language_code = d.language_code
            LEFT JOIN page_revisions r ON r.id = l.published_revision_id
            ORDER BY d.queued_at DESC, d.rowid DESC LIMIT 25')->fetchAll();
    }

    public function pending(string $network): array
    {
        // Read the current public edition so corrections made before the worker runs are reflected in the post.
        // Lê a edição pública atual para que correções feitas antes do envio apareçam no anúncio.
        $query = $this->db->prepare("SELECT d.page_id, d.language_code, l.slug, r.title, r.summary
            FROM social_deliveries d
            JOIN social_channels c ON c.network = d.network AND c.enabled = 1
            JOIN page_localizations l ON l.page_id = d.page_id AND l.language_code = d.language_code
            JOIN page_revisions r ON r.id = l.published_revision_id AND r.status = 'published'
            JOIN page_metadata m ON m.page_id = d.page_id AND m.content_type = 'article'
            WHERE d.network = :network AND d.state = 'pending'
                AND NOT EXISTS (SELECT 1 FROM hidden_articles WHERE page_id = d.page_id)
            ORDER BY d.queued_at, d.rowid LIMIT 5");
        $query->execute(['network' => $network]);
        return $query->fetchAll();
    }

    public function claim(string $page, string $network, ?string $configuredDestination = null): bool
    {
        // Persist uncertainty before network I/O. A crash or timeout must never trigger a second post automatically.
        // Persiste a incerteza antes de acessar a rede. Uma interrupção ou timeout nunca deve gerar outro anúncio automático.
        $claim = $this->db->prepare("UPDATE social_deliveries AS d SET state = 'unconfirmed', http_status = NULL
            WHERE page_id = :page AND network = :network AND state = 'pending'
                AND EXISTS (SELECT 1 FROM social_channels c WHERE c.network = d.network AND c.enabled = 1)
                AND (SELECT destination_id FROM social_destinations WHERE network = d.network) IS :destination
                AND NOT EXISTS (SELECT 1 FROM hidden_articles h WHERE h.page_id = d.page_id)
                AND EXISTS (SELECT 1 FROM page_metadata m WHERE m.page_id = d.page_id AND m.content_type = 'article')");
        $claim->execute(['page' => $page, 'network' => $network, 'destination' => $configuredDestination]);
        return $claim->rowCount() === 1;
    }

    public function finish(string $page, string $network, int $status): void
    {
        // Only explicit success or rejection changes the claim. HTTP 408 and 5xx leave delivery uncertain.
        // Somente sucesso ou rejeição explícitos alteram a reserva. HTTP 408 e 5xx deixam o envio incerto.
        $state = match (true) {
            $network === 'linkedin' && $status === 201, $network === 'facebook' && $status === 200 => 'sent',
            $status >= 400 && $status < 500 && $status !== 408 => 'rejected',
            default => 'unconfirmed',
        };
        $done = $this->db->prepare('UPDATE social_deliveries SET state = :state, http_status = :status
            WHERE page_id = :page AND network = :network');
        $done->execute(['state' => $state, 'status' => $status ?: null, 'page' => $page, 'network' => $network]);
    }

    public function retryRejected(string $page, string $network): void
    {
        // An administrator can retry explicit rejections after fixing access or configuration.
        // Um administrador pode reenviar rejeições explícitas após corrigir acesso ou configuração.
        $retry = $this->db->prepare("UPDATE social_deliveries SET state = 'pending', http_status = NULL
            WHERE page_id = :page AND network = :network AND state = 'rejected'");
        $retry->execute(['page' => $page, 'network' => $network]);
    }
}
