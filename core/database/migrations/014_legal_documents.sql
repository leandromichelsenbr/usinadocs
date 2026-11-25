-- Keep a draft and a published snapshot per document so the three editions can be saved or released together.
-- Guarda um rascunho e uma versão pública por documento para salvar ou publicar as três edições juntas.
CREATE TABLE IF NOT EXISTS legal_documents (
    slug TEXT PRIMARY KEY,
    draft_json TEXT NOT NULL CHECK (json_valid(draft_json)),
    published_json TEXT CHECK (published_json IS NULL OR json_valid(published_json)),
    published_at TEXT,
    CHECK ((published_json IS NULL) = (published_at IS NULL))
);
