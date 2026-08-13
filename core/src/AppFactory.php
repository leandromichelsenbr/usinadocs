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
use UsinaDocs\Core\Content\EditorialService;

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
        $editorial = new EditorialService($database);
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
        $app->get('/admin', static function (ServerRequestInterface $request, ResponseInterface $response) use ($twig, $editorial): ResponseInterface {
            if (!isset($_SESSION['user']) || $_SESSION['user']['role'] !== 'administrator') return $response->withHeader('Location', '/login')->withStatus(302);
            $search=(string)($request->getQueryParams()['q']??'');return $twig->render($response, 'admin.twig', ['user' => $_SESSION['user'], 'drafts' => $editorial->drafts(), 'published' => $editorial->published(), 'catalog'=>$editorial->catalog($search),'search'=>$search]);
        });
        $app->get('/admin/pages/new', static function ($request,$response) use ($twig) { if(!isset($_SESSION['user'])) return $response->withHeader('Location','/login')->withStatus(302); return $twig->render($response,'page-form.twig'); });
        $app->post('/admin/pages', static function (ServerRequestInterface $request,ResponseInterface $response) use ($editorial) { if(!isset($_SESSION['user'])) return $response->withHeader('Location','/login')->withStatus(302); $d=(array)$request->getParsedBody(); $id=$editorial->create(trim((string)$d['title']),trim((string)$d['slug']),trim((string)$d['summary']),trim((string)$d['body']),trim((string)$d['code'])); return $response->withHeader('Location','/admin')->withStatus(302); });
        $app->post('/admin/pages/{id}/publish', static function ($request,$response,array $a) use ($editorial) { if(!isset($_SESSION['user'])) return $response->withHeader('Location','/login')->withStatus(302); $editorial->publish($a['id']); return $response->withHeader('Location','/admin')->withStatus(302); });
        $app->post('/admin/pages/{id}/revisions', static function ($request,$response,array $a) use ($editorial) { if(!isset($_SESSION['user'])) return $response->withHeader('Location','/login')->withStatus(302); $editorial->createRevision($a['id']); return $response->withHeader('Location','/admin/pages/'.$a['id'].'/edit')->withStatus(302); });
        $app->get('/admin/pages/{id}/edit', static function ($request,$response,array $a) use ($editorial,$twig) { if(!isset($_SESSION['user'])) return $response->withHeader('Location','/login')->withStatus(302); $page=$editorial->draft($a['id']); if($page===null)return $response->withStatus(404); return $twig->render($response,'page-form.twig',['page'=>$page]); });
        $app->post('/admin/pages/{id}', static function (ServerRequestInterface $request,ResponseInterface $response,array $a) use ($editorial) { if(!isset($_SESSION['user'])) return $response->withHeader('Location','/login')->withStatus(302);$d=(array)$request->getParsedBody();$editorial->updateDraft($a['id'],trim((string)$d['title']),trim((string)$d['summary']),trim((string)$d['body']),trim((string)$d['code']));return $response->withHeader('Location','/admin')->withStatus(302); });
        $app->post('/admin/pages/{id}/translations/{language}', static function (ServerRequestInterface $request,ResponseInterface $response,array $a) use ($editorial) { if(!isset($_SESSION['user']))return$response->withHeader('Location','/login')->withStatus(302);$d=(array)$request->getParsedBody();$editorial->createTranslation($a['id'],$a['language'],trim((string)$d['slug']));return$response->withHeader('Location','/admin/pages/'.$a['id'].'/'.$a['language'].'/edit')->withStatus(302);});
        $app->get('/admin/pages/{id}/{language}/edit', static function ($request,$response,array $a) use ($editorial,$twig) { if(!isset($_SESSION['user']))return$response->withHeader('Location','/login')->withStatus(302);$page=$editorial->localizedDraft($a['id'],$a['language']);if($page===null)return$response->withStatus(404);return$twig->render($response,'page-form.twig',['page'=>$page]);});
        $app->post('/admin/pages/{id}/{language}', static function (ServerRequestInterface $request,ResponseInterface $response,array $a) use ($editorial) { if(!isset($_SESSION['user']))return$response->withHeader('Location','/login')->withStatus(302);$d=(array)$request->getParsedBody();$editorial->updateLocalizedDraft($a['id'],$a['language'],trim((string)$d['title']),trim((string)$d['summary']),trim((string)$d['body']),trim((string)$d['code']));return$response->withHeader('Location','/admin')->withStatus(302);});
        $app->post('/admin/pages/{id}/{language}/publish', static function ($request,$response,array $a) use ($editorial) {if(!isset($_SESSION['user']))return$response->withHeader('Location','/login')->withStatus(302);$editorial->publishLocalized($a['id'],$a['language']);return$response->withHeader('Location','/admin')->withStatus(302);});

        return $app;
    }
}
