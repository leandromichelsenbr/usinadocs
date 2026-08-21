CREATE TABLE IF NOT EXISTS reusable_artifact_localizations (
    artifact_id TEXT NOT NULL REFERENCES reusable_artifacts(id),
    language_code TEXT NOT NULL REFERENCES languages(code),
    published_revision_id TEXT,
    PRIMARY KEY (artifact_id, language_code)
);

CREATE TABLE IF NOT EXISTS reusable_artifact_translation_revisions (
    id TEXT PRIMARY KEY,
    artifact_id TEXT NOT NULL REFERENCES reusable_artifacts(id),
    language_code TEXT NOT NULL REFERENCES languages(code),
    number INTEGER NOT NULL,
    status TEXT NOT NULL CHECK (status IN ('draft', 'published')),
    source_revision_id TEXT NOT NULL REFERENCES reusable_artifact_revisions(id),
    title TEXT NOT NULL,
    description TEXT NOT NULL,
    data TEXT NOT NULL,
    created_at TEXT NOT NULL,
    published_at TEXT,
    UNIQUE (artifact_id, language_code, number)
);

CREATE INDEX IF NOT EXISTS reusable_artifact_translation_status
    ON reusable_artifact_translation_revisions (artifact_id, language_code, status, number);
