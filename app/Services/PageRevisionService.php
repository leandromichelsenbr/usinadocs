<?php

namespace App\Services;

use App\Models\Language;
use App\Models\Page;
use App\Models\PageRevision;
use App\Models\PageLocalization;
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

            PageLocalization::firstOrCreate(['page_id'=>$revision->page_id,'language_id'=>$revision->language_id],['slug'=>$revision->page->slug])->update(['published_revision_id' => $revision->id]);
            $revision->page()->update(['published_revision_id' => $revision->id]);

            return $revision->fresh(['blocks', 'page']);
        });
    }

    public function updateDraft(PageRevision $revision, Language $language, string $title, ?string $summary, array $blocks): PageRevision
    {
        abort_if($revision->status === PageRevision::PUBLISHED, 409, 'Published revisions cannot be edited.');

        return DB::transaction(function () use ($revision, $language, $title, $summary, $blocks): PageRevision {
            $revision->update([
                'language_id' => $language->id,
                'title' => $title,
                'summary' => $summary,
            ]);
            $revision->blocks()->delete();

            foreach ($blocks as $position => $block) {
                $revision->blocks()->create([
                    'type' => $block['type'],
                    'position' => $position + 1,
                    'data' => $block['data'],
                ]);
            }

            return $revision->fresh('blocks');
        });
    }

    public function createDraftFrom(Page $page, PageRevision $source): PageRevision
    {
        return $this->createDraft(
            $page,
            $source->language,
            $source->title,
            $source->summary,
            $source->blocks->map(fn ($block) => ['type' => $block->type, 'data' => $block->data])->all(),
        );
    }
}
