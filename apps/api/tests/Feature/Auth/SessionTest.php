<?php

// Checks signing in and out of the web app with a Sanctum session (work pack K-11):
// right password → session, wrong password or api_client → 422, nobody signed in → 401.
// Written as a PHPUnit class: PHPStan can only type $this->postJson() inside a TestCase class.

declare(strict_types=1);

namespace Tests\Feature\Auth;

use App\Auth\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\HttpFoundation\Response;
use Tests\TestCase;

class SessionTest extends TestCase
{
    use RefreshDatabase;

    /** Sanctum only starts a session for an origin listed in SANCTUM_STATEFUL_DOMAINS. */
    private const WEB_APP_ORIGIN = 'http://localhost';

    /** The password every UserFactory user has. */
    private const FACTORY_PASSWORD = 'password';

    #[DataProvider('webAppRoles')]
    public function test_a_web_app_user_signs_in_with_the_right_password(Role $role): void
    {
        $user = User::factory()->withRole($role)->create();

        $response = $this->signIn($user->email, self::FACTORY_PASSWORD);

        $response->assertNoContent();
        $this->assertAuthenticatedAs($user, 'web');
    }

    /**
     * @return array<string, array{Role}>
     */
    public static function webAppRoles(): array
    {
        return [
            'admin' => [Role::Admin],
            'analyst' => [Role::Analyst],
            'viewer' => [Role::Viewer],
        ];
    }

    public function test_a_wrong_password_is_refused(): void
    {
        $user = User::factory()->create();

        $response = $this->signIn($user->email, 'wrong password');

        $response->assertUnprocessable()->assertJsonValidationErrors('email');
        $this->assertGuest('web');
    }

    public function test_an_api_client_cannot_sign_in_with_a_session(): void
    {
        $apiClient = User::factory()->withRole(Role::ApiClient)->create();

        $response = $this->signIn($apiClient->email, self::FACTORY_PASSWORD);

        $response->assertUnprocessable()->assertJsonValidationErrors('email');
        $this->assertGuest('web');
    }

    public function test_signing_out_without_a_session_gets_401(): void
    {
        $response = $this->withHeader('Origin', self::WEB_APP_ORIGIN)->postJson('/api/logout');

        $response->assertUnauthorized();
    }

    public function test_signing_out_ends_the_session(): void
    {
        $user = User::factory()->create();
        $this->signIn($user->email, self::FACTORY_PASSWORD);

        $response = $this->withHeader('Origin', self::WEB_APP_ORIGIN)->postJson('/api/logout');

        $response->assertNoContent();
        $this->assertGuest('web');
    }

    /**
     * Post a sign-in from the web app's origin, as the browser would.
     *
     * @return TestResponse<Response>
     */
    private function signIn(string $email, string $password): TestResponse
    {
        return $this->withHeader('Origin', self::WEB_APP_ORIGIN)
            ->postJson('/api/login', ['email' => $email, 'password' => $password]);
    }
}
