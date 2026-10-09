<?php

// Checks that the API never answers a cross-origin request with CORS headers (config/cors.php,
// work pack K-19): the web app is same-origin, so a browser on any other site is always blocked.
// Written as a PHPUnit class: PHPStan can only type $this->getJson() inside a TestCase class.

declare(strict_types=1);

namespace Tests\Feature\Security;

use Tests\TestCase;

class CorsTest extends TestCase
{
    private const OTHER_WEBSITE = 'https://other-website.example';

    public function test_an_api_request_from_another_website_gets_no_cors_header(): void
    {
        $response = $this->withHeader('Origin', self::OTHER_WEBSITE)->getJson('/api/v1/flags');

        $response->assertHeaderMissing('Access-Control-Allow-Origin');
    }

    public function test_a_preflight_request_from_another_website_gets_no_cors_header(): void
    {
        $response = $this->withHeaders([
            'Origin' => self::OTHER_WEBSITE,
            'Access-Control-Request-Method' => 'POST',
        ])->options('/api/v1/flags');

        $response->assertHeaderMissing('Access-Control-Allow-Origin');
    }
}
