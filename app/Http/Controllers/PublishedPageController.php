<?php

namespace App\Http\Controllers;

use App\Models\Page;
use Illuminate\View\View;

class PublishedPageController extends Controller
{
    public function show(string $slug): View
    {
        $page = Page::query()
            ->where('slug', $slug)
            ->with(['publishedRevision.blocks', 'publishedRevision.language'])
            ->firstOrFail();

        abort_unless($page->publishedRevision, 404);

        return view('page', ['revision' => $page->publishedRevision]);
    }
}
