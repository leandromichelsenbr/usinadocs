<?php

declare(strict_types=1);

namespace UsinaDocs\Core\Presentation;

use InvalidArgumentException;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Slim\App;
use Slim\Views\Twig;
use Twig\TwigFunction;
use UsinaDocs\Core\Authentication\Roles;
use UsinaDocs\Core\Content\LegalDocuments;

final class LegalDocumentRoutes
{
    public static function register(App $app, Twig $twig, LegalDocuments $documents): void
    {
        // Resolve the footer only when a reader template asks for it, avoiding extra queries for assets and admin screens.
        // Consulta o rodapé apenas nos templates públicos que o usam, evitando consultas extras para arquivos e telas administrativas.
        $twig->getEnvironment()->addFunction(new TwigFunction('legal_links', $documents->links(...)));

        // Legal publication is an installation setting, restricted to administrators in either sign-in mode.
        // A publicação legal é uma configuração da instalação, restrita a administradores em ambos os modos de login.
        $screen = static function (
            ResponseInterface $response,
            string $slug = '',
            ?array $editions = null,
            bool $creating = false,
            ?string $error = null,
            ?string $saved = null,
        ) use ($twig, $documents): ResponseInterface {
            $_SESSION['legal_csrf'] ??= bin2hex(random_bytes(32));
            return $twig->render($response, 'legal-admin.twig', [
                'documents' => $documents->listing(), 'slug' => $slug, 'editions' => $editions,
                'creating' => $creating, 'languages' => LegalDocuments::LANGUAGES,
                'csrf' => $_SESSION['legal_csrf'], 'error' => $error, 'saved' => $saved,
            ])->withHeader('Cache-Control', 'no-store');
        };

        $app->get('/admin/legal', static function ($request, $response) use ($screen) {
            return self::canManage() ? $screen($response) : $response->withStatus(403);
        });
        $app->get('/admin/legal/new', static function ($request, $response) use ($screen) {
            return self::canManage() ? $screen($response, editions: [], creating: true) : $response->withStatus(403);
        });
        $app->get('/admin/legal/{slug:[a-z0-9-]+}', static function ($request, $response, array $args) use ($screen, $documents) {
            if (!self::canManage()) return $response->withStatus(403);

            $slug = $args['slug'];
            $editions = $documents->draft($slug);
            if ($editions === null) return $response->withStatus(404);
            $saved = $request->getQueryParams()['saved'] ?? null;
            return $screen($response, $slug, $editions, saved: in_array($saved, ['draft', 'published'], true) ? $saved : null);
        });

        $save = static function (ServerRequestInterface $request, ResponseInterface $response, array $args) use ($documents, $screen): ResponseInterface {
            $input = (array) $request->getParsedBody();
            $csrf = $input['csrf'] ?? null;

            // Check the session token before accepting any draft or publication, including on password installations.
            // Confere o token de sessão antes de aceitar rascunhos ou publicações, inclusive em instalações com senha.
            if (!self::canManage() || !is_string($csrf) || !isset($_SESSION['legal_csrf']) || !hash_equals($_SESSION['legal_csrf'], $csrf)) {
                return $response->withStatus(403);
            }

            $creating = !isset($args['slug']);
            $slug = $args['slug'] ?? (is_string($input['slug'] ?? null) ? trim($input['slug']) : '');
            if (!$creating && ($slug === 'new' || $documents->draft($slug) === null)) return $response->withStatus(404);

            // Only strings from the expected language fields go back into the form after a validation error.
            // Apenas textos dos campos de idioma esperados voltam ao formulário após um erro de validação.
            $editions = [];
            foreach (array_keys(LegalDocuments::LANGUAGES) as $locale) {
                $edition = $input['editions'][$locale] ?? [];
                $editions[$locale] = is_array($edition) ? array_filter($edition, 'is_string') : [];
            }
            $publish = ($input['action'] ?? '') === 'publish';

            try {
                $documents->save($slug, $editions, $publish, $creating);
            } catch (InvalidArgumentException $error) {
                return $screen($response->withStatus(422), $slug, $editions, $creating, $error->getMessage());
            }

            return $response->withStatus(303)->withHeader('Location', '/admin/legal/'.$slug.'?saved='.($publish ? 'published' : 'draft'));
        };
        $app->post('/admin/legal', $save);
        $app->post('/admin/legal/{slug:[a-z0-9-]+}', $save);
    }

    public static function readerResponse(
        ServerRequestInterface $request,
        ResponseInterface $response,
        Twig $twig,
        LegalDocuments $documents,
        string $locale,
        string $pathSlug,
    ): ?ResponseInterface {
        // Serve legal documents in the public page URL family, after checking regular editorial pages.
        // Serve os textos legais no padrão de URLs das páginas públicas, após consultar as páginas editoriais.
        if (!isset(LegalDocuments::LANGUAGES[$locale]) || !preg_match('/\Alegal-([a-z0-9-]+)\z/', $pathSlug, $matches)) {
            return null;
        }

        $slug = $matches[1];
        $document = $documents->published($slug, $locale);
        if ($document === null) return null;

        $preferences = $request->getAttribute(ReaderPreferences::class);
        if ($preferences->requestedLanguage !== null && $preferences->language !== $locale) {
            return $response->withStatus(302)->withHeader('Location', '/'.$preferences->language.'/p/legal-'.$slug);
        }

        return $twig->render($response, 'legal-document.twig', [
            'document' => $document, 'slug' => $slug, 'locale' => $locale, 'languages' => LegalDocuments::LANGUAGES,
        ])->withHeader('Cache-Control', 'no-cache');
    }

    private static function canManage(): bool
    {
        return Roles::canManageUsers($_SESSION['user'] ?? null);
    }
}
