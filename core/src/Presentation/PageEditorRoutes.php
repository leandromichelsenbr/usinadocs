<?php

declare(strict_types=1);

namespace UsinaDocs\Core\Presentation;

use InvalidArgumentException;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Slim\App;
use Slim\Views\Twig;
use UsinaDocs\Core\Authentication\Roles;
use UsinaDocs\Core\Content\ArticleSections;
use UsinaDocs\Core\Content\EditorialService;
use UsinaDocs\Core\Content\ReusableArtifactService;
use UsinaDocs\Core\Media\MediaLibrary;

final class PageEditorRoutes
{
    public static function register(
        App $app, Twig $twig, EditorialService $editorial, ReusableArtifactService $artifacts,
        MediaLibrary $media, ArticleSections $sections,
    ): void {
        // The same form serves new pages and each draft edition, while interface language is a separate preference.
        // O mesmo formulário atende páginas novas e cada edição em rascunho. O idioma da interface é uma preferência separada.
        $form = static function (ServerRequestInterface $request, ResponseInterface $response, array $args, array $submitted = []) use ($twig, $editorial, $artifacts, $media, $sections): ResponseInterface {
            if (!Roles::canEdit($_SESSION['user'] ?? null)) return $response->withHeader('Location', '/login')->withStatus(302);

            $id = $args['id'] ?? null;
            $language = $args['language'] ?? 'pt';
            $page = $id === null ? null : (isset($args['language'])
                ? $editorial->localizedDraft($id, $language)
                : $editorial->draft($id));
            if ($id !== null && $page === null) return $response->withStatus(404);
            $query = $request->getQueryParams();
            $missing = is_string($query['missing'] ?? null) ? $query['missing'] : '';
            $failed = $submitted !== [] || ($query['section_error'] ?? null) === '1';

            return $twig->render($response, 'page-form.twig', [
                'page' => $page, 'media' => $media->all(), 'models' => $editorial->editorialModels(),
                'reusable_artifacts' => $artifacts->publishedCatalog($language),
                'blocks' => $submitted['blocks'] ?? ($id === null ? [] : $editorial->blocksForDraft($id, $language)),
                'submitted' => $submitted,
                'missing' => $missing, 'section_error' => $failed,
                'sections' => $sections->availableFor($id), 'selected_section' => $id === null ? null : $sections->sectionFor($id),
            ])->withHeader('Cache-Control', 'no-store');
        };

        $save = static function (ServerRequestInterface $request, ResponseInterface $response, array $args) use ($editorial, $sections, $form): ResponseInterface {
            if (!Roles::canEdit($_SESSION['user'] ?? null)) return $response->withHeader('Location', '/login')->withStatus(302);

            $id = $args['id'] ?? null;
            $language = $args['language'] ?? 'pt';
            if ($id !== null && $editorial->localizedDraft($id, $language) === null) return $response->withStatus(404);

            $input = (array) $request->getParsedBody();
            $title = trim((string) ($input['title'] ?? ''));
            $summary = trim((string) ($input['summary'] ?? ''));
            $contentType = (string) ($input['content_type'] ?? 'reference');
            $section = $input['article_section'] ?? $input['news_section'] ?? null;
            $slug = trim((string) ($input['slug'] ?? ''));
            $blocks = json_decode((string) ($input['blocks_json'] ?? '[]'), true);
            $editPath = static fn (?string $pageId): string => $pageId === null ? '/admin/pages/new'
                : '/admin/pages/'.$pageId.(isset($args['language']) ? '/'.$language : '').'/edit';

            // An unavailable section must not cause an otherwise normal submission to overwrite the current section.
            // Uma editoria indisponível não deve fazer um envio normal substituir o vínculo atual.
            if ($contentType === 'article' && $section !== null
                && (!is_string($section) || !$sections->canAssign($id, $section))) {
                return $form($request, $response->withStatus(422), $args, [
                    'title' => $title, 'summary' => $summary, 'slug' => $slug,
                    'content_type' => $contentType, 'blocks' => is_array($blocks) ? $blocks : [],
                ]);
            }

            if ($id === null) {
                $id = $editorial->create($title, $slug, $summary, '', '', '', $contentType);
            } else {
                $editorial->setContentType($id, $contentType);
                $editorial->updateLocalizedDraft($id, $language, $title, $summary, '', '');
            }
            if (is_array($blocks)) $editorial->replaceDraftBlocks($id, $language, $blocks);

            if ($contentType === 'article' && is_string($section)) {
                try {
                    $sections->assign($id, $section);
                } catch (InvalidArgumentException $error) {
                    // If the section was blocked during the save, keep the draft and its previous classification.
                    // Se a editoria foi bloqueada durante a gravação, preserva o rascunho e sua classificação anterior.
                    return $response->withHeader('Location', $editPath($id).'?section_error=1')->withStatus(303);
                }
            }

            return $response->withHeader('Location', isset($args['language']) ? $editPath($id) : '/admin')->withStatus(302);
        };

        $app->get('/admin/pages/new', $form);
        $app->get('/admin/pages/{id}/edit', $form);
        $app->get('/admin/pages/{id}/{language:pt|en|es}/edit', $form);
        $app->post('/admin/pages', $save);
        $app->post('/admin/pages/{id}', $save);
        $app->post('/admin/pages/{id}/{language:pt|en|es}', $save);
    }
}
