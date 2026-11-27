<?php

declare(strict_types=1);

namespace UsinaDocs\Core\Content;

use PDO;

final class Sitemap
{
    public function __construct(private readonly PDO $db, private readonly bool $newsHome)
    {
    }

    public function settings(): array
    {
        return $this->db->query('SELECT automatic, snapshot_xml FROM sitemap_settings WHERE id = 1')->fetch()
            ?: ['automatic' => 1, 'snapshot_xml' => null];
    }

    public function save(string $origin, bool $automatic): void
    {
        // One write switches the mode and stores a complete snapshot when the editor selects manual mode.
        // Uma gravação muda o modo e guarda um mapa completo quando o editor seleciona o modo manual.
        $query = $this->db->prepare('INSERT INTO sitemap_settings (id, automatic, snapshot_xml) VALUES (1, ?, ?)
            ON CONFLICT(id) DO UPDATE SET automatic = excluded.automatic, snapshot_xml = excluded.snapshot_xml');
        $query->execute([(int) $automatic, $automatic ? null : $this->generate($origin)]);
    }

    public function xml(string $origin): string
    {
        $settings = $this->settings();
        return $settings['automatic'] ? $this->generate($origin) : (string) $settings['snapshot_xml'];
    }

    private function generate(string $origin): string
    {
        // Follow the public revision pointer. Draft edits and view counts never change the sitemap timestamp.
        // Segue o ponteiro da revisão pública. Rascunhos e contagens de visualizações jamais mudam a data no sitemap.
        $rows = $this->db->query(
            "SELECT 'page:' || l.page_id AS group_id, l.language_code AS locale, l.slug,
                    strftime('%Y-%m-%dT%H:%M:%SZ', r.published_at) AS modified
             FROM page_localizations l
             JOIN page_revisions r ON r.id = l.published_revision_id AND r.status = 'published'
             WHERE NOT EXISTS (SELECT 1 FROM hidden_articles h WHERE h.page_id = l.page_id)
             UNION ALL
             SELECT 'legal:' || d.slug, lang.code, 'legal-' || d.slug,
                    strftime('%Y-%m-%dT%H:%M:%SZ', d.published_at)
             FROM legal_documents d CROSS JOIN languages lang
             WHERE d.published_json IS NOT NULL
               AND json_extract(d.published_json, '$.' || lang.code || '.title') IS NOT NULL
             UNION ALL
             SELECT 'author:' || a.slug, lang.code, 'autor-' || a.slug, NULL
             FROM authors a CROSS JOIN languages lang
             WHERE CASE lang.code WHEN 'pt' THEN a.bio_pt WHEN 'en' THEN a.bio_en WHEN 'es' THEN a.bio_es END <> ''
             UNION ALL
             SELECT 'author-directory', lang.code, 'autores', NULL FROM languages lang
             WHERE EXISTS (SELECT 1 FROM authors)
             ORDER BY group_id, locale"
        );

        $entries = $this->newsHome ? ['/' => ['group_id' => 'home', 'modified' => null]] : [];
        $alternates = [];
        foreach ($rows as $row) {
            $locale = $row['locale'];
            $slug = $row['slug'];
            // The news homepage uses / as its canonical URL and omits the core's seeded example.
            // A página inicial de notícias usa / como URL canônica e omite o exemplo instalado pelo núcleo.
            if ($this->newsHome && (($locale === 'pt' && $slug === 'inicio') || $row['group_id'] === 'page:page-welcome')) {
                continue;
            }
            $path = '/'.$locale.'/p/'.rawurlencode($slug);
            $entries[$path] = $row;
        }

        // Build alternatives from the final URL set so each edition lists itself and the same siblings.
        // Gera as alternativas a partir dos endereços finais para que cada edição liste a si mesma e as irmãs.
        foreach ($entries as $path => $row) {
            if (isset($row['locale'])) {
                $alternates[$row['group_id']][$row['locale']] = $origin.$path;
            }
        }

        $escape = static fn (string $value): string => htmlspecialchars($value, ENT_QUOTES | ENT_XML1 | ENT_SUBSTITUTE, 'UTF-8');
        $xml = ['<?xml version="1.0" encoding="UTF-8"?>', '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9" xmlns:xhtml="http://www.w3.org/1999/xhtml">'];
        foreach ($entries as $path => $row) {
            $xml[] = '  <url>';
            $xml[] = '    <loc>'.$escape($origin.$path).'</loc>';
            if ($row['modified'] !== null) {
                $xml[] = '    <lastmod>'.$row['modified'].'</lastmod>';
            }
            $siblings = $alternates[$row['group_id']] ?? [];
            if (count($siblings) > 1) {
                foreach ($siblings as $locale => $url) {
                    $xml[] = '    <xhtml:link rel="alternate" hreflang="'.$escape($locale).'" href="'.$escape($url).'"/>';
                }
            }
            $xml[] = '  </url>';
        }
        $xml[] = '</urlset>';
        return implode("\n", $xml)."\n";
    }
}
