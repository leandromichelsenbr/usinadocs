<?php

namespace Tests\Feature;

// use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExampleTest extends TestCase
{
    /**
     * A basic test example.
     */
    public function test_the_public_demonstration_page_is_available(): void
    {
        $response = $this->get('/demonstracao');

        $response->assertStatus(200);
        $response->assertSee('Uma página, muitos usos');
    }
}
