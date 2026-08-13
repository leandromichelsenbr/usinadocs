CREATE TABLE IF NOT EXISTS media (
    id TEXT PRIMARY KEY,
    filename TEXT NOT NULL,
    original_name TEXT NOT NULL,
    mime_type TEXT NOT NULL,
    byte_size INTEGER NOT NULL,
    title TEXT NOT NULL,
    source_url TEXT,
    license_note TEXT,
    created_at TEXT NOT NULL
);
