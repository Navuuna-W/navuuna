<?php

// Checks API keys end to end: `keys:issue` shows a key once and stores only its hash, the
// X-Api-Key guard lets a valid key in, and a missing or wrong key gets a 401 naming the header.
// Written as a PHPUnit class: PHPStan can only type $this->getJson() inside a TestCase class.

declare(strict_types=1);

namespace Tests\Feature\Auth;

use App\Auth\Role;
use App\Models\ApiKey;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Route;
use Illuminate\Testing\TestResponse;
use Symfony\Component\HttpFoundation\Response;
use Tests\TestCase;

class ApiKeyTest extends TestCase
{
    use RefreshDatabase;

    /** A route that takes a session or a key, the way Austine's /api/v1 routes will. */
    private const MACHINE_URL = '/test-only/machine-data';

    /** What an issued key looks like: prefix plus 40 random letters and digits. */
    private const KEY_PATTERN = '/nv_[A-Za-z0-9]{40}/';

    protected function setUp(): void
    {
        parent::setUp();

        Route::middleware('auth:sanctum,api_key')
            ->get(self::MACHINE_URL, fn (Request $request) => ['user_id' => $request->user()?->id]);
    }

    public function test_keys_issue_shows_the_key_once_and_stores_only_its_hash(): void
    {
        $apiClient = User::factory()->withRole(Role::ApiClient)->create();

        $exitCode = Artisan::call('keys:issue', ['name' => 'county-gis', '--user' => $apiClient->email]);

        $this->assertSame(0, $exitCode);
        $plainKey = $this->findKeyInOutput(Artisan::output());
        $storedKey = ApiKey::sole();
        $this->assertSame('county-gis', $storedKey->name);
        $this->assertSame(ApiKey::hashKey($plainKey), $storedKey->key_hash);
        $this->assertStringNotContainsString($plainKey, (string) json_encode($storedKey->getAttributes()));
    }

    public function test_keys_issue_refuses_a_user_who_is_not_an_api_client(): void
    {
        $viewer = User::factory()->withRole(Role::Viewer)->create();

        $exitCode = Artisan::call('keys:issue', ['name' => 'oops', '--user' => $viewer->email]);

        $this->assertSame(1, $exitCode);
        $this->assertSame(0, ApiKey::count());
    }

    public function test_keys_issue_refuses_an_unknown_email(): void
    {
        $exitCode = Artisan::call('keys:issue', ['name' => 'oops', '--user' => 'nobody@navuuna.test']);

        $this->assertSame(1, $exitCode);
        $this->assertSame(0, ApiKey::count());
    }

    public function test_a_valid_key_signs_in_as_its_api_client(): void
    {
        $apiClient = User::factory()->withRole(Role::ApiClient)->create();
        $plainKey = $this->issueKeyFor($apiClient);

        $response = $this->callWithKey($plainKey);

        $response->assertOk()->assertExactJson(['user_id' => $apiClient->id]);
    }

    public function test_a_missing_key_gets_a_401_naming_the_header(): void
    {
        $response = $this->getJson(self::MACHINE_URL);

        $this->assertApiKey401($response);
    }

    public function test_a_wrong_key_gets_a_401_naming_the_header(): void
    {
        $response = $this->callWithKey('nv_'.str_repeat('x', 40));

        $this->assertApiKey401($response);
    }

    public function test_a_key_stops_working_when_its_user_is_no_longer_an_api_client(): void
    {
        $apiClient = User::factory()->withRole(Role::ApiClient)->create();
        $plainKey = $this->issueKeyFor($apiClient);
        $apiClient->update(['role' => Role::Viewer]);

        $response = $this->callWithKey($plainKey);

        $this->assertApiKey401($response);
    }

    public function test_a_session_only_route_keeps_the_default_401(): void
    {
        $response = $this->postJson('/api/logout');

        $response->assertUnauthorized()->assertExactJson(['message' => 'Unauthenticated.']);
    }

    /**
     * Run keys:issue for the user and return the plain key it printed.
     */
    private function issueKeyFor(User $apiClient): string
    {
        Artisan::call('keys:issue', ['name' => 'test-key', '--user' => $apiClient->email]);

        return $this->findKeyInOutput(Artisan::output());
    }

    /**
     * Pull the plain key out of the command's output.
     */
    private function findKeyInOutput(string $output): string
    {
        $this->assertSame(1, preg_match(self::KEY_PATTERN, $output, $matches));

        return $matches[0];
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
