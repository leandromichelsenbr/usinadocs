-- Keep the site's presentation settings together so one save updates all assets.
-- Reúne os ajustes visuais do site para que uma gravação atualize todos os ativos.
CREATE TABLE IF NOT EXISTS site_appearance (
    site_id TEXT PRIMARY KEY REFERENCES sites(id) ON DELETE CASCADE,
    display_name TEXT NOT NULL DEFAULT '',
    favicon_id TEXT REFERENCES media(id) ON DELETE SET NULL,
    logo_light_id TEXT REFERENCES media(id) ON DELETE SET NULL,
    logo_dark_id TEXT REFERENCES media(id) ON DELETE SET NULL,
    accent_color TEXT NOT NULL DEFAULT ''
);
