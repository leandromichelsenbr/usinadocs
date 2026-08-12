<?php

namespace App\Http\Controllers;

use App\Models\PageLocalization;
use Illuminate\View\View;

class PublishedPageController extends Controller
{
    public function show(string $locale, string $slug): View
    {
        $page = PageLocalization::query()
            ->where('slug', $slug)
            ->whereHas('language', fn ($q) => $q->where('route_key', $locale))
            ->with(['publishedRevision.blocks', 'publishedRevision.language','page.localizations.language'])
            ->firstOrFail();

        abort_unless($page->publishedRevision, 404);

        return view('page', ['revision' => $page->publishedRevision, 'localization' => $page]);
    }
}
