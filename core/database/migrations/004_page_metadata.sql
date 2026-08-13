CREATE TABLE IF NOT EXISTS page_metadata (
    page_id TEXT PRIMARY KEY REFERENCES pages(id),
    content_type TEXT NOT NULL DEFAULT 'reference' CHECK (content_type IN ('reference', 'article', 'lesson', 'class', 'entry_point')),
    updated_at TEXT NOT NULL
);
