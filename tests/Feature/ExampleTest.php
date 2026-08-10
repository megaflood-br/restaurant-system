<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExampleTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_application_redirects_root_to_the_public_menu(): void
    {
        $response = $this->get('/');

        $response->assertRedirect('/cardapio');
    }

    public function test_the_public_menu_returns_a_successful_response(): void
    {
        $response = $this->get('/cardapio');

        $response->assertOk();
    }
}
