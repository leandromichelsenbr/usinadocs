-- Visibility belongs to the article, independently of its published language editions.
-- A visibilidade pertence ao artigo, independentemente das edições publicadas em cada idioma.
CREATE TABLE IF NOT EXISTS hidden_articles (
    page_id TEXT PRIMARY KEY REFERENCES pages(id) ON DELETE CASCADE
);

-- Archived revisions retain their snapshots and publication dates for audit and recovery.
-- Revisões arquivadas preservam o conteúdo e as datas de publicação para auditoria e recuperação.
CREATE TABLE IF NOT EXISTS archived_page_revisions (
    revision_id TEXT PRIMARY KEY REFERENCES page_revisions(id) ON DELETE CASCADE
);
