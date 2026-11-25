CREATE TABLE IF NOT EXISTS access_groups (
    id TEXT PRIMARY KEY,
    name TEXT NOT NULL
);

INSERT OR IGNORE INTO access_groups (id, name) VALUES
    ('reader', 'Leitor'), ('editor', 'Editor'), ('administrator', 'Administrador');

CREATE TABLE IF NOT EXISTS oauth_users (
    id TEXT PRIMARY KEY,
    provider TEXT NOT NULL CHECK (provider IN ('google', 'microsoft')),
    subject TEXT NOT NULL,
    name TEXT NOT NULL,
    email TEXT,
    role TEXT NOT NULL DEFAULT 'reader' REFERENCES access_groups(id),
    created_at TEXT NOT NULL,
    UNIQUE (provider, subject)
);

-- The first administrator is invited out of band and must prove control
-- of the invited address through a provider that verifies email.
CREATE TABLE IF NOT EXISTS oauth_admin_invitations (
    email TEXT PRIMARY KEY
);
