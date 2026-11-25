<?php

declare(strict_types=1);

namespace UsinaDocs\Core\Content;

use InvalidArgumentException;
use RuntimeException;
use UsinaDocs\Core\Media\MediaLibrary;

final class PackageImages
{
    private const TYPES = ['image/png' => 'png', 'image/jpeg' => 'jpg', 'image/webp' => 'webp'];

    /** @return list<string> */
    public static function ids(array $page, array $blocks): array
    {
        $ids = [$page['cover_media_id'] ?? null];
        foreach ($blocks as $block) {
            if (($block['type'] ?? '') === 'image') {
                $ids[] = $block['data']['id'] ?? null;
            }
        }

        return array_values(array_unique(array_filter($ids, static fn ($id): bool => is_string($id) && $id !== '')));
    }

    public static function addToExport(MediaLibrary $library, string $id, array &$files): array
    {
        $image = $library->find($id);
        $path = $image === null ? null : $library->path($image);
        $bytes = $path !== null && is_file($path) ? file_get_contents($path) : false;
        if ($bytes === false) {
            throw new RuntimeException('A imagem '.$id.' está indisponível na biblioteca.');
        }

        $type = self::TYPES[$image['mime_type']] ?? null;
        $sha = hash('sha256', $bytes);
        $assetPath = 'media/'.substr($sha, 0, 2).'/'.$sha.'.'.$type;
        $asset = array_intersect_key($image, array_flip(['id', 'original_name', 'mime_type', 'title', 'source_url', 'license_note', 'created_at']))
            + ['path' => $assetPath, 'sha256' => $sha, 'bytes' => strlen($bytes)];
        self::check($asset, [$assetPath => $bytes]);
        $files[$assetPath] = $bytes;

        return $asset;
    }

    public static function check(array $asset, array $files): void
    {
        // Build the allowed path from a validated content hash and inspect the binary instead of trusting its extension.
        // Monta o caminho permitido a partir de um hash de conteúdo validado e inspeciona o arquivo em vez de confiar na extensão.
        $id = $asset['id'] ?? null;
        $mime = $asset['mime_type'] ?? null;
        $sha = $asset['sha256'] ?? null;
        if (!is_string($id) || !preg_match('/^media-[A-Za-z0-9_-]{1,64}$/D', $id)
            || !is_string($mime) || !isset(self::TYPES[$mime])
            || !is_string($sha) || !preg_match('/^[0-9a-f]{64}$/D', $sha)
            || ($asset['path'] ?? null) !== 'media/'.substr($sha, 0, 2).'/'.$sha.'.'.self::TYPES[$mime]) {
            throw new InvalidArgumentException('Identidade de imagem inválida no pacote.');
        }
        foreach (['original_name', 'title', 'created_at'] as $field) {
            if (!is_string($asset[$field] ?? null)) {
                throw new InvalidArgumentException('Metadados de imagem inválidos no pacote.');
            }
        }
        foreach (['source_url', 'license_note'] as $field) {
            if (isset($asset[$field]) && !is_string($asset[$field])) {
                throw new InvalidArgumentException('Metadados de imagem inválidos no pacote.');
            }
        }
        $data = $files[$asset['path']] ?? null;
        if (!is_string($data) || strlen($data) > 5 * 1024 * 1024 || ($asset['bytes'] ?? null) !== strlen($data)
            || !hash_equals($sha, hash('sha256', $data))
            || (new \finfo(FILEINFO_MIME_TYPE))->buffer($data) !== $mime) {
            throw new InvalidArgumentException('A imagem '.$id.' está ausente, excede 5 MB ou tem formato inválido.');
        }
    }
}
