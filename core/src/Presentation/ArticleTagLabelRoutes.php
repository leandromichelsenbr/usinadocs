<?php

declare(strict_types=1);

namespace UsinaDocs\Core\Presentation;

use InvalidArgumentException;
use Slim\App;
use Slim\Views\Twig;
use UsinaDocs\Core\Authentication\Roles;
use UsinaDocs\Core\Content\ArticleTagLabels;

final class ArticleTagLabelRoutes
{
    public static function register(App $app, Twig $twig, ArticleTagLabels $labels): void
    {
        $screen = static function ($response, ?string $selected, bool $saved = false, ?array $invalid = null) use ($twig, $labels) {
            $catalog = $labels->catalog();
            if ($selected !== null && !isset($catalog[$selected])) {
                return $response->withStatus(404);
            }
            return $twig->render($response, 'article-tag-labels-admin.twig', [
                'catalog' => $catalog, 'selected' => $selected === null ? null : $catalog[$selected],
                'saved' => $saved, 'invalid' => $invalid,
            ])->withHeader('Cache-Control', 'no-store');
        };

        // Tag names are global editorial metadata. Restrict changes to the editorial team and use the common CSRF middleware.
        // Os nomes das hashtags são metadados editoriais globais. Restringe alterações à equipe editorial e usa o middleware comum de CSRF.
        $app->get('/admin/hashtags', static function ($request, $response) use ($screen) {
            if (!Roles::canEdit($_SESSION['user'] ?? null)) {
                return $response->withStatus(403);
            }
            $prefs = $request->getQueryParams();
            return $screen($response, is_string($prefs['tag'] ?? null) ? $prefs['tag'] : null, ($prefs['saved'] ?? null) === '1');
        });
        $app->post('/admin/hashtags', static function ($request, $response) use ($screen, $labels) {
            if (!Roles::canEdit($_SESSION['user'] ?? null)) {
                return $response->withStatus(403);
            }
            $input = (array) $request->getParsedBody();
            $key = $input['tag'] ?? null;
            if (!is_string($key) || !isset($labels->catalog()[$key])) {
                return $response->withStatus(404);
            }

            $english = is_string($input['label_en'] ?? null) ? $input['label_en'] : '';
            $spanish = is_string($input['label_es'] ?? null) ? $input['label_es'] : '';
            try {
                $labels->save($key, ($input['universal'] ?? null) === '1', $english, $spanish);
            } catch (InvalidArgumentException) {
                return $screen($response->withStatus(422), $key, invalid: ['label_en' => $english, 'label_es' => $spanish]);
            }
            return $response->withStatus(303)->withHeader('Location', '/admin/hashtags?tag='.rawurlencode($key).'&saved=1');
        });
    }
}
