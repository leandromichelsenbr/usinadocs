<?php

declare(strict_types=1);

namespace UsinaDocs\Core;

use Dotenv\Dotenv;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Slim\Factory\AppFactory as SlimAppFactory;
use Slim\Views\Twig;
use UsinaDocs\Core\Content\PublishedPageRepository;
use UsinaDocs\Core\Infrastructure\Database;
use UsinaDocs\Core\Authentication\Authenticator;

final class AppFactory
{
    public static function create(string $root, ?string $databasePath = null): \Slim\App
    {
        Dotenv::createImmutable($root)->safeLoad();
        if ($databasePath === null) {
            $configuredPath = $_ENV['USINADOCS_DB_PATH'] ?? 'database/database.sqlite';
            $isAbsolute = str_starts_with($configuredPath, DIRECTORY_SEPARATOR) || preg_match('/^[A-Za-z]:[\\\\\/]/', $configuredPath) === 1;
            $databasePath = $isAbsolute ? $configuredPath : $root.DIRECTORY_SEPARATOR.$configuredPath;
        }

        $database = Database::connect($databasePath);
        $repository = new PublishedPageRepository($database);
        $authenticator = new Authenticator($database);
        $twig = Twig::create($root.'/templates', ['cache' => false]);
        $app = SlimAppFactory::create();
        $app->addErrorMiddleware(true, true, true);
        $app->addBodyParsingMiddleware();
        if (session_status() !== PHP_SESSION_ACTIVE) session_start();

        $app->get('/', static function (ServerRequestInterface $request, ResponseInterface $response): ResponseInterface {
            return $response->withHeader('Location', '/pt/p/bem-vindo')->withStatus(302);
        });
        $app->get('/favicon.ico', static function (ServerRequestInterface $request, ResponseInterface $response): ResponseInterface {
            return $response->withStatus(204);
        });

        $app->get('/{locale}/p/{slug}', static function (ServerRequestInterface $request, ResponseInterface $response, array $arguments) use ($repository, $twig): ResponseInterface {
            $page = $repository->findByLocalizedSlug((string) $arguments['locale'], (string) $arguments['slug']);

            if ($page === null) {
                $response->getBody()->write('Page not found.');

                return $response->withStatus(404);
            }

            return $twig->render($response, 'page.twig', ['page' => $page]);
        });
        $app->get('/login', static fn (ServerRequestInterface $request, ResponseInterface $response): ResponseInterface => $twig->render($response, 'login.twig'));
        $app->post('/login', static function (ServerRequestInterface $request, ResponseInterface $response) use ($authenticator): ResponseInterface {
            $data = (array) $request->getParsedBody(); $user = $authenticator->attempt((string)($data['email'] ?? ''), (string)($data['password'] ?? ''));
            if ($user === null) return $response->withHeader('Location', '/login')->withStatus(302);
            session_regenerate_id(true); $_SESSION['user'] = $user; return $response->withHeader('Location', '/admin')->withStatus(302);
        });
        $app->post('/logout', static function (ServerRequestInterface $request, ResponseInterface $response): ResponseInterface { $_SESSION = []; session_destroy(); return $response->withHeader('Location', '/')->withStatus(302); });
        $app->get('/admin', static function (ServerRequestInterface $request, ResponseInterface $response) use ($twig): ResponseInterface {
            if (!isset($_SESSION['user']) || $_SESSION['user']['role'] !== 'administrator') return $response->withHeader('Location', '/login')->withStatus(302);
            return $twig->render($response, 'admin.twig', ['user' => $_SESSION['user']]);
        });

        return $app;
    }
}
