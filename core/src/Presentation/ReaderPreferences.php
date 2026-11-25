<?php

declare(strict_types=1);

namespace UsinaDocs\Core\Presentation;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Slim\Views\Twig;

final class ReaderPreferences
{
    private const THEMES = ['auto', 'light', 'dark'];
    private const LANGUAGES = ['pt' => 'Português', 'en' => 'English', 'es' => 'Español'];
    private const LANGUAGE_TAGS = ['pt' => 'pt-BR', 'en' => 'en', 'es' => 'es'];
    private const TRANSLATIONS = [
        'pt' => [
            'language' => 'Idioma', 'theme' => 'Tema', 'auto' => 'Automático',
            'light' => 'Claro', 'dark' => 'Escuro', 'apply' => 'Aplicar',
            'home' => 'Início', 'login' => 'Entrar', 'account' => 'Minha conta', 'sign_out' => 'Sair',
            'panel' => 'Painel editorial', 'edit' => 'Editar', 'provider' => 'Entrar com',
            'login_error' => 'Não foi possível concluir o login. Tente novamente.',
            'login_unavailable' => 'O login está temporariamente indisponível.',
            'available_languages' => 'Idiomas disponíveis', 'revision' => 'Revisão',
            'translation_unavailable' => 'A tradução para o idioma selecionado ainda não está publicada.',
            'article' => 'Artigo', 'reference' => 'Referência técnica', 'lesson' => 'Aula',
            'class' => 'Classe', 'entry_point' => 'Ponto de entrada', 'source' => 'Fonte',
            'by_author' => 'Por', 'authors' => 'Autores', 'no_authors' => 'Ainda não há biografias cadastradas.',
            'publication_details' => 'Dados da publicação', 'first_published' => 'Publicado em', 'last_updated' => 'Atualizado em',
            'author_bio_fallback' => 'A biografia está disponível em português.',
            'email' => 'E-mail', 'password' => 'Senha', 'login_prompt' => 'Entre para administrar o conteúdo.',
            'reference_label' => 'Referência', 'close' => 'Fechar', 'load_image' => 'Carregar imagem',
            'image_alt' => 'Imagem de apoio', 'full_image' => 'Imagem em tamanho original',
            'video' => 'Vídeo do YouTube', 'watch_video' => 'Assistir no YouTube',
            'review' => 'Revisar este assunto', 'correct' => 'Resposta correta.',
            'incorrect' => 'Ainda não. Veja a orientação de revisão abaixo.',
        ],
        'en' => [
            'language' => 'Language', 'theme' => 'Theme', 'auto' => 'Automatic',
            'light' => 'Light', 'dark' => 'Dark', 'apply' => 'Apply',
            'home' => 'Home', 'login' => 'Sign in', 'account' => 'My account', 'sign_out' => 'Sign out',
            'panel' => 'Editorial dashboard', 'edit' => 'Edit', 'provider' => 'Sign in with',
            'login_error' => 'Could not sign in. Please try again.',
            'login_unavailable' => 'Sign-in is temporarily unavailable.',
            'available_languages' => 'Available languages', 'revision' => 'Revision',
            'translation_unavailable' => 'This article has not been published in the selected language yet.',
            'article' => 'Article', 'reference' => 'Technical reference', 'lesson' => 'Lesson',
            'class' => 'Class', 'entry_point' => 'Entry point', 'source' => 'Source',
            'by_author' => 'By', 'authors' => 'Authors', 'no_authors' => 'No author biographies yet.',
            'publication_details' => 'Publication details', 'first_published' => 'Published', 'last_updated' => 'Updated',
            'author_bio_fallback' => 'This biography is available in Portuguese.',
            'email' => 'Email', 'password' => 'Password', 'login_prompt' => 'Sign in to manage content.',
            'reference_label' => 'Reference', 'close' => 'Close', 'load_image' => 'Load image',
            'image_alt' => 'Supporting image', 'full_image' => 'Full-size image',
            'video' => 'YouTube video', 'watch_video' => 'Watch on YouTube',
            'review' => 'Review this topic', 'correct' => 'Correct answer.',
            'incorrect' => 'Try again after reading the guidance below.',
        ],
        'es' => [
            'language' => 'Idioma', 'theme' => 'Tema', 'auto' => 'Automático',
            'light' => 'Claro', 'dark' => 'Oscuro', 'apply' => 'Aplicar',
            'home' => 'Inicio', 'login' => 'Iniciar sesión', 'account' => 'Mi cuenta', 'sign_out' => 'Cerrar sesión',
            'panel' => 'Panel editorial', 'edit' => 'Editar', 'provider' => 'Iniciar sesión con',
            'login_error' => 'No fue posible iniciar sesión. Inténtalo de nuevo.',
            'login_unavailable' => 'El inicio de sesión no está disponible temporalmente.',
            'available_languages' => 'Idiomas disponibles', 'revision' => 'Revisión',
            'translation_unavailable' => 'El artículo todavía no se ha publicado en el idioma seleccionado.',
            'article' => 'Artículo', 'reference' => 'Referencia técnica', 'lesson' => 'Lección',
            'class' => 'Clase', 'entry_point' => 'Punto de entrada', 'source' => 'Fuente',
            'by_author' => 'Por', 'authors' => 'Autores', 'no_authors' => 'Todavía no hay biografías.',
            'publication_details' => 'Datos de publicación', 'first_published' => 'Publicado el', 'last_updated' => 'Actualizado el',
            'author_bio_fallback' => 'La biografía está disponible en portugués.',
            'email' => 'Correo electrónico', 'password' => 'Contraseña', 'login_prompt' => 'Inicia sesión para administrar el contenido.',
            'reference_label' => 'Referencia', 'close' => 'Cerrar', 'load_image' => 'Cargar imagen',
            'image_alt' => 'Imagen de apoyo', 'full_image' => 'Imagen de tamaño original',
            'video' => 'Vídeo de YouTube', 'watch_video' => 'Ver en YouTube',
            'review' => 'Revisar este tema', 'correct' => 'Respuesta correcta.',
            'incorrect' => 'Inténtalo de nuevo después de leer la orientación.',
        ],
    ];

