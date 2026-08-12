<?php

namespace Tests\Feature;

use App\Models\Language;
use App\Models\Page;
use App\Models\Site;
use App\Services\PageRevisionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use LogicException;
use Tests\TestCase;

class PageRevisionTest extends TestCase
{
    use RefreshDatabase;

    public function test_published_revisions_and_their_blocks_are_immutable(): void
    {
        [$page, $language] = $this->pageContext();
        $service = app(PageRevisionService::class);
        $revision = $service->publish($service->createDraft($page, $language, 'First version', null, [
            ['type' => 'text', 'data' => ['title' => 'A title', 'body' => 'A body']],
        ]));

        $this->expectException(LogicException::class);
        $revision->update(['title' => 'Changed version']);
    }

    public function test_a_new_draft_preserves_the_published_revision(): void
    {
        [$page, $language] = $this->pageContext();
        $service = app(PageRevisionService::class);
        $published = $service->publish($service->createDraft($page, $language, 'First version', null, []));
        $draft = $service->createDraft($page, $language, 'Second version', null, []);

        $this->assertSame(1, $published->number);
        $this->assertSame(2, $draft->number);
        $this->assertSame($published->id, $page->fresh()->published_revision_id);
    }

    public function test_blocks_in_published_revisions_are_immutable(): void
    {
        [$page, $language] = $this->pageContext();
        $service = app(PageRevisionService::class);
        $revision = $service->publish($service->createDraft($page, $language, 'First version', null, [
            ['type' => 'text', 'data' => ['title' => 'A title', 'body' => 'A body']],
        ]));

        $this->expectException(LogicException::class);
        $revision->blocks->first()->update(['data' => ['title' => 'Changed', 'body' => 'Changed']]);
    }

    public function test_drafts_are_not_visible_on_the_public_route(): void
    {
        [$page, $language] = $this->pageContext();
        app(PageRevisionService::class)->createDraft($page, $language, 'Unpublished', null, []);

        $this->get('/p/example-page')->assertNotFound();
    }

    private function pageContext(): array
    {
        $site = Site::create(['name' => 'Example', 'slug' => 'example']);
        $language = Language::create(['code' => 'en', 'name' => 'English', 'native_name' => 'English']);

        return [Page::create(['site_id' => $site->id, 'slug' => 'example-page']), $language];
    }
}
