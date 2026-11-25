<?php

declare(strict_types=1);

namespace UsinaDocs\Core\Authentication;

final class LoginReturn
{
    public static function articleComments(mixed $target): ?string
    {
        // Only accept a local article URL with the comments anchor as a sign-in destination.
        // Aceita apenas um endereço local de artigo com a âncora dos comentários como destino após o login.
        return is_string($target) && preg_match('~\A/(?:pt|en|es)/p/(?:[a-z0-9._\x7e-]|%[a-f0-9]{2})+#comments\z~i', $target)
            ? $target
            : null;
    }
}