    private function __construct(
        public readonly string $languageMode,
        public readonly string $language,
        public readonly string $theme,
        public readonly ?string $requestedLanguage,
        private readonly ?string $requestedTheme,
    ) {
    }

    public static function fromRequest(ServerRequestInterface $request): self
    {
        $query = $request->getQueryParams();
        $cookies = $request->getCookieParams();
        $languages = ['auto', ...array_keys(self::LANGUAGES)];
        $requestedLanguage = self::allowed($query['lang'] ?? null, $languages);
        $requestedTheme = self::allowed($query['theme'] ?? null, self::THEMES);
        $mode = $requestedLanguage ?? self::allowed($cookies['usinadocs-lang'] ?? null, $languages) ?? 'auto';
        $language = $mode === 'auto' ? self::detectLanguage($request->getHeaderLine('Accept-Language')) : $mode;
        $theme = $requestedTheme ?? self::allowed($cookies['usinadocs-theme'] ?? null, self::THEMES) ?? 'auto';

        return new self($mode, $language, $theme, $requestedLanguage, $requestedTheme);
    }

    public static function middleware(Twig $twig): callable
    {
        return static function (ServerRequestInterface $request, RequestHandlerInterface $next) use ($twig): ResponseInterface {
            $preferences = self::fromRequest($request);
            $twig->getEnvironment()->addGlobal('reader_preferences', [
                'language' => $preferences->language,
                'language_tag' => self::LANGUAGE_TAGS[$preferences->language],
                'language_mode' => $preferences->languageMode,
                'languages' => self::LANGUAGES,
                'theme' => $preferences->theme,
                'text' => self::TRANSLATIONS[$preferences->language],
            ]);

            $response = $next->handle($request->withAttribute(self::class, $preferences));
            if (!in_array($request->getMethod(), ['GET', 'HEAD'], true)) {
                return $response;
            }

            // Public pages and their redirects can differ with saved preferences
            // or the browser language. Never share those variants across readers.
            if (preg_match('~^/(?:$|login$|[a-z-]+/p/)~i', $request->getUri()->getPath())) {
                $response = $response->withAddedHeader('Vary', 'Accept-Language, Cookie');
            }
            if ($request->getMethod() === 'HEAD') {
                return $response;
            }
            foreach (['usinadocs-lang' => $preferences->requestedLanguage, 'usinadocs-theme' => $preferences->requestedTheme] as $name => $value) {
                if ($value !== null) {
                    $secure = $request->getUri()->getScheme() === 'https' ? '; Secure' : '';
                    $response = $response->withAddedHeader('Set-Cookie', $name.'='.$value.'; Max-Age=31536000; Path=/; SameSite=Lax; HttpOnly'.$secure);
                }
            }

            return $response;
        };
    }

    private static function allowed(mixed $value, array $options): ?string
    {
        return is_string($value) && in_array($value, $options, true) ? $value : null;
    }

    private static function detectLanguage(string $header): string
    {
        $selected = 'pt';
        $bestQuality = 0.0;
        foreach (explode(',', strtolower($header)) as $item) {
            if (!preg_match('/^\s*([a-z]{2,3})(?:-[a-z0-9]+)*(?:\s*;\s*q=(0(?:\.\d{0,3})?|1(?:\.0{0,3})?))?\s*$/D', $item, $matches)) {
                continue;
            }
            $quality = (float) ($matches[2] ?? 1);
            if (isset(self::LANGUAGES[$matches[1]]) && $quality > $bestQuality) {
                $bestQuality = $quality;
                $selected = $matches[1];
            }
        }

        return $selected;
    }
}
