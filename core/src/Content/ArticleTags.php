<?php

declare(strict_types=1);

namespace UsinaDocs\Core\Content;

use InvalidArgumentException;

final class ArticleTags
{
    public const BLOCK = 'article_tags';

    /** @return list<array{key: string, label: string}> */
    public static function parse(string $input): array
    {
        // Keep the editor's spelling for display and use one Unicode lowercase key for matching and deduplication.
        // Preserva a grafia para exibição e usa uma chave Unicode em minúsculas para busca e deduplicação.
        $parts = preg_split('/[\s,]+/u', trim($input), -1, PREG_SPLIT_NO_EMPTY);
        if ($parts === false) {
            throw new InvalidArgumentException('Invalid hashtags.');
        }
        $tags = [];
        foreach ($parts as $part) {
            $label = str_starts_with($part, '#') ? substr($part, 1) : $part;
            $key = self::key($label);
            if ($key === null) {
                throw new InvalidArgumentException('Invalid hashtag.');
            }
            $tags[$key] ??= ['key' => $key, 'label' => $label];
        }
        return array_values($tags);
    }

    public static function key(string $label): ?string
    {
        return preg_match('/\A[\p{L}\p{N}][\p{L}\p{M}\p{N}_-]{0,59}\z/u', $label) === 1
            ? mb_strtolower($label, 'UTF-8') : null;
    }

    public static function fromBlocks(array $blocks): array
    {
        foreach ($blocks as $block) {
            if (($block['type'] ?? null) === self::BLOCK) {
                return self::fromData($block['data'] ?? null);
            }
        }
        return [];
    }

    public static function fromData(mixed $data): array
    {
        // Packages can contain older or incomplete blocks. Only show entries with a valid label and matching key.
        // Pacotes podem conter blocos antigos ou incompletos. Exibe apenas entradas com rótulo válido e chave correspondente.
        $tags = is_array($data) && is_array($data['tags'] ?? null) ? $data['tags'] : [];
        return array_values(array_filter($tags, static fn ($tag): bool => is_array($tag)
            && is_string($tag['key'] ?? null) && is_string($tag['label'] ?? null)
            && self::key($tag['label']) === $tag['key']));
    }
}
