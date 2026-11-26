-- The administrator selects which edition each network announces. The worker reads secrets separately.
-- O administrador escolhe a edição que cada rede divulga. O processo de envio lê os segredos separadamente.
CREATE TABLE IF NOT EXISTS social_channels (
    network TEXT PRIMARY KEY CHECK (network IN ('linkedin', 'facebook')),
    language_code TEXT NOT NULL REFERENCES languages(code),
    enabled INTEGER NOT NULL CHECK (enabled IN (0, 1))
);

CREATE TABLE IF NOT EXISTS social_deliveries (
    page_id TEXT NOT NULL REFERENCES pages(id) ON DELETE CASCADE,
    network TEXT NOT NULL REFERENCES social_channels(network),
    language_code TEXT NOT NULL REFERENCES languages(code),
    queued_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
    state TEXT NOT NULL DEFAULT 'pending' CHECK (state IN ('pending', 'sent', 'rejected', 'unconfirmed')),
    http_status INTEGER,
    PRIMARY KEY (page_id, network)
);

-- Queue inside the same write as the public pointer. This also covers batch and direct editorial publishing.
-- Enfileira na mesma gravação do ponteiro público. Isso também cobre a publicação em lote e a publicação editorial direta.
-- A replacement revision never announces again. The unique key also prevents duplicates if a channel changes language.
-- Uma revisão substituta nunca gera outro anúncio. A chave única também evita duplicatas se o idioma do canal mudar.
CREATE TRIGGER IF NOT EXISTS queue_social_article
AFTER UPDATE OF published_revision_id ON page_localizations
WHEN OLD.published_revision_id IS NULL AND NEW.published_revision_id IS NOT NULL
    AND EXISTS (SELECT 1 FROM page_metadata WHERE page_id = NEW.page_id AND content_type = 'article')
    AND NOT EXISTS (SELECT 1 FROM hidden_articles WHERE page_id = NEW.page_id)
BEGIN
    INSERT OR IGNORE INTO social_deliveries (page_id, network, language_code)
        SELECT NEW.page_id, network, NEW.language_code FROM social_channels
        WHERE enabled = 1 AND language_code = NEW.language_code;
END;
