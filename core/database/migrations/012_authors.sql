-- Keep public author identities separate from accounts and their permissions.
-- Mantém as identidades públicas dos autores separadas das contas e suas permissões.
CREATE TABLE IF NOT EXISTS authors (
    slug TEXT PRIMARY KEY,
    name TEXT NOT NULL,
    bio_pt TEXT NOT NULL,
    bio_en TEXT NOT NULL DEFAULT '',
    bio_es TEXT NOT NULL DEFAULT ''
);

-- A byline belongs to the article and is shared by its localized editions.
-- A assinatura pertence ao artigo e é compartilhada por suas edições localizadas.
CREATE TABLE IF NOT EXISTS page_authors (
    page_id TEXT NOT NULL REFERENCES pages(id) ON DELETE CASCADE,
    author_slug TEXT NOT NULL REFERENCES authors(slug),
    position INTEGER NOT NULL,
    PRIMARY KEY (page_id, author_slug),
    UNIQUE (page_id, position)
);
