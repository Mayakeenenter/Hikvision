<?php

namespace Tests\Feature;

use Tests\TestCase;

class EnsureHttpsTest extends TestCase
{
    public function test_it_rejects_http_requests(): void
    {
        $response = $this->get('http://localhost/');

        $response->assertStatus(403);
    }

    public function test_it_allows_https_requests(): void
    {
        $response = $this->get('https://localhost/');

        $response->assertStatus(200);
    }
}
