<?php

declare(strict_types=1);

namespace UsinaDocs\Core\Content;

use InvalidArgumentException;
use PDO;

final class ArticleTagLabels
{
    private ?array $rules = null;

    public function __construct(private readonly PDO $db)
    {
    }

    public function label(string $key, string $original, string $language): ?string
    {
        if ($language === 'pt') {
            return $original;
        }

        // A new tag looks Portuguese until an editor supplies translations or marks it as universal.
        // Uma hashtag nova fica apenas em português até receber traduções ou ser marcada como universal.
        $rule = $this->rules()[$key] ?? null;
        return $rule === null ? null : ($rule['label_'.$language] ?? $original);
    }

    public function forReader(array $tags, string $language): array
    {
        $localized = [];
        foreach ($tags as $tag) {
            $label = $this->label($tag['key'], $tag['label'], $language);
            if ($label !== null) {
                $localized[] = ['key' => $tag['key'], 'label' => $label];
            }
        }
        return $localized;
    }

    public function catalog(): array
    {
        // List tags in current published copies and active drafts, with the published spelling first.
        // Lista as hashtags em revisões públicas e rascunhos ativos, com a grafia publicada primeiro.
        $blocks = $this->db->query("SELECT b.data FROM page_localizations l
            JOIN page_revisions r ON r.page_id = l.page_id AND r.language_code = l.language_code
            JOIN page_metadata m ON m.page_id = l.page_id AND m.content_type = 'article'
            JOIN blocks b ON b.page_revision_id = r.id AND b.type = 'article_tags'
            WHERE l.language_code = 'pt' AND (l.published_revision_id = r.id OR r.status = 'draft')
            ORDER BY r.status = 'published' DESC, r.number DESC, b.position");
        $tags = [];
        $rules = $this->rules();
        foreach ($blocks->fetchAll(PDO::FETCH_COLUMN) as $json) {
            foreach (ArticleTags::fromData(json_decode($json, true)) as $tag) {
                $tags[$tag['key']] ??= $tag + ['rule' => $rules[$tag['key']] ?? null];
            }
        }
        uasort($tags, static fn (array $a, array $b): int => strnatcasecmp($a['label'], $b['label']));
        return $tags;
    }

    public function save(string $key, bool $universal, string $english, string $spanish): void
    {
        $english = trim($english);
        $spanish = trim($spanish);
        // Pasted hashtags may include their display prefix. The template adds it when rendering.
        // Hashtags coladas podem trazer o prefixo de exibição. O template acrescenta o prefixo ao renderizar.
        $english = str_starts_with($english, '#') ? substr($english, 1) : $english;
        $spanish = str_starts_with($spanish, '#') ? substr($spanish, 1) : $spanish;
        if (ArticleTags::key($key) !== $key
            || (!$universal && (ArticleTags::key($english) === null || ArticleTags::key($spanish) === null))) {
            throw new InvalidArgumentException('Invalid hashtag labels.');
        }
        $save = $this->db->prepare('INSERT INTO article_tag_labels (tag_key, label_en, label_es) VALUES (?, ?, ?)
            ON CONFLICT (tag_key) DO UPDATE SET label_en = excluded.label_en, label_es = excluded.label_es');
        $save->execute([$key, $universal ? null : $english, $universal ? null : $spanish]);
        $this->rules = null;
    }

    private function rules(): array
    {
        // All cards use the same glossary snapshot without a query per tag or article.
        // Todas as chamadas usam a mesma leitura do glossário, sem uma consulta por hashtag ou matéria.
        return $this->rules ??= $this->db->query('SELECT tag_key, label_en, label_es FROM article_tag_labels')
            ->fetchAll(PDO::FETCH_UNIQUE | PDO::FETCH_ASSOC);
    }
}
