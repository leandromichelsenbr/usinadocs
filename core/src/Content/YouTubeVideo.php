<?php

declare(strict_types=1);

namespace UsinaDocs\Core\Content;

final class YouTubeVideo
{
    public static function id(mixed $source): ?string
    {
        if (!is_string($source)) {
            return null;
        }

        $source = trim($source);
        if (self::isId($source)) {
            return $source;
        }

        // Read only a video identifier from recognized HTTPS addresses. The renderer constructs the iframe URL itself.
        // Lê apenas o identificador do vídeo de endereços HTTPS reconhecidos. O renderizador constrói a URL do iframe.
        $url = parse_url($source);
        if ($url === false || strtolower($url['scheme'] ?? '') !== 'https'
            || isset($url['user']) || isset($url['pass']) || isset($url['port'])) {
            return null;
        }

        $host = strtolower($url['host'] ?? '');
        $path = $url['path'] ?? '';
        $id = null;
        if ($host === 'youtu.be') {
            $id = substr($path, 1);
        } elseif (in_array($host, ['youtube.com', 'www.youtube.com', 'm.youtube.com', 'www.youtube-nocookie.com'], true)) {
            if ($path === '/watch') {
                parse_str($url['query'] ?? '', $parameters);
                $id = $parameters['v'] ?? null;
            } elseif (str_starts_with($path, '/embed/')) {
                $id = substr($path, 7);
            }
        }

        return is_string($id) && self::isId($id) ? $id : null;
    }

    private static function isId(string $id): bool
    {
        return preg_match('/^[A-Za-z0-9_-]{11}$/D', $id) === 1;
    }
}
