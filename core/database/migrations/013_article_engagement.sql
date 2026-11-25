-- Keep a single aggregate per article across translations and revisions. Visitor identifiers stay out of the database.
-- Mantém um total por artigo para todas as traduções e revisões. Identificadores de visitantes ficam fora do banco.
CREATE TABLE IF NOT EXISTS article_views (
    page_id TEXT PRIMARY KEY REFERENCES pages(id) ON DELETE CASCADE,
    views INTEGER NOT NULL CHECK (views > 0)
);

-- Keep discussions attached to the edition's language, even when its published revision changes.
-- Mantém a conversa no idioma da edição, mesmo quando sua revisão publicada muda.
CREATE TABLE IF NOT EXISTS article_comments (
    id INTEGER PRIMARY KEY,
    page_id TEXT NOT NULL,
    language_code TEXT NOT NULL,
    author_id TEXT NOT NULL,
    author_name TEXT NOT NULL,
    body TEXT NOT NULL,
    created_at TEXT NOT NULL,
    status TEXT NOT NULL DEFAULT 'pending' CHECK (status IN ('pending', 'approved', 'rejected')),
    moderated_by TEXT,
    moderated_at TEXT,
    FOREIGN KEY (page_id, language_code) REFERENCES page_localizations(page_id, language_code) ON DELETE CASCADE
);

CREATE INDEX IF NOT EXISTS article_comments_thread ON article_comments(page_id, language_code, status, id);
CREATE INDEX IF NOT EXISTS article_comments_rate ON article_comments(author_id, created_at);
CREATE INDEX IF NOT EXISTS article_comments_queue ON article_comments(status, id);
