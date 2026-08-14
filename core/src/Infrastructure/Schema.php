<?php

declare(strict_types=1);

namespace UsinaDocs\Core\Infrastructure;

use PDO;

final class Schema
{
    public static function migrate(PDO $database, string $projectRoot): void
    {
        foreach (glob($projectRoot.'/database/migrations/*.sql') ?: [] as $migration) {
            $database->exec((string) file_get_contents($migration));
        }
    }

    public static function seed(PDO $database): void
    {
        $now = gmdate('c');
        $database->exec("INSERT OR IGNORE INTO sites (id, slug, name) VALUES ('site-usinadocs', 'usinadocs', 'Usina Docs')");
        $database->exec("INSERT OR IGNORE INTO languages (code, name, native_name) VALUES ('pt', 'Portuguese', 'Português'), ('en', 'English', 'English'), ('es', 'Spanish', 'Español')");
        $database->exec("INSERT OR IGNORE INTO pages (id, site_id, created_at, updated_at) VALUES ('page-welcome', 'site-usinadocs', '$now', '$now')");
        $database->exec("INSERT OR IGNORE INTO page_revisions (id, page_id, language_code, number, status, title, summary, created_at, published_at) VALUES ('revision-welcome-pt', 'page-welcome', 'pt', 1, 'published', 'Bem-vindo ao Usina Docs', 'Uma base leve para documentação e aprendizado.', '$now', '$now')");
        $database->exec("INSERT OR IGNORE INTO page_localizations (page_id, language_code, slug, published_revision_id) VALUES ('page-welcome', 'pt', 'bem-vindo', 'revision-welcome-pt')");
        $database->exec("INSERT OR IGNORE INTO page_metadata (page_id, content_type, updated_at) VALUES ('page-welcome', 'article', '$now')");
        $models = [
            ['model-reference', 'reference', 'Referência técnica', 'Sintaxe, explicação, exemplos e fontes para consulta rápida.', [['heading','Título de seção',0],['text','Texto',1],['code','Código',0],['table','Tabela',0],['diagram','Diagrama',0],['image','Imagem',0],['reference','Referência',1],['citation','Citação',0],['notice','Aviso',0]]],
            ['model-article', 'article', 'Artigo', 'Leitura editorial com contexto, ilustrações, citações e referências.', [['heading','Título de seção',0],['text','Texto',1],['table','Tabela',0],['diagram','Diagrama',0],['image','Imagem',0],['citation','Citação',0],['reference','Referência',1],['notice','Aviso',0]]],
            ['model-lesson', 'lesson', 'Aula', 'Conteúdo progressivo com explicação, exemplos e momentos de reforço.', [['heading','Etapa da aula',1],['text','Explicação',1],['code','Exemplo de código',0],['table','Tabela',0],['diagram','Diagrama',0],['quiz','Quiz',0],['image','Ilustração',0],['citation','Citação',0],['notice','Dica ou cuidado',0],['reference','Referência',0]]],
            ['model-class', 'class', 'Classe', 'Documentação de propriedades, métodos, exemplos e observações técnicas.', [['heading','Seção',1],['text','Descrição',1],['code','Exemplo',0],['notice','Observação',0],['reference','Referência',1]]],
            ['model-entry-point', 'entry_point', 'Ponto de entrada', 'Contexto de execução, parâmetros, retorno e cuidados de implementação.', [['heading','Seção',1],['text','Descrição',1],['code','Exemplo',0],['notice','Cuidado',0],['reference','Referência',1]]],
        ];
        $model = $database->prepare('INSERT OR IGNORE INTO editorial_models (id, content_type, label, description) VALUES (:id, :content_type, :label, :description)');
        $artifact = $database->prepare('INSERT OR IGNORE INTO editorial_model_artifacts (id, model_id, artifact_type, label, is_required, position) VALUES (:id, :model, :type, :label, :required, :position)');
        foreach ($models as [$id, $type, $label, $description, $artifacts]) { $model->execute(['id'=>$id,'content_type'=>$type,'label'=>$label,'description'=>$description]); foreach ($artifacts as $position=>[$artifactType,$artifactLabel,$required]) $artifact->execute(['id'=>$id.'-'.$artifactType,'model'=>$id,'type'=>$artifactType,'label'=>$artifactLabel,'required'=>$required,'position'=>$position+1]); }

        $text = json_encode(['title' => 'Conhecimento reutilizável', 'body' => 'Uma mesma explicação pode atender à consulta, à revisão e a uma aula, sem duplicação editorial.'], JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);
        $code = json_encode(['language' => 'text', 'code' => 'Página → Revisão → Blocos → Traduções', 'caption' => 'Modelo editorial'], JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);
        $statement = $database->prepare('INSERT OR IGNORE INTO blocks (id, page_revision_id, type, position, data) VALUES (:id, :revision, :type, :position, :data)');
        foreach ([['block-welcome-text', 'text', 1, $text], ['block-welcome-code', 'code', 2, $code]] as [$id, $type, $position, $data]) {
            $statement->execute(['id' => $id, 'revision' => 'revision-welcome-pt', 'type' => $type, 'position' => $position, 'data' => $data]);
        }

        $email = $_ENV['USINADOCS_ADMIN_EMAIL'] ?? 'admin@example.test';
        $password = $_ENV['USINADOCS_ADMIN_PASSWORD'] ?? 'change-this-password';
        $user = $database->prepare('INSERT OR IGNORE INTO users (id, email, name, password_hash, role, created_at) VALUES (:id, :email, :name, :password_hash, :role, :created_at)');
        $user->execute(['id' => 'user-admin', 'email' => $email, 'name' => 'Administrator', 'password_hash' => password_hash($password, PASSWORD_DEFAULT), 'role' => 'administrator', 'created_at' => $now]);
    }
}
