<?php

namespace App\Services;

use App\Models\Language;
use App\Models\Page;
use App\Models\PageRevision;
use Illuminate\Support\Facades\DB;

class PageRevisionService
{
    public function createDraft(Page $page, Language $language, string $title, ?string $summary, array $blocks): PageRevision
    {
        return DB::transaction(function () use ($page, $language, $title, $summary, $blocks): PageRevision {
            $number = ((int) $page->revisions()->max('number')) + 1;
            $revision = $page->revisions()->create([
                'language_id' => $language->id,
                'number' => $number,
                'status' => PageRevision::DRAFT,
                'title' => $title,
                'summary' => $summary,
            ]);

            foreach ($blocks as $position => $block) {
                $revision->blocks()->create([
                    'type' => $block['type'],
                    'position' => $position + 1,
                    'data' => $block['data'],
                ]);
            }

            return $revision->load('blocks');
        });
    }

    public function publish(PageRevision $revision): PageRevision
    {
        return DB::transaction(function () use ($revision): PageRevision {
            $revision->update([
                'status' => PageRevision::PUBLISHED,
                'published_at' => now(),
            ]);

            $revision->page()->update(['published_revision_id' => $revision->id]);

            return $revision->fresh(['blocks', 'page']);
        });
    }
}
