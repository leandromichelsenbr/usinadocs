-- Existing article-wide totals have no language attribution. Keep them and start counting editions separately.
-- Os totais existentes por artigo não identificam idioma. Preserva esses valores e passa a contar as edições separadamente.
CREATE TABLE IF NOT EXISTS article_edition_views (
    page_id TEXT NOT NULL,
    language_code TEXT NOT NULL,
    views INTEGER NOT NULL CHECK (views > 0),
    PRIMARY KEY (page_id, language_code),
    FOREIGN KEY (page_id, language_code) REFERENCES page_localizations(page_id, language_code) ON DELETE CASCADE
);

-- The destination ID is public metadata. The worker still reads tokens only from its private configuration.
-- O ID do destino é um metadado público. O processo de envio continua lendo os tokens só da configuração privada.
CREATE TABLE IF NOT EXISTS social_destinations (
    network TEXT PRIMARY KEY REFERENCES social_channels(network) ON DELETE CASCADE,
    destination_id TEXT NOT NULL
);
