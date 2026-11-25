<?php

declare(strict_types=1);

namespace UsinaDocs\Core\Presentation;

use InvalidArgumentException;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Slim\App;
use Slim\Views\Twig;
use UsinaDocs\Core\Authentication\Roles;
use UsinaDocs\Core\Content\AuthorDirectory;

final class AuthorRoutes
{
    public static function register(App $app, Twig $twig, AuthorDirectory $authors): void
    {
        $localeRoute = '/{locale:pt|en|es}/p';
        $languageRedirect = static function (ServerRequestInterface $request, ResponseInterface $response, string $locale, string $path): ?ResponseInterface {
            // Apply the shared language selector to the profile's localized URL.
            // Aplica o seletor de idioma compartilhado ao endereço localizado do perfil.
            $preferences = $request->getAttribute(ReaderPreferences::class);
            return $preferences->requestedLanguage !== null && $preferences->language !== $locale
                ? $response->withHeader('Location', '/'.$preferences->language.'/p/'.$path)->withStatus(302)
                : null;
        };

        // These reader URLs use the same localized path family as the rest of Usina Docs.
        // Esses endereços públicos usam a mesma família de caminhos localizados do Usina Docs.
        $app->get($localeRoute.'/autores', static function ($request, $response, array $args) use ($twig, $authors, $languageRedirect) {
            $redirect = $languageRedirect($request, $response, $args['locale'], 'autores');
            if ($redirect !== null) return $redirect;

            return $twig->render($response, 'author-profile.twig', [
                'locale'=>$args['locale'], 'directory'=>$authors->all(), 'profile'=>null,
            ]);
        });
        $app->get($localeRoute.'/autor-{slug:[a-z0-9-]+}', static function ($request, $response, array $args) use ($twig, $authors, $languageRedirect) {
            $profile = $authors->find($args['slug']);
            if ($profile === null) return $response->withStatus(404);
            $locale = $args['locale'];
            $redirect = $languageRedirect($request, $response, $locale, 'autor-'.$profile['slug']);
            if ($redirect !== null) return $redirect;

            $language = $profile['bio_'.$locale] === '' ? 'pt' : $locale;

            return $twig->render($response, 'author-profile.twig', [
                'locale'=>$locale, 'profile'=>$profile,
                'biography'=>$profile['bio_'.$language], 'biography_language'=>$language,
            ]);
        });

        $admin = static function (ResponseInterface $response, ?array $profile = null, ?string $error = null, bool $saved = false) use ($twig, $authors): ResponseInterface {
            $_SESSION['author_csrf'] ??= bin2hex(random_bytes(32));
            return $twig->render($response, 'authors-admin.twig', [
                'directory'=>$authors->all(), 'profile'=>$profile, 'error'=>$error,
                'saved'=>$saved, 'csrf'=>$_SESSION['author_csrf'],
            ])->withHeader('Cache-Control', 'no-store');
        };
        $app->get('/admin/authors', static function ($request, $response) use ($admin) {
            return self::mayEdit() ? $admin($response) : $response->withStatus(403);
        });
        $app->get('/admin/authors/{slug}', static function ($request, $response, array $args) use ($admin, $authors) {
            if (!self::mayEdit()) return $response->withStatus(403);
            $profile = $authors->find($args['slug']);
            return $profile === null ? $response->withStatus(404)
                : $admin($response, $profile, saved: ($request->getQueryParams()['saved'] ?? '') === '1');
        });
        $save = static function ($request, $response, array $args) use ($admin, $authors) {
            if (!self::validPost($request)) return $response->withStatus(403);
            $slug = $args['slug'] ?? null;
            if ($slug !== null && $authors->find($slug) === null) return $response->withStatus(404);
            $input = (array) $request->getParsedBody();
            try {
                $slug = $authors->save($slug, $input);
            } catch (InvalidArgumentException $error) {
                $profile = array_filter(array_intersect_key($input, array_flip(['slug','name','bio_pt','bio_en','bio_es'])), 'is_string');
                if ($slug !== null) $profile['editing_slug'] = $slug;
                return $admin($response->withStatus(422), $profile, $error->getMessage());
            }
            return $response->withHeader('Location', '/admin/authors/'.$slug.'?saved=1')->withStatus(303);
        };
        $app->post('/admin/authors', $save);
        $app->post('/admin/authors/{slug}', $save);

        $byline = static function (ResponseInterface $response, string $id, ?string $error = null) use ($twig, $authors): ResponseInterface {
            $_SESSION['author_csrf'] ??= bin2hex(random_bytes(32));
            return $twig->render($response, 'author-byline.twig', [
                'page_id'=>$id, 'directory'=>$authors->all(), 'selected'=>array_column($authors->byline($id), 'slug'),
                'csrf'=>$_SESSION['author_csrf'], 'error'=>$error,
            ])->withHeader('Cache-Control', 'no-store');
        };
        $app->get('/admin/pages/{id}/authors', static function ($request, $response, array $args) use ($byline, $authors) {
            if (!self::mayEdit()) return $response->withStatus(403);
            return $authors->isArticle($args['id']) ? $byline($response, $args['id']) : $response->withStatus(404);
        });
        $app->post('/admin/pages/{id}/authors', static function ($request, $response, array $args) use ($byline, $authors) {
            if (!self::validPost($request)) return $response->withStatus(403);
            if (!$authors->isArticle($args['id'])) return $response->withStatus(404);
            $slugs = ((array) $request->getParsedBody())['authors'] ?? [];
            try {
                if (!is_array($slugs)) throw new InvalidArgumentException('Selecione autores cadastrados.');
                $authors->assign($args['id'], $slugs);
            } catch (InvalidArgumentException $error) {
                return $byline($response->withStatus(422), $args['id'], $error->getMessage());
            }
            return $response->withHeader('Location', '/admin/pages/'.$args['id'].'/release')->withStatus(303);
        });
    }

    private static function mayEdit(): bool
    {
        return Roles::canEdit($_SESSION['user'] ?? null);
    }

    private static function validPost(ServerRequestInterface $request): bool
    {
        // A session token also protects the forms on password-based installations.
        // Um token de sessão protege os formulários também nas instalações com senha.
        $token = ((array) $request->getParsedBody())['csrf'] ?? null;
        return self::mayEdit() && is_string($token) && isset($_SESSION['author_csrf'])
            && hash_equals($_SESSION['author_csrf'], $token);
    }
}
