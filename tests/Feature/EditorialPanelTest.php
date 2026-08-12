<?php

namespace Tests\Feature;

use App\Models\Language;
use App\Models\Page;
use App\Models\Site;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class EditorialPanelTest extends TestCase
{
    use RefreshDatabase;

    public function test_administrator_can_create_and_publish_a_page(): void
    {
        $administrator = User::create([
            'name' => 'Administrator',
            'email' => 'admin@example.test',
            'role' => 'administrator',
            'password' => Hash::make('correct horse battery staple'),
        ]);
        $site = Site::create(['name' => 'Example', 'slug' => 'example']);
        $language = Language::create(['code' => 'en', 'route_key' => 'en', 'name' => 'English', 'native_name' => 'English']);

        $response = $this->actingAs($administrator)->post('/admin/pages', [
            'site_id' => $site->id,
            'slug' => 'first-page',
            'language_id' => $language->id,
            'title' => 'First page',
            'summary' => 'Created through the panel.',
            'blocks' => [
                ['type' => 'text', 'title' => 'Introduction', 'body' => 'Visible after publication.'],
            ],
        ]);

        $page = Page::where('slug', 'first-page')->firstOrFail();
        $revision = $page->revisions()->firstOrFail();
        $response->assertRedirect(route('admin.pages.edit', [$page, $revision]));
        $this->get('/en/p/first-page')->assertNotFound();

        $this->actingAs($administrator)->post(route('admin.pages.publish', [$page, $revision]))->assertRedirect(route('admin.pages.index'));
        $this->get('/en/p/first-page')->assertOk()->assertSee('First page')->assertSee('Visible after publication.');
    }

    public function test_visitors_cannot_access_editorial_routes(): void
    {
        $this->get('/admin/pages')->assertRedirect('/login');
    }
}
