<?php

declare(strict_types=1);

namespace UsinaDocs\Core\Presentation;

use InvalidArgumentException;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Slim\App;
use Slim\Views\Twig;
use UsinaDocs\Core\Authentication\Roles;
use UsinaDocs\Core\Content\ArticleSections;
use UsinaDocs\Core\Security\CsrfToken;

final class SectionRoutes
{
    public static function register(App $app, Twig $twig, ArticleSections $sections): void
    {
        $show = static function (ResponseInterface $response, ?array $section = null, ?string $error = null, bool $saved = false) use ($twig, $sections): ResponseInterface {
            return $twig->render($response, 'sections-admin.twig', [
                'sections' => $sections->all(), 'section' => $section, 'error' => $error,
                'saved' => $saved,
            ])->withHeader('Cache-Control', 'no-store');
        };
        $app->get('/admin/sections', static function (ServerRequestInterface $request, ResponseInterface $response) use ($show, $sections): ResponseInterface {
            if (!Roles::canEdit($_SESSION['user'] ?? null)) return $response->withStatus(403);
            $query = $request->getQueryParams();
            $slug = $query['section'] ?? null;
            $section = is_string($slug) ? $sections->find($slug) : null;
            if ($slug !== null && $section === null) return $response->withStatus(404);

            return $show($response, $section, saved: ($query['saved'] ?? null) === '1');
        });

        $save = static function (ServerRequestInterface $request, ResponseInterface $response, array $args) use ($show, $sections): ResponseInterface {
            $input = (array) $request->getParsedBody();
            $token = $input['csrf'] ?? null;
            // EN: Check the same editorial role on both routes and confirm the shared session token
            // (also enforced by the CsrfToken middleware).
            // PT: Confere o papel editorial nas duas rotas e o token de sessão compartilhado
            // (também exigido pelo middleware CsrfToken).
            if (!Roles::canEdit($_SESSION['user'] ?? null) || !CsrfToken::isValid($token)) {
                return $response->withStatus(403);
            }
            $slug = $args['slug'] ?? null;
            if ($slug !== null && $sections->find($slug) === null) return $response->withStatus(404);
            try {
                $saved = $sections->save($slug, $input);
            } catch (InvalidArgumentException $error) {
                $draft = array_filter(array_intersect_key($input, array_flip(['slug', 'title_pt', 'title_en', 'title_es', 'blocked'])), 'is_string');
                $draft['editing_slug'] = $slug;

                return $show($response->withStatus(422), $draft, $error->getMessage());
            }

            return $response->withStatus(303)->withHeader('Location', '/admin/sections?section='.rawurlencode($saved).'&saved=1');
        };
        $app->post('/admin/sections', $save);
        $app->post('/admin/sections/{slug:[a-z0-9-]+}', $save);
    }
}
