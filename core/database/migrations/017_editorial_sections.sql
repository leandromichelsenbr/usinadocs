-- Keep stable section IDs so editing titles or blocking a section preserves article assignments.
-- Mantém identificadores estáveis para preservar os vínculos ao editar ou bloquear uma editoria.
CREATE TABLE IF NOT EXISTS editorial_sections (
    slug TEXT PRIMARY KEY,
    title_pt TEXT NOT NULL,
    title_en TEXT NOT NULL,
    title_es TEXT NOT NULL,
    blocked INTEGER NOT NULL DEFAULT 0 CHECK (blocked IN (0, 1)),
    position INTEGER NOT NULL DEFAULT 0
);

CREATE TABLE IF NOT EXISTS article_section_assignments (
    page_id TEXT PRIMARY KEY REFERENCES pages(id) ON DELETE CASCADE,
    section_slug TEXT NOT NULL REFERENCES editorial_sections(slug)
);

CREATE INDEX IF NOT EXISTS article_section_assignments_section ON article_section_assignments(section_slug);
