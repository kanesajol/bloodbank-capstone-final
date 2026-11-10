<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\URL;
use Tests\TestCase;

class ExampleTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        URL::forceRootUrl('http://localhost');
    }

    /**
     * A basic test example.
     */
    public function test_the_application_returns_a_successful_response(): void
    {
        $response = $this->get('/');

        $response->assertOk()->assertSee('Staff Login');
    }

    public function test_the_login_page_is_available_at_the_login_path(): void
    {
        $response = $this->get('/login');

        $response->assertOk()->assertSee('Staff Login');
    }
}
