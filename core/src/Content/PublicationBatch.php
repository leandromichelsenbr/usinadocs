<?php

declare(strict_types=1);

namespace UsinaDocs\Core\Content;

use PDO;
use RuntimeException;
use Throwable;

final class PublicationBatch
{
    public const LANGUAGES = ['pt' => 'Português', 'en' => 'English', 'es' => 'Español'];

    public function __construct(
        private readonly PDO $db,
        private readonly EditorialService $editorial,
        private readonly PublishedPageRepository $pages,
        private readonly PageCovers $covers,
    ) {
    }

    public function preview(string $pageId): ?array
    {
        $query = $this->db->prepare('SELECT 1 FROM pages WHERE id = :id');
        $query->execute(['id' => $pageId]);
        if (!$query->fetchColumn()) {
            return null;
        }

        $editions = [];
        $ready = true;
        $hasDraft = false;
        foreach (self::LANGUAGES as $language => $label) {
            $page = $this->pages->findForPreview($pageId, $language);
            $draft = ($page['status'] ?? null) === 'draft';
            $missing = $draft ? $this->editorial->missingRequiredArtifacts($pageId, $language) : [];
            $editions[$language] = ['label' => $label, 'page' => $page, 'draft' => $draft, 'missing' => $missing];
            $ready = $ready && $page !== null && $missing === [];
            $hasDraft = $hasDraft || $draft;
        }

        $cover = $this->covers->get($pageId);

        // Hash the actual rendered revisions, including content that can change within a draft ID.
        // Calcula o hash das revisões exibidas, incluindo conteúdo que pode mudar dentro do mesmo ID de rascunho.
        $snapshot = hash('sha256', json_encode([$editions, $cover], JSON_THROW_ON_ERROR));

        return ['editions' => $editions, 'cover' => $cover, 'ready' => $ready && $hasDraft, 'snapshot' => $snapshot];
    }

    public function publish(string $pageId, string $snapshot): void
    {
        $this->db->beginTransaction();
        try {
            // Acquire the SQLite write lock before checking the preview and hold it through the three updates.
            // Obtém a trava de escrita do SQLite antes de conferir a prévia e a mantém até as três atualizações.
            $lock = $this->db->prepare('UPDATE pages SET updated_at = updated_at WHERE id = :id');
            $lock->execute(['id' => $pageId]);
            $batch = $this->preview($pageId);
            if ($batch === null || !$batch['ready'] || !hash_equals($batch['snapshot'], $snapshot)) {
                throw new RuntimeException('Confira a prévia atual. As três edições devem existir e todos os rascunhos devem estar completos.');
            }

            $now = gmdate('c');
            foreach ($batch['editions'] as $language => $edition) {
                if ($edition['draft']) {
                    $this->editorial->publishLocalized($pageId, $language, $now);
                }
            }
            $this->db->commit();
        } catch (Throwable $error) {
            $this->db->rollBack();
            throw $error;
        }
    }
}
