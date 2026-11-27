<?php

declare(strict_types=1);

namespace UsinaDocs\Core\Presentation;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Slim\App;
use Slim\Views\Twig;
use UsinaDocs\Core\Authentication\Roles;
use UsinaDocs\Core\Content\Sitemap;

final class SitemapRoutes
{
    public static function register(App $app, Twig $twig, Sitemap $sitemap, string $origin): void
    {
        // Use the configured canonical origin instead of reflecting the HTTP Host header into the index.
        // Usa a origem canônica configurada em vez de refletir o cabeçalho Host no índice.
        $app->map(['GET', 'HEAD'], '/sitemap.xml', static function (ServerRequestInterface $request, ResponseInterface $response) use ($sitemap, $origin): ResponseInterface {
            if ($request->getMethod() === 'GET') {
                $response->getBody()->write($sitemap->xml($origin));
            }
            return $response->withHeader('Content-Type', 'application/xml; charset=utf-8')
                ->withHeader('Cache-Control', 'no-store');
        });

        $app->map(['GET', 'HEAD'], '/robots.txt', static function (ServerRequestInterface $request, ResponseInterface $response) use ($origin): ResponseInterface {
            if ($request->getMethod() === 'GET') {
                $response->getBody()->write("User-agent: *\nDisallow: /admin\nSitemap: ".$origin."/sitemap.xml\n");
            }
            return $response->withHeader('Content-Type', 'text/plain; charset=utf-8');
        });

        // The administrator changes only how the sitemap is refreshed. Shared middleware validates the session token.
        // O administrador muda apenas como o mapa é atualizado. O middleware comum valida o token de sessão.
        $app->get('/admin/sitemap', static function ($request, $response) use ($twig, $sitemap) {
            if (!Roles::canManageUsers($_SESSION['user'] ?? null)) {
                return $response->withStatus(403);
            }
            return $twig->render($response, 'sitemap-admin.twig', [
                'automatic' => (bool) $sitemap->settings()['automatic'],
                'saved' => ($request->getQueryParams()['saved'] ?? null) === '1',
            ])->withHeader('Cache-Control', 'no-store');
        });
        $app->post('/admin/sitemap', static function ($request, $response) use ($sitemap, $origin) {
            if (!Roles::canManageUsers($_SESSION['user'] ?? null)) {
                return $response->withStatus(403);
            }
            $mode = ((array) $request->getParsedBody())['mode'] ?? null;
            if (!in_array($mode, ['automatic', 'manual'], true)) {
                return $response->withStatus(400);
            }
            $sitemap->save($origin, $mode === 'automatic');
            return $response->withStatus(303)->withHeader('Location', '/admin/sitemap?saved=1');
        });
    }
}
