<?php

declare(strict_types=1);

namespace UsinaDocs\Core\Content;

use InvalidArgumentException;
use PDO;
use Throwable;

final class AuthorDirectory
{
    public function __construct(private readonly PDO $db)
    {
    }

    public function all(): array
    {
        return $this->db->query('SELECT slug, name FROM authors ORDER BY name COLLATE NOCASE, slug')->fetchAll();
    }

    public function find(string $slug): ?array
    {
        $query = $this->db->prepare('SELECT slug, name, bio_pt, bio_en, bio_es FROM authors WHERE slug=:slug');
        $query->execute(['slug'=>$slug]);

        return $query->fetch() ?: null;
    }

    public function save(?string $existingSlug, array $input): string
    {
        $slug = $existingSlug ?? self::field($input, 'slug', 64);
        $name = self::field($input, 'name', 120);
        $pt = self::field($input, 'bio_pt', 8000);
        $en = self::field($input, 'bio_en', 8000);
        $es = self::field($input, 'bio_es', 8000);
        if (!preg_match('/^[a-z0-9]+(?:-[a-z0-9]+)*$/D', $slug) || $name === '' || $pt === '') {
            throw new InvalidArgumentException('Informe o nome, um endereço com letras minúsculas, números e hífens, e a biografia em português.');
        }

        // Preserve a profile's URL when the name or biography changes.
        // Preserva o endereço do perfil quando o nome ou a biografia muda.
        $sql = $existingSlug === null
            ? 'INSERT INTO authors (slug,name,bio_pt,bio_en,bio_es) VALUES (:slug,:name,:pt,:en,:es) ON CONFLICT(slug) DO NOTHING'
            : 'UPDATE authors SET name=:name,bio_pt=:pt,bio_en=:en,bio_es=:es WHERE slug=:slug';
        $query = $this->db->prepare($sql);
        $query->execute(['slug'=>$slug,'name'=>$name,'pt'=>$pt,'en'=>$en,'es'=>$es]);
        if ($existingSlug === null && $query->rowCount() !== 1) {
            throw new InvalidArgumentException('Já existe um autor com esse endereço.');
        }

        return $slug;
    }

    public function isArticle(string $pageId): bool
    {
        $query = $this->db->prepare("SELECT 1 FROM page_metadata WHERE page_id=:page AND content_type='article'");
        $query->execute(['page'=>$pageId]);

        return (bool) $query->fetchColumn();
    }

    public function byline(string $pageId): array
    {
        $query = $this->db->prepare(
            'SELECT a.slug, a.name FROM page_authors pa JOIN authors a ON a.slug=pa.author_slug
             WHERE pa.page_id=:page ORDER BY pa.position'
        );
        $query->execute(['page'=>$pageId]);

        return $query->fetchAll();
    }

    public function assign(string $pageId, array $slugs): void
    {
        foreach ($slugs as $slug) {
            if (!is_string($slug)) throw new InvalidArgumentException('Selecione autores cadastrados.');
        }
        $slugs = array_values(array_unique($slugs));

        // Replace the ordered byline as one transaction. An invalid author leaves it intact.
        // Substitui a assinatura ordenada em uma transação. Um autor inválido preserva a assinatura anterior.
        $this->db->beginTransaction();
        try {
            $clear = $this->db->prepare('DELETE FROM page_authors WHERE page_id=:page');
            $clear->execute(['page'=>$pageId]);
            if (!$this->isArticle($pageId)) throw new InvalidArgumentException('A assinatura está disponível para artigos.');
            $add = $this->db->prepare('INSERT INTO page_authors (page_id,author_slug,position) VALUES (:page,:author,:position)');
            foreach ($slugs as $position => $slug) {
                if ($this->find($slug) === null) throw new InvalidArgumentException('Selecione autores cadastrados.');
                $add->execute(['page'=>$pageId,'author'=>$slug,'position'=>$position]);
            }
            $this->db->commit();
        } catch (Throwable $error) {
            $this->db->rollBack();
            throw $error;
        }
    }

    private static function field(array $input, string $key, int $limit): string
    {
        $value = $input[$key] ?? '';
        if (!is_string($value) || mb_strlen($value) > $limit) {
            throw new InvalidArgumentException('Confira o tamanho e o formato dos campos do autor.');
        }

        return trim($value);
    }
}
