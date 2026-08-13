<?php

declare(strict_types=1);

namespace UsinaDocs\Core\Infrastructure;

use PDO;

final class Schema
{
    public static function migrate(PDO $database, string $projectRoot): void
    {
        $database->exec((string) file_get_contents($projectRoot.'/database/migrations/001_core.sql'));
    }

    public static function seed(PDO $database): void
    {
        $now = gmdate('c');
        $database->exec("INSERT OR IGNORE INTO sites (id, slug, name) VALUES ('site-usinadocs', 'usinadocs', 'Usina Docs')");
        $database->exec("INSERT OR IGNORE INTO languages (code, name, native_name) VALUES ('pt', 'Portuguese', 'Português'), ('en', 'English', 'English'), ('es', 'Spanish', 'Español')");
        $database->exec("INSERT OR IGNORE INTO pages (id, site_id, created_at, updated_at) VALUES ('page-welcome', 'site-usinadocs', '$now', '$now')");
        $database->exec("INSERT OR IGNORE INTO page_revisions (id, page_id, language_code, number, status, title, summary, created_at, published_at) VALUES ('revision-welcome-pt', 'page-welcome', 'pt', 1, 'published', 'Bem-vindo ao Usina Docs', 'Uma base leve para documentação e aprendizado.', '$now', '$now')");
        $database->exec("INSERT OR IGNORE INTO page_localizations (page_id, language_code, slug, published_revision_id) VALUES ('page-welcome', 'pt', 'bem-vindo', 'revision-welcome-pt')");

        $text = json_encode(['title' => 'Conhecimento reutilizável', 'body' => 'Uma mesma explicação pode atender à consulta, à revisão e a uma aula, sem duplicação editorial.'], JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);
        $code = json_encode(['language' => 'text', 'code' => 'Página → Revisão → Blocos → Traduções', 'caption' => 'Modelo editorial'], JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);
        $statement = $database->prepare('INSERT OR IGNORE INTO blocks (id, page_revision_id, type, position, data) VALUES (:id, :revision, :type, :position, :data)');
        foreach ([['block-welcome-text', 'text', 1, $text], ['block-welcome-code', 'code', 2, $code]] as [$id, $type, $position, $data]) {
            $statement->execute(['id' => $id, 'revision' => 'revision-welcome-pt', 'type' => $type, 'position' => $position, 'data' => $data]);
        }
    }
}
