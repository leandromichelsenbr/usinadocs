CREATE TABLE IF NOT EXISTS editorial_model_revisions (
    id TEXT PRIMARY KEY,
    model_id TEXT NOT NULL REFERENCES editorial_models(id),
    number INTEGER NOT NULL,
    status TEXT NOT NULL CHECK (status IN ('draft', 'published')),
    label TEXT NOT NULL,
    description TEXT NOT NULL,
    created_at TEXT NOT NULL,
    published_at TEXT,
    UNIQUE (model_id, number)
);

CREATE TABLE IF NOT EXISTS editorial_model_revision_artifacts (
    id TEXT PRIMARY KEY,
    model_revision_id TEXT NOT NULL REFERENCES editorial_model_revisions(id),
    artifact_type TEXT NOT NULL,
    label TEXT NOT NULL,
    is_required INTEGER NOT NULL DEFAULT 0,
    position INTEGER NOT NULL,
    UNIQUE (model_revision_id, position)
);

CREATE INDEX IF NOT EXISTS editorial_model_revision_status
    ON editorial_model_revisions (model_id, status, number);
