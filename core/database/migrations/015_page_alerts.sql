-- Store one editorial alert per article so all published editions carry the same priority.
-- Guarda um alerta editorial por artigo para que todas as edições publicadas tenham a mesma prioridade.
CREATE TABLE IF NOT EXISTS page_alerts (
    page_id TEXT PRIMARY KEY REFERENCES pages(id) ON DELETE CASCADE
);
