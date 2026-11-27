-- Localize a stable tag key once for all articles that use it. NULL labels mark a shared technical or universal name.
-- Traduz uma chave de hashtag para todas as matérias que a usam. Rótulos NULL marcam um nome técnico ou universal.
CREATE TABLE IF NOT EXISTS article_tag_labels (
    tag_key TEXT PRIMARY KEY,
    label_en TEXT,
    label_es TEXT,
    CHECK ((label_en IS NULL) = (label_es IS NULL))
);
