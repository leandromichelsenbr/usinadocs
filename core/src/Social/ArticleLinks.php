<?php

declare(strict_types=1);

namespace UsinaDocs\Core\Social;

final class ArticleLinks
{
    public static function origin(string $url): ?string
    {
        // Accept only an HTTP origin. A query, credentials or path would corrupt the public permalink.
        // Aceita somente uma origem HTTP. Consulta, credenciais ou caminho corromperiam o endereço público.
        if (!preg_match('~\Ahttps?://(?:[a-z0-9.-]+|\[[0-9a-f:]+\])(?::[0-9]{1,5})?/?\z~i', $url)) {
            return null;
        }

        return rtrim($url, '/');
    }

    public static function url(string $origin, array $article): string
    {
        return $origin.'/'.$article['language_code'].'/p/'.rawurlencode($article['slug']);
    }

    public static function share(string $url, string $title): array
    {
        // Ordinary links defer contact with each network until the reader chooses to share.
        // Links comuns adiam o contato com cada rede até o leitor escolher compartilhar.
        return [
            'WhatsApp' => 'https://wa.me/?text='.rawurlencode($title."\n".$url),
            'LinkedIn' => 'https://www.linkedin.com/sharing/share-offsite/?url='.rawurlencode($url),
            'Facebook' => 'https://www.facebook.com/sharer/sharer.php?u='.rawurlencode($url),
        ];
    }
}
