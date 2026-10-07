<?php

// Checks the rate limiters: 60 API calls a minute per key, then a 429 that says when to retry;
// each key, the tiles and sign-in each have their own bucket (work pack K-11, NFR-05).
// Written as a PHPUnit class: PHPStan can only type $this->getJson() inside a TestCase class.

declare(strict_types=1);

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Illuminate\Testing\TestResponse;
use Symfony\Component\HttpFoundation\Response;
use Tests\TestCase;

class RateLimitTest extends TestCase
{
    use RefreshDatabase;

    private const API_URL = '/test-only/api-call';

    private const TILE_URL = '/test-only/tile';

    private const API_LIMIT = 60;

    private const LOGIN_LIMIT = 5;

    protected function setUp(): void
    {
        parent::setUp();

        // Only the limiter is under test here, so these routes have no auth middleware.
        Route::middleware('throttle:api')->get(self::API_URL, fn () => response()->noContent());
        Route::middleware('throttle:tiles')->get(self::TILE_URL, fn () => response()->noContent());
    }

    public function test_the_61st_call_with_one_key_gets_a_429_saying_when_to_retry(): void
    {
        $this->callApiTimes(self::API_LIMIT, 'nv_key_one');

        $response = $this->callApiWithKey('nv_key_one');

        $response->assertTooManyRequests()->assertHeader('Retry-After');
        $response->assertJson([
            'message' => 'Too many requests.',
            'limit' => self::API_LIMIT,
            'window_seconds' => 60,
        ]);
        $this->assertGreaterThan(0, $response->json('retry_after_seconds'));
    }

    public function test_each_key_has_its_own_bucket(): void
    {
        $this->callApiTimes(self::API_LIMIT, 'nv_key_one');

        $response = $this->callApiWithKey('nv_key_two');

        $response->assertNoContent();
    }

    public function test_tile_requests_do_not_use_up_the_api_bucket(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);
        $this->callApiTimes(self::API_LIMIT, null);

        $response = $this->getJson(self::TILE_URL);

        $response->assertNoContent();
        $this->getJson(self::API_URL)->assertTooManyRequests();
    }

    public function test_the_6th_sign_in_attempt_in_a_minute_gets_a_429(): void
    {
        $user = User::factory()->create();
        for ($attempt = 1; $attempt <= self::LOGIN_LIMIT; $attempt++) {
            $this->signIn($user->email)->assertUnprocessable();
        }

        $response = $this->signIn($user->email);

        $response->assertTooManyRequests()->assertJson(['limit' => self::LOGIN_LIMIT]);
    }

    /**
     * Call the API route $times times, with $plainKey in X-Api-Key or (null) as the current user.
     */
    private function callApiTimes(int $times, ?string $plainKey): void
    {
        for ($call = 1; $call <= $times; $call++) {
            $response = $plainKey === null ? $this->getJson(self::API_URL) : $this->callApiWithKey($plainKey);
            $response->assertNoContent();
        }
    }

    /**
     * @return TestResponse<Response>
     */
    private function callApiWithKey(string $plainKey): TestResponse
    {
        return $this->withHeader('X-Api-Key', $plainKey)->getJson(self::API_URL);
    }

    /**
     * Try to sign in with a wrong password, as the browser would.
     *
     * @return TestResponse<Response>
     */
    private function signIn(string $email): TestResponse
    {
        return $this->withHeader('Origin', 'http://localhost')
            ->postJson('/api/login', ['email' => $email, 'password' => 'wrong password']);
    }
}
