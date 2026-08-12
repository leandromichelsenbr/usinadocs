<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Language;
use App\Models\Page;
use App\Models\PageRevision;
use App\Models\PageLocalization;
use App\Models\Site;
use App\Services\PageRevisionService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class PageController extends Controller
{
    public function index(): View
    {
        return view('admin.pages.index', [
            'pages' => Page::with(['site', 'localizations.language', 'localizations.publishedRevision'])->orderBy('slug')->get(),
        ]);
    }

    public function create(): View
    {
        return view('admin.pages.create', [
            'sites' => Site::orderBy('name')->get(),
            'languages' => Language::orderBy('name')->get(),
        ]);
    }

    public function store(Request $request, PageRevisionService $service): RedirectResponse
    {
        $data = $this->validatedPayload($request);
        $page = Page::create(['site_id' => $data['site_id'], 'slug' => $data['slug']]);
        $language = Language::findOrFail($data['language_id']);
        $page->localizations()->create(['language_id'=>$language->id,'slug'=>$data['slug']]);
        $revision = $service->createDraft($page, $language, $data['title'], $data['summary'], $data['blocks']);

        return redirect()->route('admin.pages.edit', [$page, $revision])->with('success', 'Página criada como rascunho.');
    }

    public function createTranslation(Page $page): View { return view('admin.pages.translation', ['page'=>$page,'languages'=>Language::whereDoesntHave('localizations',fn($q)=>$q->where('page_id',$page->id))->get()]); }
    public function storeTranslation(Request $request, Page $page, PageRevisionService $service): RedirectResponse {
        $data=$this->validatedPayload($request,false); $request->validate(['slug'=>['required','alpha_dash','max:120',Rule::unique('page_localizations','slug')->where('language_id',$data['language_id'])]]);
        $language=Language::findOrFail($data['language_id']); $page->localizations()->create(['language_id'=>$language->id,'slug'=>$request->string('slug')]);
        $revision=$service->createDraft($page,$language,$data['title'],$data['summary'],$data['blocks']); return redirect()->route('admin.pages.edit',[$page,$revision]); }

    public function edit(Page $page, PageRevision $revision): View
    {
        abort_unless($revision->page_id === $page->id, 404);
        abort_if($revision->status === PageRevision::PUBLISHED, 409, 'Published revisions cannot be edited. Create a new revision instead.');

        return view('admin.pages.edit', [
            'page' => $page,
            'revision' => $revision->load('blocks'),
            'languages' => Language::orderBy('name')->get(),
        ]);
    }

    public function update(Request $request, Page $page, PageRevision $revision, PageRevisionService $service): RedirectResponse
    {
        abort_unless($revision->page_id === $page->id, 404);
        $data = $this->validatedPayload($request, false);
        $service->updateDraft($revision, Language::findOrFail($data['language_id']), $data['title'], $data['summary'], $data['blocks']);

        return back()->with('success', 'Rascunho atualizado.');
    }

    public function publish(Page $page, PageRevision $revision, PageRevisionService $service): RedirectResponse
    {
        abort_unless($revision->page_id === $page->id, 404);
        abort_if($revision->status === PageRevision::PUBLISHED, 409);
        $service->publish($revision);

        return redirect()->route('admin.pages.index')->with('success', 'Revisão publicada.');
    }

    public function newRevision(Page $page, PageRevisionService $service): RedirectResponse
    {
        $source = $page->publishedRevision;
        abort_unless($source, 422, 'A page needs a published revision before a new revision can be opened.');
        $revision = $service->createDraftFrom($page, $source->load('blocks'));

        return redirect()->route('admin.pages.edit', [$page, $revision])->with('success', 'Nova revisão criada a partir da publicação atual.');
    }

    private function validatedPayload(Request $request, bool $creating = true): array
    {
        $siteId = $request->integer('site_id');
        $rules = [
            'language_id' => ['required', 'integer', Rule::exists('languages', 'id')],
            'title' => ['required', 'string', 'max:255'],
            'summary' => ['nullable', 'string'],
            'blocks' => ['array', 'max:6'],
            'blocks.*.type' => ['nullable', Rule::in(['text', 'code', 'reference'])],
            'blocks.*.title' => ['nullable', 'string', 'max:255'],
            'blocks.*.body' => ['nullable', 'string'],
            'blocks.*.code' => ['nullable', 'string'],
        ];

        if ($creating) {
            $rules['site_id'] = ['required', 'integer', Rule::exists('sites', 'id')];
            $rules['slug'] = ['required', 'alpha_dash', 'max:120', Rule::unique('pages', 'slug')->where('site_id', $siteId)];
        }

        $data = $request->validate($rules);
        $data['summary'] = $data['summary'] ?? null;
        $data['blocks'] = $this->normaliseBlocks($data['blocks'] ?? []);

        return $data;
    }

    private function normaliseBlocks(array $blocks): array
    {
        return collect($blocks)->map(function (array $block): ?array {
            $type = $block['type'] ?? null;
            if (! $type) {
                return null;
            }

            if ($type === 'code') {
                return filled($block['code'] ?? null) ? ['type' => 'code', 'data' => ['code' => $block['code']]] : null;
            }

            return filled($block['title'] ?? null) || filled($block['body'] ?? null)
                ? ['type' => $type, 'data' => ['title' => $block['title'] ?? '', 'body' => $block['body'] ?? '']]
                : null;
        })->filter()->values()->all();
    }
}
