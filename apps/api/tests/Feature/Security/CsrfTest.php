<?php

// Checks that the web app's session requests need a CSRF token (statefulApi() in
// bootstrap/app.php, work pack K-19): a form on another website cannot sign someone in or
// change a finding using their cookie.
// Written as a PHPUnit class: PHPStan can only type $this->postJson() inside a TestCase class.

declare(strict_types=1);

namespace Tests\Feature\Security;

use Illuminate\Testing\TestResponse;
use Symfony\Component\HttpFoundation\Response;
use Tests\TestCase;

class CsrfTest extends TestCase
{
    /** Sanctum only starts a session for an origin listed in SANCTUM_STATEFUL_DOMAINS. */
    private const WEB_APP_ORIGIN = 'http://localhost';

    private const SESSION_CSRF_TOKEN = 'the-token-in-the-session';

    protected function setUp(): void
    {
        parent::setUp();
        // Laravel skips the CSRF check while env is "testing"; these tests need the real check.
        $this->app['env'] = 'production';
    }

    public function test_a_session_request_without_a_csrf_token_is_refused(): void
    {
        $response = $this->postLoginFromAnotherSite(csrfToken: null);

        $response->assertStatus(419);
    }

    public function test_a_session_request_with_a_wrong_csrf_token_is_refused(): void
    {
        $response = $this->postLoginFromAnotherSite(csrfToken: 'a-guessed-token');

        $response->assertStatus(419);
    }

    public function test_a_session_request_with_the_right_csrf_token_gets_past_the_check(): void
    {
        $response = $this->postLoginFromAnotherSite(csrfToken: self::SESSION_CSRF_TOKEN);

        // 422: the CSRF check passed and the (empty) sign-in form was validated.
        $response->assertUnprocessable();
    }

    /**
     * POST /api/login as a browser sends it when another site submits the request:
     * Sec-Fetch-Site says cross-site, so only the token can let it through.
     *
     * @return TestResponse<Response>
     */
    private function postLoginFromAnotherSite(?string $csrfToken): TestResponse
    {
        $headers = ['Origin' => self::WEB_APP_ORIGIN, 'Sec-Fetch-Site' => 'cross-site'];
        if ($csrfToken !== null) {
            $headers['X-CSRF-TOKEN'] = $csrfToken;
        }

        return $this->withSession(['_token' => self::SESSION_CSRF_TOKEN])
            ->withHeaders($headers)
            ->postJson('/api/login', []);
    }
}
