<?php

declare(strict_types=1);

namespace UsinaDocs\Core;

use Dotenv\Dotenv;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Slim\Factory\AppFactory as SlimAppFactory;
use Slim\Views\Twig;
use UsinaDocs\Core\Content\PublishedPageRepository;
use UsinaDocs\Core\Content\NewsArticles;
use UsinaDocs\Core\Infrastructure\Database;
use UsinaDocs\Core\Authentication\Authenticator;
use UsinaDocs\Core\Authentication\LoginReturn;
use UsinaDocs\Core\Authentication\OAuthAccounts;
use UsinaDocs\Core\Authentication\OAuthClient;
use UsinaDocs\Core\Authentication\OAuthRoutes;
use UsinaDocs\Core\Authentication\Roles;
use UsinaDocs\Core\Content\EditorialService;
use UsinaDocs\Core\Content\ArticleSections;
use UsinaDocs\Core\Content\ArticleTags;
use UsinaDocs\Core\Content\EditorialMaintenance;
use UsinaDocs\Core\Content\ReusableArtifactService;
use UsinaDocs\Core\Content\ContentPackageService;
use UsinaDocs\Core\Content\PageCovers;
use UsinaDocs\Core\Content\PageAlerts;
use UsinaDocs\Core\Content\AuthorDirectory;
use UsinaDocs\Core\Content\ArticleEngagement;
use UsinaDocs\Core\Content\LegalDocuments;
use UsinaDocs\Core\Content\PublicationBatch;
use UsinaDocs\Core\Content\Sitemap;
use UsinaDocs\Core\Media\MediaLibrary;
use UsinaDocs\Core\Presentation\AppearanceRoutes;
use UsinaDocs\Core\Presentation\ReaderPreferences;
use UsinaDocs\Core\Presentation\EditorialMaintenanceRoutes;
use UsinaDocs\Core\Presentation\PageEditorRoutes;
use UsinaDocs\Core\Presentation\SectionRoutes;
use UsinaDocs\Core\Presentation\PublicationRoutes;
use UsinaDocs\Core\Presentation\AuthorRoutes;
use UsinaDocs\Core\Presentation\ArticleEngagementRoutes;
use UsinaDocs\Core\Presentation\LegalDocumentRoutes;
use UsinaDocs\Core\Presentation\SiteAppearance;
use UsinaDocs\Core\Presentation\SitemapRoutes;
use UsinaDocs\Core\Security\CsrfToken;
use UsinaDocs\Core\Presentation\SocialRoutes;
use UsinaDocs\Core\Social\ArticleLinks;
use UsinaDocs\Core\Social\SocialPublication;
use Twig\TwigFunction;

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
        // EN: The news homepage is optional. Installations that publish a newspaper (such as Usina News)
        // set USINADOCS_NEWS_HOME=1; every other installation keeps the documentation homepage.
        // PT: A página inicial de notícias é opcional. Instalações que publicam um jornal (como a Usina News)
        // definem USINADOCS_NEWS_HOME=1; as demais mantêm a página inicial de documentação.
        $news = ($_ENV['USINADOCS_NEWS_HOME'] ?? '') === '1' ? new NewsArticles($database) : null;
        $useOAuth = ($_ENV['USINADOCS_AUTH_MODE'] ?? '') === 'oauth';
        $editorial = new EditorialService($database);
        $artifacts = new ReusableArtifactService($database);
        $media = new MediaLibrary($database, $root.'/storage/media');
        $packages = new ContentPackageService($database, $media);
        $covers = new PageCovers($database, $media);
        $appearance = new SiteAppearance($database, $media);
        $twig = Twig::create($root.'/templates', ['cache' => false]);
        // Hashtag links point to the news archive, which is available when the news homepage is enabled.
        // Links de hashtags apontam para o arquivo de notícias, disponível quando a capa de notícias está ativa.
        $twig->getEnvironment()->addGlobal('news_hashtags', $news !== null);
        if ($news !== null) {
            // EN: The newspaper's name and newsroom time zone are installation settings, so the same
            // core can power other publications. The defaults keep the current Usina News behavior.
            // PT: O nome do jornal e o fuso da redação são configurações da instalação, para que o mesmo
            // núcleo atenda outras publicações. Os padrões mantêm o comportamento atual da Usina News.
            $twig->getEnvironment()->addGlobal('site_default_name', (string) ($_ENV['USINADOCS_SITE_NAME'] ?? 'Usina News'));
            $twig->getEnvironment()->addGlobal('site_timezone', (string) ($_ENV['USINADOCS_TIMEZONE'] ?? 'America/Sao_Paulo'));
        }
        $app = SlimAppFactory::create();
        // EN: Slim runs middleware in reverse order of registration (last added runs first).
        // These three are registered first so they run last, right before the route:
        // routing -> body parsing -> CSRF check -> route. Routing comes first so unknown
        // paths and wrong methods still answer 404/405; body parsing comes before the CSRF
        // check so the form field is available.
        // PT: O Slim executa os middlewares na ordem inversa do registro (o último
        // registrado roda primeiro). Estes três são registrados antes dos demais para rodar
        // por último, logo antes da rota: roteamento -> leitura do corpo -> verificação de
        // CSRF -> rota. O roteamento vem antes para que caminhos inexistentes e métodos
        // errados continuem respondendo 404/405; a leitura do corpo vem antes da verificação
        // para que o campo do formulário esteja disponível.
        $app->add(CsrfToken::middleware());
        $app->addBodyParsingMiddleware();
        $app->addRoutingMiddleware();
        // EN: Every template can print the session token with {{ csrf_token() }}.
        // PT: Todo template pode imprimir o token da sessão com {{ csrf_token() }}.
        $twig->getEnvironment()->addFunction(new TwigFunction('csrf_token', CsrfToken::current(...)));
        if (session_status() !== PHP_SESSION_ACTIVE) {
            if ($useOAuth) {
                session_name('usinadocs_oauth');
                session_set_cookie_params(['path' => '/', 'secure' => true, 'httponly' => true, 'samesite' => 'Lax']);
                ini_set('session.use_strict_mode', '1');
            } else {
                // EN: Password mode may run over plain HTTP during local evaluation, so the cookie
                // cannot require Secure. HttpOnly keeps scripts from reading it and SameSite=Lax
                // stops browsers from sending it with cross-site POSTs, a second layer over the token.
                // PT: O modo senha pode rodar em HTTP simples na avaliação local, então o cookie
                // não pode exigir Secure. HttpOnly impede que scripts o leiam e SameSite=Lax faz o
                // navegador não enviá-lo em POSTs vindos de outros sites, uma segunda camada além do token.
                session_set_cookie_params(['path' => '/', 'httponly' => true, 'samesite' => 'Lax']);
                ini_set('session.use_strict_mode', '1');
            }
            session_start();
        }
        if ($useOAuth) {
            $configPath = (string) ($_ENV['USINADOCS_OAUTH_CONFIG'] ?? '');
            $settings = is_readable($configPath) ? json_decode((string) file_get_contents($configPath), true) : null;
            OAuthRoutes::register($app, $twig, new OAuthClient(is_array($settings) ? $settings : []), new OAuthAccounts($database));
        }
        // Use release metadata shipped with this installation. An optional deployment stamp identifies the exact source revision.
        // Usa os metadados de versão desta instalação. Um identificador opcional da implantação indica a revisão exata do código.
        $version = trim((string) file_get_contents(dirname(__DIR__).'/VERSION'));
        $revisionFile = dirname(__DIR__).'/BUILD_REVISION';
        $revision = is_readable($revisionFile) ? trim((string) file_get_contents($revisionFile)) : '';
        if (preg_match('/\A(?:[0-9a-f]{40}|[0-9a-f]{64})\z/', $revision)) {
            $version .= ' · '.substr($revision, 0, 7);
        }
        $twig->getEnvironment()->addGlobal('usinadocs_version', $version);

        AppearanceRoutes::register($app, $twig, $appearance, $media);
        // Legal documents are shared site content and use the same templates under either sign-in mode.
        // Os textos legais são conteúdo do site e usam os mesmos templates nos dois modos de login.
        $legalDocuments = new LegalDocuments($database);
        LegalDocumentRoutes::register($app, $twig, $legalDocuments);
        AuthorRoutes::register($app, $twig, new AuthorDirectory($database));
        $engagement = new ArticleEngagement($database);
        ArticleEngagementRoutes::register($app, $twig, $engagement, $repository);
        // Channel management and the reader URLs share the publication instance, without loading API tokens in PHP-FPM.
        // O gerenciamento dos canais e os links públicos usam a mesma instalação, sem carregar tokens de API no PHP-FPM.
        SocialRoutes::register($app, $twig, new SocialPublication($database));
        $publicOrigin = ArticleLinks::origin($_ENV['USINADOCS_PUBLIC_ORIGIN'] ?? '');
        // Enable indexing only after the installation declares its public URL.
        // Habilita a indexação somente depois que a instalação declara sua URL pública.
        $twig->getEnvironment()->addGlobal('sitemap_available', $publicOrigin !== null);
        if ($publicOrigin !== null) {
            SitemapRoutes::register($app, $twig, new Sitemap($database, $news !== null), $publicOrigin);
        }
        PublicationRoutes::register($app, $twig, new PublicationBatch($database, $editorial, $repository, $covers), $repository, $covers, $media, new PageAlerts($database));
        EditorialMaintenanceRoutes::register($app, $twig, $editorial, new EditorialMaintenance($database));
        // Use the same directory for section management and article classification.
        // Usa o mesmo cadastro para gerenciar editorias e classificar artigos.
        $sections = new ArticleSections($database);
        SectionRoutes::register($app, $twig, $sections);
        PageEditorRoutes::register($app, $twig, $editorial, $artifacts, $media, $sections);
        $app->add(ReaderPreferences::middleware($twig));
        $app->add(static function (ServerRequestInterface $request, $next) use ($twig, $appearance): ResponseInterface {
            // A request sees one saved identity across its page, icon and logo components.
            // Cada requisição vê a mesma identidade salva na página, no ícone e nos componentes de logotipo.
            $settings = $appearance->current();
            $twig->getEnvironment()->addGlobal('site_appearance', $settings);

            return $next->handle($request->withAttribute(SiteAppearance::class, $settings));
        });
        $app->addErrorMiddleware(!$useOAuth, true, true);

        if ($news === null) {
            $app->get('/', static function (ServerRequestInterface $request, ResponseInterface $response): ResponseInterface {
                return $response->withHeader('Location', '/pt/p/bem-vindo')->withStatus(302);
            });
        } else {
            // EN: Both URLs show the same reader homepage, so links to /pt/p/inicio keep working
            // while the web server root is configured.
            // PT: Os dois endereços mostram a mesma página inicial, para que links para /pt/p/inicio
            // continuem funcionando enquanto a raiz do servidor web é configurada.
            $homepage = static function (
                ServerRequestInterface $request,
                ResponseInterface $response
            ) use ($twig, $news, $root, $engagement, $sections): ResponseInterface {
                $language = $request->getAttribute(ReaderPreferences::class)->language;
                $translations = require $root.'/config/news-reader-copy.php';
                $copy = $translations[$language];
                $query = $request->getQueryParams();
                $tag = is_string($query['tag'] ?? null) ? ArticleTags::key($query['tag']) : null;
                $pageSize = 24;
                $pageNumber = $tag === null ? 1 : (filter_var($query['page'] ?? null, FILTER_VALIDATE_INT,
                    ['options' => ['min_range' => 1, 'max_range' => intdiv(PHP_INT_MAX, $pageSize)]]) ?: 1);
                // EN: Use published section names from the directory so edits and blocks take effect on the homepage.
                // PT: Usa os nomes do cadastro para refletir as alterações e os bloqueios na página inicial.
                $channels = [];
                foreach ($tag === null ? $sections->all() : [] as $section) {
                    if ($section['blocked']) continue;
                    $slug = $section['slug'];
                    $channels[$slug] = ['name' => $section['title_'.$language], 'articles' => $news->latest(3, $slug, $language)];
                }

                // EN: Fetch one snapshot of counts for all cards, including articles repeated in a section.
                // PT: Busca os totais de todas as chamadas de uma vez, inclusive de artigos repetidos numa editoria.
                // Fetch one additional result to tell readers whether another archive page exists.
                // Busca um resultado a mais para informar se existe outra página do arquivo.
                $latest = $tag === null ? $news->latest(6, null, $language)
                    : $news->latest($pageSize + 1, null, $language, $tag, ($pageNumber - 1) * $pageSize);
                $hasNext = count($latest) > $pageSize;
                if ($hasNext) {
                    array_pop($latest);
                }
                $tagLabel = $tag === null ? null : (array_column($latest[0]['tags'] ?? [], 'label', 'key')[$tag] ?? $tag);
                $listedArticles = array_merge($latest, ...array_column($channels, 'articles'));
                $counts = $engagement->countsForArticles(array_column($listedArticles, 'page_id'));

                return $twig->render($response, 'news-home.twig', [
                    'latest' => $latest,
                    'engagement_counts' => $counts,
                    'news_copy' => $copy,
                    'sections' => $channels,
                    'active_tag' => $tag,
                    'tag_label' => $tagLabel,
                    'tag_page' => $pageNumber,
                    'tag_has_next' => $hasNext,
                ]);
            };
            $app->map(['GET', 'HEAD'], '/', $homepage);
            $app->map(['GET', 'HEAD'], '/pt/p/inicio', $homepage);
        }
        $app->get('/favicon.ico', static function (ServerRequestInterface $request, ResponseInterface $response): ResponseInterface {
            // The legacy browser path tracks the current selection without caching a previous redirect.
            // O endereço tradicional do navegador acompanha a seleção atual sem guardar um redirecionamento anterior.
            $icon = $request->getAttribute(SiteAppearance::class)['favicon_id'];

            return $icon === null
                ? $response->withStatus(204)
                : $response->withHeader('Location', '/media/'.rawurlencode($icon))->withHeader('Cache-Control', 'no-store')->withStatus(302);
        });
        $app->get('/media/{id}', static function ($request,$response,array $a) use ($media) { $item=$media->find($a['id']);if($item===null||!is_file($media->path($item)))return$response->withStatus(404);$stream=fopen($media->path($item),'rb');$response->getBody()->write((string)stream_get_contents($stream));fclose($stream);return$response->withHeader('Content-Type',$item['mime_type'])->withHeader('Content-Length',(string)$item['byte_size']);});

        $app->get('/{locale}/p/{slug}', static function (ServerRequestInterface $request, ResponseInterface $response, array $arguments) use ($repository, $twig, $engagement, $legalDocuments, $covers, $publicOrigin): ResponseInterface {
            $page = $repository->findByLocalizedSlug((string) $arguments['locale'], (string) $arguments['slug']);

            if ($page === null) {
                // Fall back to legal documents only when no published editorial page claims this address.
                // Consulta os textos legais apenas quando nenhuma página editorial publicada usa esse endereço.
                $legal = LegalDocumentRoutes::readerResponse(
                    $request, $response, $twig, $legalDocuments, $arguments['locale'], $arguments['slug']
                );
                if ($legal !== null) return $legal;

                $response->getBody()->write('Page not found.');

                // A hidden article can return at the same URL, so avoid caching its temporary 404.
                // Um artigo oculto pode voltar no mesmo endereço, por isso evita cache do 404 temporário.
                return $response->withStatus(404)->withHeader('Cache-Control', 'no-store');
            }

            $preferences = $request->getAttribute(ReaderPreferences::class);
            if ($preferences->requestedLanguage !== null && $preferences->language !== $page['language_code']) {
                // Keep each translation at its published URL, even when its slug differs.
                foreach ($page['translations'] as $translation) {
                    if ($translation['language_code'] === $preferences->language) {
                        return $response->withHeader('Location', '/'.$preferences->language.'/p/'.rawurlencode($translation['slug']))->withStatus(302);
                    }
                }
            }

            $canEdit = Roles::canEdit($_SESSION['user'] ?? null);
            // Build engagement only after finding a published article and resolving language redirects.
            // Prepara a interação apenas após encontrar um artigo publicado e resolver os redirecionamentos de idioma.
            $readerContext = $page['content_type'] === 'article'
                ? ArticleEngagementRoutes::readerContext($request, $page, $engagement)
                : null;
            if ($readerContext !== null) {
                $response = $response->withHeader('Cache-Control', 'private, no-store');
            }
            // Share the published edition's stable address, omitting theme, session and tracking parameters.
            // Compartilha o endereço estável da edição publicada, sem parâmetros de tema, sessão ou rastreamento.
            $socialShare = null;
            $uri = $request->getUri();
            $origin = $publicOrigin ?? ArticleLinks::origin($uri->getScheme().'://'.$uri->getAuthority());
            if ($page['content_type'] === 'article' && $origin !== null) {
                $url = ArticleLinks::url($origin, $page);
                $cover = $covers->get($page['page_id']);
                $socialShare = [
                    'url' => $url,
                    'links' => ArticleLinks::share($url, $page['title']),
                    'image' => $cover === null ? null : $origin.'/media/'.rawurlencode($cover),
                ];
            }
            return $twig->render($response, 'page.twig', [
                'page' => $page, 'can_edit' => $canEdit, 'engagement' => $readerContext, 'social_share' => $socialShare,
            ]);
        });
        if (!$useOAuth) {
            $authenticator = new Authenticator($database);
            $app->get('/login', static function (ServerRequestInterface $request, ResponseInterface $response) use ($twig): ResponseInterface {
                return $twig->render($response, 'login.twig', [
                    'next' => LoginReturn::articleComments($request->getQueryParams()['next'] ?? null),
                ])->withHeader('Cache-Control', 'no-store');
            });
            $app->post('/login', static function (ServerRequestInterface $request, ResponseInterface $response) use ($authenticator): ResponseInterface {
                $data = (array) $request->getParsedBody();
                $user = $authenticator->attempt((string) ($data['email'] ?? ''), (string) ($data['password'] ?? ''));
                // Preserve the comment destination through a password retry and after successful authentication.
                // Preserva o destino do comentário em nova tentativa de senha e após a autenticação bem-sucedida.
                $next = LoginReturn::articleComments($data['next'] ?? null);
                if ($user === null) {
                    return $response->withHeader('Location', '/login'.($next === null ? '' : '?next='.rawurlencode($next)))->withStatus(302);
                }
                session_regenerate_id(true);
                $_SESSION['user'] = $user;
                // EN: A new identity gets a new CSRF token, so a token obtained before sign-in is useless afterwards.
                // PT: Uma nova identidade recebe um novo token CSRF; um token obtido antes do login deixa de valer.
                CsrfToken::rotate();
                unset($_SESSION['article_comment_flash']);
                return $response->withHeader('Location', $next ?? '/admin')->withStatus(302);
            });
        }
        $app->post('/logout', static function (ServerRequestInterface $request, ResponseInterface $response): ResponseInterface { $_SESSION = []; session_destroy(); return $response->withHeader('Location', '/')->withStatus(302); });
        $app->get('/admin', static function (ServerRequestInterface $request, ResponseInterface $response) use ($twig, $editorial): ResponseInterface {
            if (!Roles::canEdit($_SESSION['user'] ?? null)) return $response->withHeader('Location', '/login')->withStatus(302);
            $input = $request->getQueryParams()['q'] ?? '';
            $search = is_string($input) ? $input : '';

            return $twig->render($response, 'admin.twig', [
                'user' => $_SESSION['user'], 'catalog' => $editorial->catalog($search), 'search' => $search,
                'can_manage_appearance' => Roles::canManageUsers($_SESSION['user']),
            ])->withHeader('Cache-Control', 'no-store');
        });
        $app->get('/admin/translations', static function (ServerRequestInterface $request, ResponseInterface $response) use ($twig, $editorial,$artifacts): ResponseInterface {if(!Roles::canEdit($_SESSION['user'] ?? null))return$response->withHeader('Location','/login')->withStatus(302);$search=(string)($request->getQueryParams()['q']??'');return$twig->render($response,'translation-review.twig',['pages'=>$editorial->translationReview($search),'artifacts'=>$artifacts->translationReview($search),'search'=>$search]);});
        $app->get('/admin/packages', static function ($request,$response) use ($twig) {if(!Roles::canEdit($_SESSION['user'] ?? null))return$response->withHeader('Location','/login')->withStatus(302);return$twig->render($response,'packages.twig');});
        $app->post('/admin/packages/preview', static function (ServerRequestInterface $request,ResponseInterface $response) use ($twig,$packages) {if(!Roles::canEdit($_SESSION['user'] ?? null))return$response->withHeader('Location','/login')->withStatus(302);$file=$request->getUploadedFiles()['package']??null;if($file===null||$file->getError()!==UPLOAD_ERR_OK)return$twig->render($response,'packages.twig',['error'=>'Selecione um pacote ZIP válido.'])->withStatus(422);$content=(string)$file->getStream()->getContents();$preview=$packages->preview($content);$token=null;if($preview['valid']&&$preview['summary']['slug_conflicts']===0&&$preview['summary']['route_changes']===0&&$preview['summary']['unknown_blocks']===0){$token=bin2hex(random_bytes(16));$path=tempnam(sys_get_temp_dir(),'usinadocs-import-');if($path!==false){file_put_contents($path,$content);$_SESSION['package_previews'][$token]=$path;}}return$twig->render($response,'packages.twig',['preview'=>$preview,'filename'=>$file->getClientFilename(),'token'=>$token]);});
        $app->post('/admin/packages/import', static function (ServerRequestInterface $request,ResponseInterface $response) use ($twig,$packages) {if(!Roles::canEdit($_SESSION['user'] ?? null))return$response->withHeader('Location','/login')->withStatus(302);$data=(array)$request->getParsedBody();$token=(string)($data['token']??'');$strategy=(string)($data['strategy']??'create_only');$path=$_SESSION['package_previews'][$token]??null;unset($_SESSION['package_previews'][$token]);$resolved=is_string($path)?realpath($path):false;$temporary=realpath(sys_get_temp_dir());if($resolved===false||$temporary===false||!str_starts_with($resolved,$temporary.DIRECTORY_SEPARATOR)||!is_file($resolved))return$twig->render($response,'packages.twig',['error'=>'A prévia expirou. Envie o pacote novamente.'])->withStatus(422);try{$result=$packages->import((string)file_get_contents($resolved),$strategy);}finally{@unlink($resolved);}return$twig->render($response,'packages.twig',['result'=>$result]);});
        $app->get('/admin/pages/{id}/export', static function ($request,$response,array $a) use ($packages) {if(!Roles::canEdit($_SESSION['user'] ?? null))return$response->withHeader('Location','/login')->withStatus(302);$package=$packages->exportPublishedPage($a['id']);if($package===null)return$response->withStatus(404);$response->getBody()->write($package['content']);return$response->withHeader('Content-Type','application/zip')->withHeader('Content-Disposition','attachment; filename="'.$package['filename'].'"')->withHeader('Content-Length',(string)strlen($package['content']));});
        $app->get('/admin/models', static function ($request,$response) use ($twig,$editorial) {if(!Roles::canEdit($_SESSION['user'] ?? null))return$response->withHeader('Location','/login')->withStatus(302);return$twig->render($response,'models.twig',['models'=>$editorial->editorialModels()]);});
        $app->get('/admin/models/new', static function ($request,$response) use ($twig) {if(!Roles::canEdit($_SESSION['user'] ?? null))return$response->withHeader('Location','/login')->withStatus(302);return$twig->render($response,'model-form.twig',['artifacts'=>[['type'=>'text','label'=>'Texto','required'=>true]]]);});
        $app->post('/admin/models', static function (ServerRequestInterface $request,ResponseInterface $response) use ($editorial) {if(!Roles::canEdit($_SESSION['user'] ?? null))return$response->withHeader('Location','/login')->withStatus(302);$data=(array)$request->getParsedBody();$artifacts=json_decode((string)($data['artifacts_json']??'[]'),true);$id=$editorial->createEditorialModel((string)($data['content_type']??''),trim((string)($data['label']??'')),trim((string)($data['description']??'')),is_array($artifacts)?$artifacts:[]);return$response->withHeader('Location','/admin/models/'.$id.'/edit')->withStatus(302);});
        $app->post('/admin/models/{id}/revisions', static function ($request,$response,array $arguments) use ($editorial) {if(!Roles::canEdit($_SESSION['user'] ?? null))return$response->withHeader('Location','/login')->withStatus(302);$editorial->createEditorialModelRevision($arguments['id']);return$response->withHeader('Location','/admin/models/'.$arguments['id'].'/edit')->withStatus(302);});
        $app->get('/admin/models/{id}/edit', static function ($request,$response,array $arguments) use ($twig,$editorial) {if(!Roles::canEdit($_SESSION['user'] ?? null))return$response->withHeader('Location','/login')->withStatus(302);$model=$editorial->editorialModelDraft($arguments['id']);if($model===null)return$response->withStatus(404);return$twig->render($response,'model-form.twig',['model'=>$model,'artifacts'=>$model['artifacts']]);});
        $app->post('/admin/models/{id}', static function (ServerRequestInterface $request,ResponseInterface $response,array $arguments) use ($editorial) {if(!Roles::canEdit($_SESSION['user'] ?? null))return$response->withHeader('Location','/login')->withStatus(302);$data=(array)$request->getParsedBody();$artifacts=json_decode((string)($data['artifacts_json']??'[]'),true);$editorial->updateEditorialModelDraft($arguments['id'],trim((string)($data['label']??'')),trim((string)($data['description']??'')),is_array($artifacts)?$artifacts:[]);return$response->withHeader('Location','/admin/models')->withStatus(302);});
        $app->post('/admin/models/{id}/publish', static function ($request,$response,array $arguments) use ($editorial) {if(!Roles::canEdit($_SESSION['user'] ?? null))return$response->withHeader('Location','/login')->withStatus(302);$editorial->publishEditorialModel($arguments['id']);return$response->withHeader('Location','/admin/models')->withStatus(302);});
        $app->get('/admin/models/{id}/history', static function ($request,$response,array $a) use ($editorial,$twig) {if(!Roles::canEdit($_SESSION['user'] ?? null))return$response->withHeader('Location','/login')->withStatus(302);$history=$editorial->editorialModelHistory($a['id']);if($history===[])return$response->withStatus(404);$current=array_values(array_filter($history,static fn(array $revision):bool=>$revision['is_current']));return$twig->render($response,'model-history.twig',['model_id'=>$a['id'],'history'=>$history,'current'=>$current[0]??null]);});
        $app->get('/admin/models/{id}/compare', static function ($request,$response,array $a) use ($editorial,$twig) {if(!Roles::canEdit($_SESSION['user'] ?? null))return$response->withHeader('Location','/login')->withStatus(302);$q=$request->getQueryParams();$comparison=$editorial->compareEditorialModelRevisions($a['id'],(string)($q['from']??''),(string)($q['to']??''));if($comparison===null)return$response->withStatus(404);return$twig->render($response,'model-comparison.twig',['model_id'=>$a['id'],'comparison'=>$comparison]);});
        $app->post('/admin/models/{id}/restore/{revision}', static function ($request,$response,array $a) use ($editorial) {if(!Roles::canEdit($_SESSION['user'] ?? null))return$response->withHeader('Location','/login')->withStatus(302);$id=$editorial->restoreEditorialModelRevision($a['id'],$a['revision']);return$response->withHeader('Location',$id?'/admin/models/'.$a['id'].'/edit':'/admin/models/'.$a['id'].'/history')->withStatus(302);});
        $app->get('/admin/artifacts', static function ($request,$response) use ($twig,$artifacts) {if(!Roles::canEdit($_SESSION['user'] ?? null))return$response->withHeader('Location','/login')->withStatus(302);return$twig->render($response,'artifacts.twig',['artifacts'=>$artifacts->catalog()]);});
        $app->get('/admin/artifacts/new', static function ($request,$response) use ($twig) {if(!Roles::canEdit($_SESSION['user'] ?? null))return$response->withHeader('Location','/login')->withStatus(302);return$twig->render($response,'artifact-form.twig',['data'=>[]]);});
        $app->post('/admin/artifacts', static function (ServerRequestInterface $request,ResponseInterface $response) use ($artifacts) {if(!Roles::canEdit($_SESSION['user'] ?? null))return$response->withHeader('Location','/login')->withStatus(302);$body=(array)$request->getParsedBody();$data=json_decode((string)($body['data_json']??'{}'),true);$id=$artifacts->create(trim((string)($body['slug']??'')),(string)($body['artifact_type']??'text'),trim((string)($body['title']??'')),trim((string)($body['description']??'')),is_array($data)?$data:[]);return$response->withHeader('Location','/admin/artifacts/'.$id.'/edit')->withStatus(302);});
        $app->get('/admin/artifacts/{id}/edit', static function ($request,$response,array $arguments) use ($twig,$artifacts) {if(!Roles::canEdit($_SESSION['user'] ?? null))return$response->withHeader('Location','/login')->withStatus(302);$artifact=$artifacts->draft($arguments['id']);if($artifact===null)return$response->withStatus(404);return$twig->render($response,'artifact-form.twig',['artifact'=>$artifact,'data'=>$artifact['data']]);});
        $app->get('/admin/artifacts/{id}/usages', static function ($request,$response,array $arguments) use ($twig,$artifacts) {if(!Roles::canEdit($_SESSION['user'] ?? null))return$response->withHeader('Location','/login')->withStatus(302);$catalog=array_values(array_filter($artifacts->catalog(),static fn(array $artifact):bool=>$artifact['id']===$arguments['id']));if($catalog===[])return$response->withStatus(404);return$twig->render($response,'artifact-usages.twig',['artifact'=>$catalog[0],'usages'=>$artifacts->usages($arguments['id'])]);});
        $app->get('/admin/artifacts/{id}/history/{language}', static function ($request,$response,array $a) use ($twig,$artifacts) {if(!Roles::canEdit($_SESSION['user'] ?? null))return$response->withHeader('Location','/login')->withStatus(302);$history=$artifacts->history($a['id'],$a['language']);if($history===[])return$response->withStatus(404);$current=array_values(array_filter($history,static fn(array $revision):bool=>$revision['is_current']));return$twig->render($response,'artifact-history.twig',['artifact_id'=>$a['id'],'language'=>$a['language'],'history'=>$history,'current'=>$current[0]??null]);});
        $app->get('/admin/artifacts/{id}/compare/{language}', static function ($request,$response,array $a) use ($twig,$artifacts) {if(!Roles::canEdit($_SESSION['user'] ?? null))return$response->withHeader('Location','/login')->withStatus(302);$q=$request->getQueryParams();$comparison=$artifacts->compareRevisions($a['id'],$a['language'],(string)($q['from']??''),(string)($q['to']??''));if($comparison===null)return$response->withStatus(404);return$twig->render($response,'artifact-comparison.twig',['artifact_id'=>$a['id'],'language'=>$a['language'],'comparison'=>$comparison]);});
        $app->post('/admin/artifacts/{id}/restore/{language}/{revision}', static function ($request,$response,array $a) use ($artifacts) {if(!Roles::canEdit($_SESSION['user'] ?? null))return$response->withHeader('Location','/login')->withStatus(302);$id=$artifacts->restoreRevision($a['id'],$a['language'],$a['revision']);$path=$a['language']==='pt'?'/admin/artifacts/'.$a['id'].'/edit':'/admin/artifacts/'.$a['id'].'/'.$a['language'].'/edit';return$response->withHeader('Location',$id?$path:'/admin/artifacts/'.$a['id'].'/history/'.$a['language'])->withStatus(302);});
        $app->post('/admin/artifacts/{id}', static function (ServerRequestInterface $request,ResponseInterface $response,array $arguments) use ($artifacts) {if(!Roles::canEdit($_SESSION['user'] ?? null))return$response->withHeader('Location','/login')->withStatus(302);$body=(array)$request->getParsedBody();$data=json_decode((string)($body['data_json']??'{}'),true);$artifacts->updateDraft($arguments['id'],trim((string)($body['title']??'')),trim((string)($body['description']??'')),is_array($data)?$data:[]);return$response->withHeader('Location','/admin/artifacts/'.$arguments['id'].'/edit')->withStatus(302);});
        $app->post('/admin/artifacts/{id}/publish', static function ($request,$response,array $arguments) use ($artifacts) {if(!Roles::canEdit($_SESSION['user'] ?? null))return$response->withHeader('Location','/login')->withStatus(302);$artifacts->publish($arguments['id']);return$response->withHeader('Location','/admin/artifacts')->withStatus(302);});
        $app->post('/admin/artifacts/{id}/revisions', static function ($request,$response,array $arguments) use ($artifacts) {if(!Roles::canEdit($_SESSION['user'] ?? null))return$response->withHeader('Location','/login')->withStatus(302);$artifacts->createRevision($arguments['id']);return$response->withHeader('Location','/admin/artifacts/'.$arguments['id'].'/edit')->withStatus(302);});
        $app->post('/admin/artifacts/{id}/translations/{language}', static function ($request,$response,array $arguments) use ($artifacts) {if(!Roles::canEdit($_SESSION['user'] ?? null))return$response->withHeader('Location','/login')->withStatus(302);$artifacts->createTranslation($arguments['id'],$arguments['language']);return$response->withHeader('Location','/admin/artifacts/'.$arguments['id'].'/'.$arguments['language'].'/edit')->withStatus(302);});
        $app->post('/admin/artifacts/{id}/translations/{language}/refresh', static function ($request,$response,array $arguments) use ($artifacts) {if(!Roles::canEdit($_SESSION['user'] ?? null))return$response->withHeader('Location','/login')->withStatus(302);$revision=$artifacts->refreshTranslation($arguments['id'],$arguments['language']);$path=$revision?'/admin/artifacts/'.$arguments['id'].'/'.$arguments['language'].'/edit':'/admin/translations';return$response->withHeader('Location',$path)->withStatus(302);});
        $app->get('/admin/artifacts/{id}/{language}/edit', static function ($request,$response,array $arguments) use ($twig,$artifacts) {if(!Roles::canEdit($_SESSION['user'] ?? null))return$response->withHeader('Location','/login')->withStatus(302);$artifact=$artifacts->translationDraft($arguments['id'],$arguments['language']);if($artifact===null)return$response->withStatus(404);return$twig->render($response,'artifact-translation-form.twig',['artifact'=>$artifact,'data'=>$artifact['data']]);});
        $app->post('/admin/artifacts/{id}/{language}', static function (ServerRequestInterface $request,ResponseInterface $response,array $arguments) use ($artifacts) {if(!Roles::canEdit($_SESSION['user'] ?? null))return$response->withHeader('Location','/login')->withStatus(302);$body=(array)$request->getParsedBody();$data=json_decode((string)($body['data_json']??'{}'),true);$artifacts->updateTranslationDraft($arguments['id'],$arguments['language'],trim((string)($body['title']??'')),trim((string)($body['description']??'')),is_array($data)?$data:[]);return$response->withHeader('Location','/admin/artifacts/'.$arguments['id'].'/'.$arguments['language'].'/edit')->withStatus(302);});
        $app->post('/admin/artifacts/{id}/{language}/publish', static function ($request,$response,array $arguments) use ($artifacts) {if(!Roles::canEdit($_SESSION['user'] ?? null))return$response->withHeader('Location','/login')->withStatus(302);$artifacts->publishTranslation($arguments['id'],$arguments['language']);return$response->withHeader('Location','/admin/artifacts')->withStatus(302);});
        $app->post('/admin/artifacts/{id}/{language}/revisions', static function ($request,$response,array $arguments) use ($artifacts) {if(!Roles::canEdit($_SESSION['user'] ?? null))return$response->withHeader('Location','/login')->withStatus(302);$artifacts->createTranslationRevision($arguments['id'],$arguments['language']);return$response->withHeader('Location','/admin/artifacts/'.$arguments['id'].'/'.$arguments['language'].'/edit')->withStatus(302);});
        $app->get('/admin/media', static function ($request,$response) use ($twig,$media) {if(!Roles::canEdit($_SESSION['user'] ?? null))return$response->withHeader('Location','/login')->withStatus(302);return$twig->render($response,'media.twig',['media'=>$media->all()]);});
        $app->post('/admin/media', static function (ServerRequestInterface $request,ResponseInterface $response) use ($media) {if(!Roles::canEdit($_SESSION['user'] ?? null))return$response->withHeader('Location','/login')->withStatus(302);$d=(array)$request->getParsedBody();$file=$request->getUploadedFiles()['file']??null;if($file===null||$file->getError()!==UPLOAD_ERR_OK)return$response->withHeader('Location','/admin/media')->withStatus(302);$path=tempnam(sys_get_temp_dir(),'usinadocs-media-');$file->moveTo($path);try{$media->store($path,$file->getClientFilename()??'image',(string)($d['title']??''),(string)($d['source_url']??''),(string)($d['license_note']??''));}finally{@unlink($path);}return$response->withHeader('Location','/admin/media')->withStatus(302);});
        $app->post('/admin/pages/{id}/publish', static function ($request,$response,array $a) use ($editorial) { if(!Roles::canEdit($_SESSION['user'] ?? null)) return $response->withHeader('Location','/login')->withStatus(302);$missing=$editorial->missingRequiredArtifacts($a['id']);if($missing!==[])return$response->withHeader('Location','/admin/pages/'.$a['id'].'/edit?missing='.rawurlencode(implode(', ',$missing)))->withStatus(302);$editorial->publish($a['id']); return $response->withHeader('Location','/admin')->withStatus(302); });
        $app->post('/admin/pages/{id}/revisions', static function ($request,$response,array $a) use ($editorial) { if(!Roles::canEdit($_SESSION['user'] ?? null)) return $response->withHeader('Location','/login')->withStatus(302); $editorial->createRevision($a['id']); return $response->withHeader('Location','/admin/pages/'.$a['id'].'/edit')->withStatus(302); });
        $app->post('/admin/pages/{id}/revisions/{language}', static function ($request,$response,array $a) use ($editorial) {if(!Roles::canEdit($_SESSION['user'] ?? null))return$response->withHeader('Location','/login')->withStatus(302);$editorial->createLocalizedRevision($a['id'],$a['language']);$path=$a['language']==='pt'?'/admin/pages/'.$a['id'].'/edit':'/admin/pages/'.$a['id'].'/'.$a['language'].'/edit';return$response->withHeader('Location',$path)->withStatus(302);});
        $app->get('/admin/pages/{id}/compare/{language}', static function ($request,$response,array $a) use ($editorial,$twig) {if(!Roles::canEdit($_SESSION['user'] ?? null))return$response->withHeader('Location','/login')->withStatus(302);$query=$request->getQueryParams();$comparison=$editorial->compareRevisions($a['id'],$a['language'],(string)($query['from']??''),(string)($query['to']??''));if($comparison===null)return$response->withStatus(404);return$twig->render($response,'page-comparison.twig',['page_id'=>$a['id'],'language'=>$a['language'],'comparison'=>$comparison]);});
        $app->post('/admin/pages/{id}/restore/{language}/{revision}', static function ($request,$response,array $a) use ($editorial) {if(!Roles::canEdit($_SESSION['user'] ?? null))return$response->withHeader('Location','/login')->withStatus(302);$restored=$editorial->restoreRevision($a['id'],$a['language'],$a['revision']);if($restored===null)return$response->withHeader('Location','/admin/pages/'.$a['id'].'/history/'.$a['language'].'?restore=blocked')->withStatus(302);$path=$a['language']==='pt'?'/admin/pages/'.$a['id'].'/edit':'/admin/pages/'.$a['id'].'/'.$a['language'].'/edit';return$response->withHeader('Location',$path)->withStatus(302);});
        $app->post('/admin/pages/{id}/translations/{language}', static function (ServerRequestInterface $request,ResponseInterface $response,array $a) use ($editorial) { if(!Roles::canEdit($_SESSION['user'] ?? null))return$response->withHeader('Location','/login')->withStatus(302);$d=(array)$request->getParsedBody();$editorial->createTranslation($a['id'],$a['language'],trim((string)$d['slug']));return$response->withHeader('Location','/admin/pages/'.$a['id'].'/'.$a['language'].'/edit')->withStatus(302);});
        $app->post('/admin/pages/{id}/translations/{language}/refresh', static function ($request,$response,array $a) use ($editorial) {if(!Roles::canEdit($_SESSION['user'] ?? null))return$response->withHeader('Location','/login')->withStatus(302);$revision=$editorial->refreshTranslation($a['id'],$a['language']);if($revision===null)return$response->withHeader('Location','/admin/translations')->withStatus(302);return$response->withHeader('Location','/admin/pages/'.$a['id'].'/'.$a['language'].'/edit')->withStatus(302);});
        $app->get('/admin/pages/{id}/translations/{language}/new', static function ($request,$response,array $a) use ($twig) {if(!Roles::canEdit($_SESSION['user'] ?? null))return$response->withHeader('Location','/login')->withStatus(302);if(!in_array($a['language'],['en','es'],true))return$response->withStatus(404);return$twig->render($response,'translation-form.twig',['page_id'=>$a['id'],'language'=>$a['language']]);});
        $app->post('/admin/pages/{id}/{language}/publish', static function ($request,$response,array $a) use ($editorial) {if(!Roles::canEdit($_SESSION['user'] ?? null))return$response->withHeader('Location','/login')->withStatus(302);$missing=$editorial->missingRequiredArtifacts($a['id'],$a['language']);if($missing!==[])return$response->withHeader('Location','/admin/pages/'.$a['id'].'/'.$a['language'].'/edit?missing='.rawurlencode(implode(', ',$missing)))->withStatus(302);$editorial->publishLocalized($a['id'],$a['language']);return$response->withHeader('Location','/admin')->withStatus(302);});

        return $app;
    }
}
