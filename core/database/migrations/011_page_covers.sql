-- One cover serves the calls for every language of a page.
-- Uma capa atende às chamadas de todos os idiomas da página.
CREATE TABLE IF NOT EXISTS page_covers (
    page_id TEXT PRIMARY KEY REFERENCES pages(id) ON DELETE CASCADE,
    media_id TEXT NOT NULL REFERENCES media(id) ON DELETE CASCADE
);
