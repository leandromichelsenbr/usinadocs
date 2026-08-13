CREATE TABLE IF NOT EXISTS sites (
    id TEXT PRIMARY KEY,
    slug TEXT NOT NULL UNIQUE,
    name TEXT NOT NULL
);

CREATE TABLE IF NOT EXISTS languages (
    code TEXT PRIMARY KEY,
    name TEXT NOT NULL,
    native_name TEXT NOT NULL
);

CREATE TABLE IF NOT EXISTS pages (
    id TEXT PRIMARY KEY,
    site_id TEXT NOT NULL REFERENCES sites(id),
    created_at TEXT NOT NULL,
    updated_at TEXT NOT NULL
);

CREATE TABLE IF NOT EXISTS page_localizations (
    page_id TEXT NOT NULL REFERENCES pages(id),
    language_code TEXT NOT NULL REFERENCES languages(code),
    slug TEXT NOT NULL,
    published_revision_id TEXT,
    PRIMARY KEY (page_id, language_code),
    UNIQUE (language_code, slug)
);

CREATE TABLE IF NOT EXISTS page_revisions (
    id TEXT PRIMARY KEY,
    page_id TEXT NOT NULL REFERENCES pages(id),
    language_code TEXT NOT NULL REFERENCES languages(code),
    number INTEGER NOT NULL,
    status TEXT NOT NULL CHECK (status IN ('draft', 'in_review', 'published')),
    title TEXT NOT NULL,
    summary TEXT,
    created_at TEXT NOT NULL,
    published_at TEXT,
    UNIQUE (page_id, number)
);

CREATE TABLE IF NOT EXISTS blocks (
    id TEXT PRIMARY KEY,
    page_revision_id TEXT NOT NULL REFERENCES page_revisions(id),
    type TEXT NOT NULL,
    position INTEGER NOT NULL,
    data TEXT NOT NULL,
    UNIQUE (page_revision_id, position)
);
