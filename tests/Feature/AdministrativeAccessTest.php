<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AdministrativeAccessTest extends TestCase
{
    use RefreshDatabase;

    public function test_visitors_are_redirected_to_login_from_administration(): void
    {
        $this->get('/admin')->assertRedirect('/login');
    }

    public function test_an_editor_cannot_access_administration(): void
    {
        $editor = User::create([
            'name' => 'Editor',
            'email' => 'editor@example.test',
            'role' => 'editor',
            'password' => Hash::make('correct horse battery staple'),
        ]);

        $this->actingAs($editor)->get('/admin')->assertForbidden();
    }

    public function test_administrator_can_sign_in_and_access_administration(): void
    {
        $administrator = User::create([
            'name' => 'Administrator',
            'email' => 'admin@example.test',
            'role' => 'administrator',
            'password' => Hash::make('correct horse battery staple'),
        ]);

        $this->post('/login', [
            'email' => $administrator->email,
            'password' => 'correct horse battery staple',
        ])->assertRedirect('/admin');

        $this->assertAuthenticatedAs($administrator);
        $this->get('/admin')->assertOk()->assertSee('Base administrativa pronta.');
    }
}
