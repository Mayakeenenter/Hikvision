<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EnsureHttpsTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_allows_https_requests(): void
    {
        $response = $this->get('https://localhost/');

        $response->assertStatus(200);
    }
}
