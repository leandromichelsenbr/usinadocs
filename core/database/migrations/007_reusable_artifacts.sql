CREATE TABLE IF NOT EXISTS reusable_artifacts (
    id TEXT PRIMARY KEY,
    site_id TEXT NOT NULL REFERENCES sites(id),
    slug TEXT NOT NULL UNIQUE,
    artifact_type TEXT NOT NULL,
    published_revision_id TEXT,
    created_at TEXT NOT NULL,
    updated_at TEXT NOT NULL
);

CREATE TABLE IF NOT EXISTS reusable_artifact_revisions (
    id TEXT PRIMARY KEY,
    artifact_id TEXT NOT NULL REFERENCES reusable_artifacts(id),
    number INTEGER NOT NULL,
    status TEXT NOT NULL CHECK (status IN ('draft', 'published')),
    title TEXT NOT NULL,
    description TEXT NOT NULL,
    data TEXT NOT NULL,
    created_at TEXT NOT NULL,
    published_at TEXT,
    UNIQUE (artifact_id, number)
);

CREATE INDEX IF NOT EXISTS reusable_artifact_revision_status
    ON reusable_artifact_revisions (artifact_id, status, number);
