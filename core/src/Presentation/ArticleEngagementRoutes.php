<?php

declare(strict_types=1);

namespace UsinaDocs\Core\Presentation;

use PDOException;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Slim\App;
use Slim\Views\Twig;
use UsinaDocs\Core\Authentication\Roles;
use UsinaDocs\Core\Content\ArticleEngagement;
use UsinaDocs\Core\Content\PublishedPageRepository;

final class ArticleEngagementRoutes
{
    private const BOT_PATTERN = '/bot|crawler|spider|slurp|bingpreview|facebookexternalhit|headless|uptime|monitor/i';

    public static function readerContext(ServerRequestInterface $request, array $page, ArticleEngagement $engagement): array
    {
        $pageId = $page['page_id'];
        $locale = $page['language_code'];
        $path = self::path($page);
        $agent = $request->getHeaderLine('User-Agent');
        $purpose = $request->getHeaderLine('Sec-Purpose').' '.$request->getHeaderLine('Purpose');
        $reading = $request->getMethod() === 'GET' && !preg_match('/prefetch|prerender/i', $purpose);
        $views = null;

        // Keep the deduplication marker in the existing PHP session and only set it after the database write.
        // Guarda a marca de deduplicação na sessão PHP existente e só a define após gravar no banco.
        try {
            if ($reading && $agent !== '' && !preg_match(self::BOT_PATTERN, $agent) && empty($_SESSION['article_views'][$pageId])) {
                $engagement->incrementViews($pageId);
                $_SESSION['article_views'][$pageId] = true;
            }
            $views = $engagement->viewCount($pageId);
        } catch (PDOException $error) {
            // A failed counter write must not interrupt article reading or present a false zero.
            // Uma falha ao gravar o contador não deve interromper a leitura nem apresentar um zero incorreto.
            error_log('Usina Docs article view counter: '.$error->getMessage());
        }

        $flash = $_SESSION['article_comment_flash'] ?? [];
        if ($reading && ($flash['path'] ?? null) === $path) {
            unset($_SESSION['article_comment_flash']);
        } else {
            $flash = [];
        }

        if (isset($_SESSION['user']['id'])) {
            $_SESSION['article_comment_csrf'] ??= bin2hex(random_bytes(32));
        }

        return [
            'views' => $views,
            'comments' => $engagement->approvedComments($pageId, $locale),
            'path' => $path,
            'flash' => $flash['message'] ?? null,
            'csrf' => isset($_SESSION['user']['id']) ? $_SESSION['article_comment_csrf'] : null,
        ];
    }

    public static function register(App $app, Twig $twig, ArticleEngagement $engagement, PublishedPageRepository $pages): void
    {
        $app->post('/{locale:pt|en|es}/p/{slug}/comments', static function ($request, $response, array $args) use ($engagement, $pages) {
            // Derive the page, locale and commenter from trusted sources instead of submitted metadata.
            // Obtém a página, o idioma e o autor do comentário de fontes confiáveis em vez de campos enviados pelo formulário.
            $page = $pages->findByLocalizedSlug($args['locale'], $args['slug']);
            if ($page === null || $page['content_type'] !== 'article') {
                return $response->withStatus(404);
            }

            $user = $_SESSION['user'] ?? null;
            $input = (array) $request->getParsedBody();
            if (!isset($user['id']) || !self::validToken($input['csrf'] ?? null, 'article_comment_csrf')) {
                return $response->withStatus(403);
            }

            $body = is_string($input['body'] ?? null) ? trim($input['body']) : '';
            if (mb_check_encoding($body, 'UTF-8') && mb_strlen($body) >= 2 && mb_strlen($body) <= 2000) {
                $saved = $engagement->submitComment($page['page_id'], $page['language_code'], $user, $body);
                $message = $saved ? 'pending' : 'rate_limit';
            } else {
                $message = 'invalid_length';
            }

            $path = self::path($page);
            $_SESSION['article_comment_flash'] = ['path' => $path, 'message' => $message];
            return $response->withHeader('Location', $path.'#comments')->withStatus(303);
        });

        $app->get('/admin/comments', static function ($request, $response) use ($twig, $engagement) {
            if (!Roles::canEdit($_SESSION['user'] ?? null)) return $response->withStatus(403);

            $query = $request->getQueryParams();
            $status = in_array($query['status'] ?? null, ArticleEngagement::COMMENT_STATUSES, true) ? $query['status'] : 'pending';
            $_SESSION['comment_moderation_csrf'] ??= bin2hex(random_bytes(32));

            return $twig->render($response, 'comments-admin.twig', [
                'status' => $status,
                'csrf' => $_SESSION['comment_moderation_csrf'],
                'comments' => $engagement->moderationQueue($status),
            ])->withHeader('Cache-Control', 'no-store');
        });
        $app->post('/admin/comments/{id:[0-9]+}', static function ($request, $response, array $args) use ($engagement) {
            $user = $_SESSION['user'] ?? null;
            $input = (array) $request->getParsedBody();
            if (!Roles::canEdit($user) || !self::validToken($input['csrf'] ?? null, 'comment_moderation_csrf')) {
                return $response->withStatus(403);
            }

            $current = $input['current'] ?? null;
            $decision = $input['decision'] ?? null;
            if (!in_array($current, ArticleEngagement::COMMENT_STATUSES, true)
                || !in_array($decision, ['approved', 'rejected'], true) || $current === $decision) {
                return $response->withStatus(422);
            }

            if (!$engagement->moderate((int) $args['id'], $current, $decision, $user['id'])) {
                return $response->withStatus(409);
            }
            return $response->withHeader('Location', '/admin/comments?status='.$current)->withStatus(303);
        });

        $app->get('/admin/article-views', static function ($request, $response) use ($twig, $engagement) {
            if (!Roles::canEdit($_SESSION['user'] ?? null)) return $response->withStatus(403);

            $articles = $engagement->articleViews();
            return $twig->render($response, 'article-views-admin.twig', [
                'articles' => $articles,
                'total' => array_sum(array_column($articles, 'views')),
            ])->withHeader('Cache-Control', 'no-store');
        });
    }

    private static function path(array $page): string
    {
        return '/'.$page['language_code'].'/p/'.rawurlencode($page['slug']);
    }

    private static function validToken(mixed $token, string $key): bool
    {
        // Each form uses its own session token, including on installations with password sign-in.
        // Cada formulário usa seu próprio token de sessão, inclusive em instalações com login por senha.
        return is_string($token) && isset($_SESSION[$key]) && hash_equals($_SESSION[$key], $token);
    }
}
