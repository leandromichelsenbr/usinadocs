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

        $repository = new PublishedPageRepository(Database::connect($databasePath));
        $twig = Twig::create($root.'/templates', ['cache' => false]);
        $app = SlimAppFactory::create();

        $app->get('/', static function (ServerRequestInterface $request, ResponseInterface $response): ResponseInterface {
            return $response->withHeader('Location', '/pt/p/bem-vindo')->withStatus(302);
        });

        $app->get('/{locale}/p/{slug}', static function (ServerRequestInterface $request, ResponseInterface $response, array $arguments) use ($repository, $twig): ResponseInterface {
            $page = $repository->findByLocalizedSlug((string) $arguments['locale'], (string) $arguments['slug']);

            if ($page === null) {
                $response->getBody()->write('Page not found.');

                return $response->withStatus(404);
            }

            return $twig->render($response, 'page.twig', ['page' => $page]);
        });

        return $app;
    }
}
