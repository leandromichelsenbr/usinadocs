<?php

declare(strict_types=1);

namespace UsinaDocs\Core\Authentication;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Slim\App;
use Slim\Views\Twig;
use Throwable;

final class OAuthRoutes
{
    public static function register(App $app, Twig $twig, OAuthClient $client, OAuthAccounts $accounts): void
    {
        $app->add(static function (ServerRequestInterface $request, RequestHandlerInterface $handler) use ($app, $twig, $client, $accounts): ResponseInterface {
            // Read privileges from storage on each request so a role change
            // also takes effect for people who already have a session.
            $user = isset($_SESSION['oauth_user_id']) ? $accounts->find($_SESSION['oauth_user_id']) : null;
            $_SESSION['user'] = $user;
            $twig->getEnvironment()->addGlobal('oauth_enabled', true);
            $twig->getEnvironment()->addGlobal('account', $user);
            $twig->getEnvironment()->addGlobal('can_manage_users', Roles::canManageUsers($user));
            $twig->getEnvironment()->addGlobal('can_access_editor', Roles::canEdit($user));

            $path = rawurldecode($request->getUri()->getPath());
            $editorial = $path === '/admin' || str_starts_with($path, '/admin/');
            if ($editorial && !Roles::canEdit($user)) {
                $response = $app->getResponseFactory()->createResponse($user === null ? 302 : 403);
                return $user === null ? $response->withHeader('Location', '/login') : $response;
            }

            // Browsers send Origin for the editorial POST forms. Requiring
            // the configured origin protects all existing forms against CSRF.
            if (($editorial || $path === '/logout') && $request->getMethod() === 'POST'
                && ($client->origin() === '' || $request->getHeaderLine('Origin') !== $client->origin())) {
                return $app->getResponseFactory()->createResponse(403);
            }

            return $handler->handle($request);
        });

        $app->get('/login', static function ($request, $response) use ($twig, $client) {
            return $twig->render($response, 'oauth-login.twig', [
                'providers' => $client->providers(),
                'next' => LoginReturn::articleComments($request->getQueryParams()['next'] ?? null),
                'failed' => ($request->getQueryParams()['error'] ?? '') === 'oauth',
            ])->withHeader('Cache-Control', 'no-store');
        });

        $app->get('/auth/{provider:google|microsoft}', static function ($request, $response, array $arguments) use ($client) {
            $provider = $arguments['provider'];
            if (!isset($client->providers()[$provider])) return $response->withStatus(404);

            $state = bin2hex(random_bytes(24));
            $verifier = bin2hex(random_bytes(32));
            // Bind the local comment destination to the same OAuth attempt as state and PKCE.
            // Vincula o destino local do comentário à mesma tentativa OAuth que contém o estado e o PKCE.
            $_SESSION['oauth_pending'][$provider] = [
                'state' => $state, 'verifier' => $verifier, 'expires' => time() + 600,
                'next' => LoginReturn::articleComments($request->getQueryParams()['next'] ?? null),
            ];

            return $response->withStatus(302)
                ->withHeader('Location', $client->authorizationUrl($provider, $state, $verifier))
                ->withHeader('Cache-Control', 'no-store');
        });

        $app->get('/auth/{provider:google|microsoft}/callback', static function ($request, $response, array $arguments) use ($client, $accounts) {
            $provider = $arguments['provider'];
            $query = $request->getQueryParams();
            $pending = $_SESSION['oauth_pending'][$provider] ?? null;
            if ($pending === null || $pending['expires'] < time() || !is_string($query['state'] ?? null)
                || !hash_equals($pending['state'], $query['state'])) {
                return $response->withStatus(403)->withHeader('Cache-Control', 'no-store');
            }
            unset($_SESSION['oauth_pending'][$provider]);
            $next = $pending['next'] ?? null;
            $retry = '/login?error=oauth'.($next === null ? '' : '&next='.rawurlencode($next));
            if (!is_string($query['code'] ?? null) || $query['code'] === '') {
                return $response->withStatus(302)->withHeader('Location', $retry);
            }

            try {
                $identity = $client->identity($provider, $query['code'], $pending['verifier']);
                if (!session_regenerate_id(true)) {
                    return $response->withStatus(500);
                }
                $user = $accounts->signIn($provider, $identity['subject'], $identity['name'], $identity['email'], $identity['verified']);
                // Carry anonymous view markers across sign-in while replacing the old authentication state.
                // Preserva as marcas anônimas de visualização no login e substitui o estado anterior de autenticação.
                $_SESSION = ['oauth_user_id' => $user['id'], 'article_views' => $_SESSION['article_views'] ?? []];
            } catch (Throwable $exception) {
                // Provider failures may contain credentials or tokens. Return
                // one message and leave the current browser session unchanged.
                error_log('Usina Docs OAuth callback failed for '.$provider);
                return $response->withStatus(302)->withHeader('Location', $retry);
            }

            return $response->withStatus(302)->withHeader('Cache-Control', 'no-store')
                ->withHeader('Location', $next ?? (Roles::canEdit($user) ? '/admin' : '/'));
        });

        $app->get('/admin/users', static function ($request, $response) use ($twig, $accounts) {
            if (!Roles::canManageUsers($_SESSION['user'] ?? null)) return $response->withStatus(403);

            return $twig->render($response, 'oauth-users.twig', [
                'users' => $accounts->users(),
                'groups' => $accounts->groups(),
            ])->withHeader('Cache-Control', 'no-store');
        });

        $app->post('/admin/users/{id}/group', static function ($request, $response, array $arguments) use ($accounts) {
            if (!Roles::canManageUsers($_SESSION['user'] ?? null)) return $response->withStatus(403);

            $group = ((array) $request->getParsedBody())['group'] ?? null;
            if (!is_string($group) || !$accounts->changeRole($arguments['id'], $group)) {
                $response->getBody()->write('Não foi possível alterar o grupo. Mantenha pelo menos um administrador.');
                return $response->withStatus(422)->withHeader('Content-Type', 'text/plain; charset=UTF-8');
            }

            return $response->withStatus(303)->withHeader('Location', '/admin/users');
        });
    }
}
