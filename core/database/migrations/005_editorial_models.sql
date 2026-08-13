CREATE TABLE IF NOT EXISTS editorial_models (
    id TEXT PRIMARY KEY,
    content_type TEXT NOT NULL UNIQUE,
    label TEXT NOT NULL,
    description TEXT NOT NULL,
    is_system INTEGER NOT NULL DEFAULT 1
);

CREATE TABLE IF NOT EXISTS editorial_model_artifacts (
    id TEXT PRIMARY KEY,
    model_id TEXT NOT NULL REFERENCES editorial_models(id),
    artifact_type TEXT NOT NULL,
    label TEXT NOT NULL,
    is_required INTEGER NOT NULL DEFAULT 0,
    position INTEGER NOT NULL,
    UNIQUE (model_id, artifact_type)
);
