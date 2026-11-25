<?php

declare(strict_types=1);

namespace UsinaDocs\Core\Presentation;

use InvalidArgumentException;
use PDO;
use UsinaDocs\Core\Media\MediaLibrary;

final class SiteAppearance
{
    private const SITE_ID = 'site-usinadocs';
    private const ASSET_FIELDS = [
        'favicon_id' => 'Ícone do navegador',
        'logo_light_id' => 'Logotipo do tema claro',
        'logo_dark_id' => 'Logotipo do tema escuro',
    ];

    public function __construct(private readonly PDO $database, private readonly MediaLibrary $media)
    {
    }

    /** @return array<string, string|null> */
    public function current(): array
    {
        $statement = $this->database->prepare('SELECT display_name, favicon_id, logo_light_id, logo_dark_id, accent_color FROM site_appearance WHERE site_id = :site_id');
        $statement->execute(['site_id' => self::SITE_ID]);

        // An installation can use its original identity until an administrator saves a configuration.
        // A instalação pode usar a identidade original até um administrador salvar uma configuração.
        return $statement->fetch() ?: [
            'display_name' => '',
            'favicon_id' => null,
            'logo_light_id' => null,
            'logo_dark_id' => null,
            'accent_color' => '',
        ];
    }

    public function save(array $input): void
    {
        $name = self::textField($input, 'display_name');
        $color = self::textField($input, 'accent_color');
        if (mb_strlen($name) > 100) {
            throw new InvalidArgumentException('O nome do site deve ter no máximo 100 caracteres.');
        }
        if ($color !== '' && preg_match('/^#[a-fA-F0-9]{6}$/D', $color) !== 1) {
            throw new InvalidArgumentException('Use uma cor hexadecimal com seis dígitos, como #087C38.');
        }

        $settings = ['site_id' => self::SITE_ID, 'display_name' => $name, 'accent_color' => strtolower($color)];
        foreach (self::ASSET_FIELDS as $field => $label) {
            $id = self::textField($input, $field);
            // Accept only images already served by the installation's media library.
            // Aceita apenas imagens já servidas pela biblioteca de mídia da instalação.
            if ($id !== '') {
                $image = $this->media->find($id);
                if ($image === null || !is_file($this->media->path($image))) {
                    throw new InvalidArgumentException($label.' deve ser uma imagem disponível na biblioteca de mídia.');
                }
            }
            $settings[$field] = $id === '' ? null : $id;
        }

        // Validate every field before a single atomic write, so a bad selection cannot save half the identity.
        // Valida todos os campos antes de uma gravação atômica, para que uma seleção inválida não salve só parte da identidade.
        $statement = $this->database->prepare(
            'INSERT INTO site_appearance (site_id, display_name, favicon_id, logo_light_id, logo_dark_id, accent_color)
             VALUES (:site_id, :display_name, :favicon_id, :logo_light_id, :logo_dark_id, :accent_color)
             ON CONFLICT (site_id) DO UPDATE SET
                display_name = excluded.display_name,
                favicon_id = excluded.favicon_id,
                logo_light_id = excluded.logo_light_id,
                logo_dark_id = excluded.logo_dark_id,
                accent_color = excluded.accent_color'
        );
        $statement->execute($settings);
    }

    private static function textField(array $input, string $field): string
    {
        $value = $input[$field] ?? '';
        if (!is_string($value)) {
            throw new InvalidArgumentException('Há um campo inválido nos ajustes de interface.');
        }

        return trim($value);
    }
}
