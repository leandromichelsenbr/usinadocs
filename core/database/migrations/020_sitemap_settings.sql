-- Store a snapshot for manual mode. Automatic mode reads the live publication state.
-- Guarda uma versão do mapa para o modo manual. O modo automático consulta a publicação atual.
CREATE TABLE IF NOT EXISTS sitemap_settings (
    id INTEGER PRIMARY KEY CHECK (id = 1),
    automatic INTEGER NOT NULL CHECK (automatic IN (0, 1)),
    snapshot_xml TEXT
);
