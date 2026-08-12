<?php

namespace Tests\Feature;

use App\Models\Language;
use App\Models\Page;
use App\Models\Site;
use App\Services\PageRevisionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExampleTest extends TestCase
{
    use RefreshDatabase;

    /**
     * A basic test example.
     */
    public function test_the_public_demonstration_page_is_available(): void
    {
        $site = Site::create(['name' => 'Example', 'slug' => 'example']);
        $language = Language::create(['code' => 'en', 'name' => 'English', 'native_name' => 'English']);
        $page = Page::create(['site_id' => $site->id, 'slug' => 'welcome']);
        $revision = app(PageRevisionService::class)->createDraft($page, $language, 'Welcome', 'A public page.', [
            ['type' => 'text', 'data' => ['title' => 'Start here', 'body' => 'Published content is visible.']],
        ]);
        app(PageRevisionService::class)->publish($revision);

        $response = $this->get('/p/welcome');

        $response->assertStatus(200);
        $response->assertSee('Welcome');
    }
}
