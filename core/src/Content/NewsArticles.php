<?php

declare(strict_types=1);

namespace UsinaDocs\Core\Content;

use PDO;

/**
 * EN: Read model for the optional news homepage (USINADOCS_NEWS_HOME=1). It lists
 * published articles for the reader's language, newest first, optionally limited
 * to one editorial section. It only reads: publication, sections, covers and
 * alerts are managed by their own services.
 *
 * PT: Modelo de leitura da página inicial de notícias opcional
 * (USINADOCS_NEWS_HOME=1). Lista os artigos publicados no idioma do leitor, do
 * mais recente para o mais antigo, opcionalmente restritos a uma editoria. Só faz
 * leitura: publicação, editorias, capas e alertas são mantidos por seus próprios
 * serviços.
 */
final class NewsArticles
{
    public function __construct(private readonly PDO $database)
    {
    }

    /** @return list<array<string, string|int|null>> */
    public function latest(int $limit, ?string $section = null, string $language = 'pt'): array
    {
        // The localization points to the revision that readers can see. Joining
        // through that pointer keeps drafts out of the homepage and section lists.
        // A translation never changes the article's sort order, and its cover is shared across editions.
        // A tradução não muda a ordem do artigo, e a capa é compartilhada por todas as edições.
        // Return the stable page ID so the home can load metrics for all displayed editions together.
        // Retorna o ID estável da página para a home buscar as métricas das edições exibidas em conjunto.
        // The shared alert flag is available for any translation shown in the card.
        // O alerta compartilhado fica disponível para qualquer tradução exibida na chamada.
        // Apply the same article-wide visibility rule in every homepage list.
        // Aplica a mesma regra de visibilidade por artigo a todas as listas da página principal.
        // A blocked section stops labeling or grouping its articles. The latest list still includes them.
        // A editoria bloqueada deixa de rotular ou agrupar artigos. Eles continuam na lista de últimas matérias.
        $statement = $this->database->prepare(
            "SELECT pl.page_id, CASE WHEN translated.id IS NULL THEN pl.slug ELSE localized.slug END AS slug,
                 COALESCE(translated.title, r.title) AS title,
                 COALESCE(translated.summary, r.summary) AS summary,
                 COALESCE(translated.language_code, pl.language_code) AS language,
                 r.published_at, s.slug AS section, c.media_id AS cover_media_id, (alert.page_id IS NOT NULL) AS is_critical
             FROM page_localizations pl
             JOIN page_revisions r ON r.id = pl.published_revision_id
             LEFT JOIN page_localizations localized ON localized.page_id = pl.page_id AND localized.language_code = :language
             LEFT JOIN page_revisions translated ON translated.id = localized.published_revision_id AND translated.status = 'published'
             JOIN page_metadata m ON m.page_id = pl.page_id
             LEFT JOIN article_section_assignments a ON a.page_id = pl.page_id
             LEFT JOIN editorial_sections s ON s.slug = a.section_slug AND s.blocked = 0
             LEFT JOIN page_covers c ON c.page_id = pl.page_id
             LEFT JOIN page_alerts alert ON alert.page_id = pl.page_id
             WHERE pl.language_code = 'pt' AND r.status = 'published'
                 AND m.content_type = 'article' AND pl.page_id <> 'page-welcome'
                 AND NOT EXISTS (SELECT 1 FROM hidden_articles h WHERE h.page_id = pl.page_id)
                 AND (:section IS NULL OR s.slug = :section)
             ORDER BY r.published_at DESC, pl.slug ASC
             LIMIT :limit"
        );
        $statement->bindValue('language', $language, PDO::PARAM_STR);
        $statement->bindValue('section', $section, $section === null ? PDO::PARAM_NULL : PDO::PARAM_STR);
        $statement->bindValue('limit', $limit, PDO::PARAM_INT);
        $statement->execute();

        return $statement->fetchAll(PDO::FETCH_ASSOC);
    }

}
