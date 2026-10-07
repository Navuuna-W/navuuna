<?php

// Checks the X-Api-Key guard: a valid key signs in as its api_client, and a missing or wrong
// key gets a 401 that names the header. Session-only routes keep Laravel's default 401.
// Written as a PHPUnit class: PHPStan can only type $this->getJson() inside a TestCase class.

declare(strict_types=1);

namespace Tests\Feature\Auth;

use App\Auth\Role;
use App\Models\ApiKey;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Illuminate\Testing\TestResponse;
use Symfony\Component\HttpFoundation\Response;
use Tests\TestCase;

class ApiKeyGuardTest extends TestCase
{
    use RefreshDatabase;

    /** A route that takes a session or a key, the way Austine's /api/v1 routes will. */
    private const MACHINE_URL = '/test-only/machine-data';

    /** A key in the issued format; tests store its hash directly instead of running keys:issue. */
    private const PLAIN_KEY = 'nv_abcdefghijklmnopqrstuvwxyz0123456789ABCD';

    protected function setUp(): void
    {
        parent::setUp();

        Route::middleware('auth:sanctum,api_key')
            ->get(self::MACHINE_URL, fn (Request $request) => ['user_id' => $request->user()?->id]);
    }

    public function test_a_valid_key_signs_in_as_its_api_client(): void
    {
        $apiClient = $this->createApiClientWithKey();

        $response = $this->callWithKey(self::PLAIN_KEY);

        $response->assertOk()->assertExactJson(['user_id' => $apiClient->id]);
    }

    public function test_a_missing_key_gets_a_401_naming_the_header(): void
    {
        $response = $this->getJson(self::MACHINE_URL);

        $this->assertApiKey401($response);
    }

    public function test_a_wrong_key_gets_a_401_naming_the_header(): void
    {
        $this->createApiClientWithKey();

        $response = $this->callWithKey('nv_'.str_repeat('x', 40));

        $this->assertApiKey401($response);
    }

    public function test_a_key_stops_working_when_its_user_is_no_longer_an_api_client(): void
    {
        $apiClient = $this->createApiClientWithKey();
        $apiClient->update(['role' => Role::Viewer]);

        $response = $this->callWithKey(self::PLAIN_KEY);

        $this->assertApiKey401($response);
    }

    public function test_a_session_only_route_keeps_the_default_401(): void
    {
        $response = $this->postJson('/api/logout');

        $response->assertUnauthorized()->assertExactJson(['message' => 'Unauthenticated.']);
    }

    /**
     * Create an api_client user who owns PLAIN_KEY.
     */
    private function createApiClientWithKey(): User
    {
        $apiClient = User::factory()->withRole(Role::ApiClient)->create();
        ApiKey::create([
            'user_id' => $apiClient->id,
            'name' => 'test-key',
            'key_hash' => ApiKey::hashKey(self::PLAIN_KEY),
        ]);

        return $apiClient;
    }

    /**
     * @return TestResponse<Response>
     */
    private function callWithKey(string $plainKey): TestResponse
    {
        return $this->withHeader('X-Api-Key', $plainKey)->getJson(self::MACHINE_URL);
    }

    /**
     * @param  TestResponse<Response>  $response
     */
    private function assertApiKey401(TestResponse $response): void
    {
        $response->assertUnauthorized()->assertExactJson([
            'message' => 'Missing or invalid API key. Send it in the X-Api-Key header.',
            'header' => 'X-Api-Key',
        ]);
    }
}
