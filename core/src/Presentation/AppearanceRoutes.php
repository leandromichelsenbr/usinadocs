<?php

declare(strict_types=1);

namespace UsinaDocs\Core\Presentation;

use InvalidArgumentException;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Slim\App;
use Slim\Views\Twig;
use UsinaDocs\Core\Authentication\Roles;
use UsinaDocs\Core\Media\MediaLibrary;
use UsinaDocs\Core\Security\CsrfToken;

final class AppearanceRoutes
{
    public static function register(App $app, Twig $twig, SiteAppearance $appearance, MediaLibrary $media): void
    {
        // Every installation shares the same form and authorization rules, including password-based installations.
        // Todas as instalações usam o mesmo formulário e as mesmas regras de autorização, inclusive as com acesso por senha.
        $screen = static function (
            ResponseInterface $response,
            array $settings,
            ?string $error = null,
            bool $saved = false
        ) use ($twig, $media): ResponseInterface {
            return $twig->render($response, 'appearance.twig', [
                'settings' => $settings,
                'images' => $media->all(),
                'error' => $error,
                'saved' => $saved,
            ])->withHeader('Cache-Control', 'no-store');
        };

        $app->get('/admin/appearance', static function (ServerRequestInterface $request, ResponseInterface $response) use ($appearance, $screen): ResponseInterface {
            if (!Roles::canManageUsers($_SESSION['user'] ?? null)) {
                return $response->withStatus(403);
            }

            $saved = !empty($_SESSION['appearance_saved']);
            unset($_SESSION['appearance_saved']);

            return $screen($response, $appearance->current(), saved: $saved);
        });

        $app->post('/admin/appearance', static function (ServerRequestInterface $request, ResponseInterface $response) use ($appearance, $screen): ResponseInterface {
            $input = (array) $request->getParsedBody();
            $token = $input['csrf'] ?? null;

            // The token proves that the administrator opened the form in this session before submitting it.
            // O token comprova que o administrador abriu o formulário nesta sessão antes de enviá-lo.
            if (!Roles::canManageUsers($_SESSION['user'] ?? null) || !CsrfToken::isValid($token)) {
                return $response->withStatus(403);
            }

            try {
                $appearance->save($input);
            } catch (InvalidArgumentException $exception) {
                $rawSettings = array_intersect_key($input, $appearance->current());

                return $screen($response->withStatus(422), array_filter($rawSettings, 'is_string'), $exception->getMessage());
            }

            // Use a redirect after saving so refreshing the page cannot resubmit the changes.
            // Redireciona após salvar para que atualizar a página não envie as mudanças de novo.
            $_SESSION['appearance_saved'] = true;

            return $response->withStatus(303)->withHeader('Location', '/admin/appearance');
        });
    }
}
