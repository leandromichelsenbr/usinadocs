<?php

declare(strict_types=1);

namespace UsinaDocs\Core\Content;

use InvalidArgumentException;
use PDO;

final class LegalDocuments
{
    public const LANGUAGES = ['pt' => 'Português', 'en' => 'English', 'es' => 'Español'];
    private const DEFAULT_TITLES = [
        'privacidade' => ['pt' => 'Política de privacidade', 'en' => 'Privacy policy', 'es' => 'Política de privacidad'],
        'termos-de-uso' => ['pt' => 'Termos de uso', 'en' => 'Terms of use', 'es' => 'Términos de uso'],
        'responsabilidade' => ['pt' => 'Aviso de responsabilidade', 'en' => 'Disclaimer', 'es' => 'Aviso de responsabilidad'],
        'cookies' => ['pt' => 'Política de cookies', 'en' => 'Cookie policy', 'es' => 'Política de cookies'],
    ];

    public function __construct(private readonly PDO $db)
    {
    }

    public function listing(): array
    {
        // Offer common document types immediately without publishing placeholder text.
        // Oferece os tipos de documento mais comuns desde o início, sem publicar textos provisórios.
        $list = [];
        foreach (self::DEFAULT_TITLES as $slug => $titles) {
            $list[$slug] = ['title' => $titles['pt'], 'status' => 'empty'];
        }

        foreach ($this->db->query("SELECT slug, json_extract(draft_json, '$.pt.title') AS title, published_at, draft_json <> published_json AS changed FROM legal_documents ORDER BY slug") as $row) {
            $slug = $row['slug'];
            $list[$slug] = [
                'title' => $row['title'] !== '' ? $row['title'] : ($list[$slug]['title'] ?? $slug),
                'status' => match (true) {
                    $row['published_at'] === null => 'draft',
                    (bool) $row['changed'] => 'changed',
                    default => 'published',
                },
            ];
        }

        return $list;
    }

    public function draft(string $slug): ?array
    {
        $statement = $this->db->prepare('SELECT draft_json FROM legal_documents WHERE slug = ?');
        $statement->execute([$slug]);
        $json = $statement->fetchColumn();

        if ($json !== false) return json_decode($json, true, flags: JSON_THROW_ON_ERROR);

        return isset(self::DEFAULT_TITLES[$slug])
            ? array_map(static fn (string $title): array => ['title' => $title, 'body' => ''], self::DEFAULT_TITLES[$slug])
            : null;
    }

    public function published(string $slug, string $locale): ?array
    {
        $statement = $this->db->prepare('SELECT published_json, published_at FROM legal_documents WHERE slug = ? AND published_json IS NOT NULL');
        $statement->execute([$slug]);
        $row = $statement->fetch();
        if ($row === false) return null;

        $editions = json_decode($row['published_json'], true, flags: JSON_THROW_ON_ERROR);
        return $editions[$locale] + ['published_at' => $row['published_at']];
    }

    public function links(string $locale): array
    {
        // Footers read only titles from published snapshots. Edits in the form do not change link labels.
        // Rodapés leem apenas os títulos das versões publicadas. Alterações no formulário não mudam os rótulos dos links.
        $statement = $this->db->prepare('SELECT slug, json_extract(published_json, ?) AS title FROM legal_documents WHERE published_json IS NOT NULL ORDER BY slug');
        $statement->execute(['$.'.$locale.'.title']);
        return $statement->fetchAll();
    }

    public function save(string $slug, array $editions, bool $publish, bool $create): void
    {
        if (!preg_match('/\A[a-z0-9]+(?:-[a-z0-9]+)*\z/', $slug) || strlen($slug) > 64 || $slug === 'new') {
            throw new InvalidArgumentException('Use um endereço de até 64 caracteres com letras minúsculas, números e hífens.');
        }
        if ($create && isset(self::DEFAULT_TITLES[$slug])) {
            throw new InvalidArgumentException('Abra o documento correspondente na lista para editá-lo.');
        }

        $texts = [];
        foreach (self::LANGUAGES as $locale => $language) {
            $edition = $editions[$locale] ?? [];
            $title = $edition['title'] ?? '';
            $body = $edition['body'] ?? '';
            if (!is_string($title) || !is_string($body) || !mb_check_encoding([$title, $body], 'UTF-8')
                || mb_strlen($title) > 160 || mb_strlen($body) > 30000) {
                throw new InvalidArgumentException("Em {$language}, o título deve ter até 160 caracteres e o texto até 30.000.");
            }

            $title = trim($title);
            $body = trim($body);
            if ($publish && ($title === '' || $body === '')) {
                throw new InvalidArgumentException("Preencha o título e o texto em {$language} para publicar.");
            }
            $texts[$locale] = ['title' => $title, 'body' => $body];
        }

        // Check the shared public namespace before announcing a legal page that an article would mask.
        // Confere os endereços públicos compartilhados antes de anunciar um texto legal que um artigo ocultaria.
        if ($publish) {
            $claimed = $this->db->prepare("SELECT 1 FROM page_localizations
                WHERE slug = ? AND language_code IN ('pt', 'en', 'es') AND published_revision_id IS NOT NULL");
            $claimed->execute(['legal-'.$slug]);
            if ($claimed->fetchColumn()) {
                throw new InvalidArgumentException('Uma página editorial já usa o endereço público deste documento.');
            }
        }

        // One upsert makes all translations visible together. A draft write leaves the published snapshot intact.
        // Um upsert torna todas as traduções visíveis juntas. Salvar o rascunho preserva a versão pública.
        $conflict = $create ? 'DO NOTHING' : 'DO UPDATE SET
            draft_json = excluded.draft_json,
            published_json = COALESCE(excluded.published_json, legal_documents.published_json),
            published_at = COALESCE(excluded.published_at, legal_documents.published_at)';
        $statement = $this->db->prepare(
            'INSERT INTO legal_documents(slug, draft_json, published_json, published_at) VALUES (?, ?, ?, ?)
             ON CONFLICT(slug) '.$conflict
        );
        $json = json_encode($texts, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
        $statement->execute([$slug, $json, $publish ? $json : null, $publish ? gmdate('c') : null]);
        if ($create && $statement->rowCount() !== 1) {
            throw new InvalidArgumentException('Já existe um documento com esse endereço.');
        }
    }
}
