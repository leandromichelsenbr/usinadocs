<?php

declare(strict_types=1);

namespace UsinaDocs\Core\Content;

use InvalidArgumentException;
use PDO;
use UsinaDocs\Core\Media\MediaLibrary;

final class PageCovers
{
    public function __construct(private readonly PDO $db, private readonly MediaLibrary $media)
    {
    }

    public function get(string $pageId): ?string
    {
        $query = $this->db->prepare('SELECT media_id FROM page_covers WHERE page_id = :page');
        $query->execute(['page' => $pageId]);

        return $query->fetchColumn() ?: null;
    }

    public function set(string $pageId, ?string $mediaId): void
    {
        if ($mediaId === null) {
            $query = $this->db->prepare('DELETE FROM page_covers WHERE page_id = :page');
            $query->execute(['page' => $pageId]);
            return;
        }

        // Only select a library image that the media endpoint can actually serve.
        // Seleciona apenas uma imagem da biblioteca que o endereço de mídia pode servir.
        $asset = $this->media->find($mediaId);
        if ($asset === null || !is_file($this->media->path($asset))) {
            throw new InvalidArgumentException('Escolha uma imagem disponível na biblioteca de mídia.');
        }

        $query = $this->db->prepare(
            'INSERT INTO page_covers (page_id, media_id) VALUES (:page, :media)
             ON CONFLICT (page_id) DO UPDATE SET media_id = excluded.media_id'
        );
        $query->execute(['page' => $pageId, 'media' => $mediaId]);
    }
}
