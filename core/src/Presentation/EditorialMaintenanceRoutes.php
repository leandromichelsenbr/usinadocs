<?php

declare(strict_types=1);

namespace UsinaDocs\Core\Presentation;

use DomainException;
use Psr\Http\Message\ServerRequestInterface;
use Slim\App;
use Slim\Views\Twig;
use UsinaDocs\Core\Authentication\Roles;
use UsinaDocs\Core\Content\EditorialMaintenance;
use UsinaDocs\Core\Content\EditorialService;
use UsinaDocs\Core\Security\CsrfToken;

final class EditorialMaintenanceRoutes
{
    public static function register(App $app, Twig $twig, EditorialService $editorial, EditorialMaintenance $maintenance): void
    {
        $twig->getEnvironment()->addGlobal('editorial_labels', require dirname(__DIR__, 2).'/config/editorial-labels.php');
        $twig->getEnvironment()->addGlobal('editor_form_labels', require dirname(__DIR__, 2).'/config/editor-form-labels.php');
        $page = '/admin/pages/{id}';
        $language = '{language:pt|en|es}';
        $history = static fn (array $args): string => '/admin/pages/'.$args['id'].'/history/'.$args['language'];

        $app->get($page.'/history/'.$language, static function ($request, $response, array $args) use ($twig, $editorial) {
            if (!Roles::canEdit($_SESSION['user'] ?? null)) return $response->withHeader('Location', '/login')->withStatus(302);
            $revisions = $editorial->revisionHistory($args['id'], $args['language']);
            if ($revisions === []) return $response->withStatus(404);
            $current = array_values(array_filter($revisions, static fn (array $revision): bool => $revision['is_current']));

            return $twig->render($response, 'page-history.twig', [
                'history' => $revisions, 'current' => $current[0] ?? null,
                'page_id' => $args['id'], 'language' => $args['language'],
                'show_archived' => ($request->getQueryParams()['archived'] ?? '') === '1',
            ])->withHeader('Cache-Control', 'no-store');
        });

        // Show an explicit confirmation before deleting a draft and bind the form to that exact revision.
        // Exibe uma confirmação antes da exclusão e vincula o formulário àquele rascunho específico.
        $discard = $page.'/revisions/'.$language.'/discard';
        $app->get($discard, static function ($request, $response, array $args) use ($twig, $editorial) {
            if (!Roles::canEdit($_SESSION['user'] ?? null)) return $response->withHeader('Location', '/login')->withStatus(302);
            foreach ($editorial->revisionHistory($args['id'], $args['language']) as $revision) {
                if ($revision['status'] === 'draft') {
                    return $twig->render($response, 'discard-page-draft.twig', [
                        'page_id' => $args['id'], 'language' => $args['language'],
                        'draft' => $revision,
                    ])->withHeader('Cache-Control', 'no-store');
                }
            }

            return $response->withStatus(404);
        });
        $app->post($discard, static function ($request, $response, array $args) use ($editorial) {
            if (!self::authorized($request)) return $response->withStatus(403);
            $revision = ((array) $request->getParsedBody())['revision'] ?? null;
            if (!is_string($revision) || $revision === '') return $response->withStatus(422);
            try {
                $editorial->discardDraft($args['id'], $args['language'], $revision);
            } catch (DomainException $error) {
                $response->getBody()->write($error->getMessage());
                return $response->withStatus(409)->withHeader('Content-Type', 'text/plain; charset=utf-8');
            }

            return $response->withHeader('Location', '/admin')->withStatus(303);
        });

        $app->post($page.'/revisions/'.$language.'/{revision}/archive', static function ($request, $response, array $args) use ($maintenance, $history) {
            if (!self::authorized($request)) return $response->withStatus(403);
            $choice = ((array) $request->getParsedBody())['archived'] ?? null;
            if (!in_array($choice, ['0', '1'], true)) return $response->withStatus(422);
            if (!$maintenance->setArchived($args['id'], $args['language'], $args['revision'], $choice === '1')) {
                return $response->withStatus(409);
            }

            $query = $choice === '0' ? '?archived=1' : '';
            return $response->withHeader('Location', $history($args).$query)->withStatus(303);
        });

        $app->post($page.'/visibility', static function ($request, $response, array $args) use ($maintenance) {
            if (!self::authorized($request)) return $response->withStatus(403);
            $input = (array) $request->getParsedBody();
            $choice = $input['hidden'] ?? null;
            if (!in_array($choice, ['0', '1'], true)) return $response->withStatus(422);
            if (!$maintenance->setHidden($args['id'], $choice === '1')) return $response->withStatus(422);

            $search = is_string($input['q'] ?? null) ? trim($input['q']) : '';
            return $response->withHeader('Location', '/admin'.($search === '' ? '' : '?q='.rawurlencode($search)))->withStatus(303);
        });
    }


    private static function authorized(ServerRequestInterface $request): bool
    {
        // EN: Shared session token, also enforced by the CsrfToken middleware for every POST.
        // PT: Token de sessão compartilhado, também exigido pelo middleware CsrfToken em todo POST.
        $token = ((array) $request->getParsedBody())[CsrfToken::FIELD] ?? null;
        return Roles::canEdit($_SESSION['user'] ?? null) && CsrfToken::isValid($token);
    }
}
