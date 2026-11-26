<?php

declare(strict_types=1);

namespace UsinaDocs\Core\Presentation;

use Slim\App;
use Slim\Views\Twig;
use UsinaDocs\Core\Authentication\Roles;
use UsinaDocs\Core\Content\PublicationBatch;
use UsinaDocs\Core\Social\SocialPublication;

final class SocialRoutes
{
    public static function register(App $app, Twig $twig, SocialPublication $social): void
    {
        // Only administrators manage the organization's external channels. Shared middleware checks CSRF on POST.
        // Somente administradores gerenciam os canais externos da organização. O middleware comum verifica CSRF no POST.
        $app->get('/admin/social', static function ($request, $response) use ($twig, $social) {
            if (!Roles::canManageUsers($_SESSION['user'] ?? null)) {
                return $response->withStatus(403);
            }

            return $twig->render($response, 'social-admin.twig', [
                'networks' => SocialPublication::NETWORKS,
                'languages' => PublicationBatch::LANGUAGES,
                'channels' => $social->channels(),
                'deliveries' => $social->recent(),
                'saved' => ($request->getQueryParams()['saved'] ?? null) === '1',
            ])->withHeader('Cache-Control', 'no-store');
        });

        $app->post('/admin/social/{network}', static function ($request, $response, array $args) use ($social) {
            if (!Roles::canManageUsers($_SESSION['user'] ?? null)) {
                return $response->withStatus(403);
            }

            $input = (array) $request->getParsedBody();
            $network = $args['network'];
            $language = $input['language'] ?? null;
            if (!isset(SocialPublication::NETWORKS[$network]) || !is_string($language) || !isset(PublicationBatch::LANGUAGES[$language])) {
                return $response->withStatus(400);
            }

            $social->saveChannel($network, $language, ($input['enabled'] ?? null) === '1');
            return $response->withStatus(303)->withHeader('Location', '/admin/social?saved=1');
        });

        $app->post('/admin/social/{network}/retry/{page}', static function ($request, $response, array $args) use ($social) {
            if (!Roles::canManageUsers($_SESSION['user'] ?? null)) {
                return $response->withStatus(403);
            }

            $social->retryRejected($args['page'], $args['network']);
            return $response->withStatus(303)->withHeader('Location', '/admin/social');
        });
    }
}
