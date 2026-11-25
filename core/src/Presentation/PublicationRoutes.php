<?php

declare(strict_types=1);

namespace UsinaDocs\Core\Presentation;

use InvalidArgumentException;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use RuntimeException;
use Slim\App;
use Slim\Views\Twig;
use UsinaDocs\Core\Authentication\Roles;
use UsinaDocs\Core\Content\PageCovers;
use UsinaDocs\Core\Content\PageAlerts;
use UsinaDocs\Core\Content\PublicationBatch;
use UsinaDocs\Core\Content\PublishedPageRepository;
use UsinaDocs\Core\Media\MediaLibrary;

final class PublicationRoutes
{
    public static function register(
        App $app,
        Twig $twig,
        PublicationBatch $batch,
        PublishedPageRepository $pages,
        PageCovers $covers,
        MediaLibrary $media,
        PageAlerts $alerts,
    ): void {
        $display = static function (ResponseInterface $response, string $pageId, ?string $error = null) use ($twig, $batch, $media, $alerts): ResponseInterface {
            $release = $batch->preview($pageId);
            if ($release === null) {
                return $response->withStatus(404);
            }
            $_SESSION['release_csrf'] ??= bin2hex(random_bytes(32));

            return $twig->render($response, 'publication-release.twig', [
                'page_id' => $pageId,
                'release' => $release,
                'images' => $media->all(),
                'critical' => $alerts->isCritical($pageId),
                'csrf' => $_SESSION['release_csrf'],
                'error' => $error,
            ])->withHeader('Cache-Control', 'no-store');
        };

        // Draft markup is never served through a public article URL or cached for another reader.
        // O HTML de rascunho nunca é servido pelo endereço público da matéria nem guardado para outro leitor.
        $app->get('/admin/pages/{id}/preview/{language:pt|en|es}', static function ($request, $response, array $args) use ($pages, $twig) {
            if (!Roles::canEdit($_SESSION['user'] ?? null)) return $response->withStatus(403);
            $page = $pages->findForPreview($args['id'], $args['language']);
            if ($page === null) return $response->withStatus(404);

            return $twig->render($response, 'page.twig', [
                'page' => $page, 'can_edit' => false, 'editorial_preview' => true,
            ])->withHeader('Cache-Control', 'no-store')->withHeader('X-Robots-Tag', 'noindex')
                ->withHeader('X-Frame-Options', 'SAMEORIGIN');
        });

        $app->get('/admin/pages/{id}/release', static function ($request, $response, array $args) use ($display) {
            if (!Roles::canEdit($_SESSION['user'] ?? null)) return $response->withStatus(403);
            return $display($response, $args['id']);
        });

        $app->post('/admin/pages/{id}/release', static function ($request, $response, array $args) use ($batch, $display) {
            if (!self::authorizedPost($request)) return $response->withStatus(403);
            $snapshot = ((array) $request->getParsedBody())['snapshot'] ?? '';
            try {
                $batch->publish($args['id'], is_string($snapshot) ? $snapshot : '');
            } catch (RuntimeException $error) {
                if ($error instanceof \PDOException) throw $error;
                return $display($response->withStatus(409), $args['id'], $error->getMessage());
            }

            return $response->withHeader('Location', '/admin/pages/'.$args['id'].'/release')->withStatus(303);
        });

        // Treat the shared alert as an editorial setting, with the same role and token protection as the cover.
        // Trata o alerta compartilhado como opção editorial, com a mesma proteção de papel e token da capa.
        $app->post('/admin/pages/{id}/critical-alert', static function ($request, $response, array $args) use ($alerts, $batch, $display) {
            if (!self::authorizedPost($request)) return $response->withStatus(403);
            if ($batch->preview($args['id']) === null) return $response->withStatus(404);
            $critical = ((array) $request->getParsedBody())['critical'] ?? null;
            if (!in_array($critical, ['0', '1'], true)) return $response->withStatus(422);
            try {
                $alerts->setCritical($args['id'], $critical === '1');
            } catch (InvalidArgumentException $error) {
                return $display($response->withStatus(422), $args['id'], $error->getMessage());
            }

            return $response->withHeader('Location', '/admin/pages/'.$args['id'].'/release#alerta')->withStatus(303);
        });

        $app->post('/admin/pages/{id}/cover', static function ($request, $response, array $args) use ($batch, $covers, $display) {
            if (!self::authorizedPost($request)) return $response->withStatus(403);
            if ($batch->preview($args['id']) === null) return $response->withStatus(404);
            $mediaId = ((array) $request->getParsedBody())['cover'] ?? null;
            if (!is_string($mediaId)) return $response->withStatus(422);
            try {
                $covers->set($args['id'], $mediaId === '' ? null : $mediaId);
            } catch (InvalidArgumentException $error) {
                return $display($response->withStatus(422), $args['id'], $error->getMessage());
            }

            return $response->withHeader('Location', '/admin/pages/'.$args['id'].'/release#capa')->withStatus(303);
        });
    }

    private static function authorizedPost(ServerRequestInterface $request): bool
    {
        // Keep the form protected in both OAuth and password-based installations.
        // Protege o formulário em instalações com OAuth e com senha.
        $token = ((array) $request->getParsedBody())['csrf'] ?? null;
        return Roles::canEdit($_SESSION['user'] ?? null) && is_string($token)
            && isset($_SESSION['release_csrf']) && hash_equals($_SESSION['release_csrf'], $token);
    }
}
