<?php

// Checks GET /api/v1/me (work pack A-10, DEC-13): nobody signed in gets 401, each web-app role
// reads back its own name, role and review capability, and the body carries exactly three keys —
// so a new column on users (an email, a phone number) can never widen it by accident (NFR-06).
// Written as a PHPUnit class: PHPStan can only type $this->getJson() inside a TestCase class.

declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Auth\Role;
use App\Models\User;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class CurrentUserEndpointTest extends TestCase
{
    private const CURRENT_USER_URL = '/api/v1/me';

    /** The only keys /me may ever answer with, in order (DEC-13 contract slice). */
    private const EXPECTED_KEYS = ['name', 'role', 'can_review_findings'];

    public function test_nobody_signed_in_gets_401(): void
    {
        $response = $this->getJson(self::CURRENT_USER_URL);

        // The exact body proves /me takes a session only: a route that also accepted an API key
        // would answer with the X-Api-Key 401 instead (App\Auth\MissingApiKeyResponse).
        $response->assertUnauthorized()->assertExactJson(['message' => 'Unauthenticated.']);
    }

    #[DataProvider('webAppRolesAndTheirReviewCapability')]
    public function test_each_web_app_role_reads_back_its_name_role_and_review_capability(
        Role $role,
        bool $canReviewFindings,
    ): void {
        $user = User::factory()->withRole($role)->make(['name' => 'Asha Mwangi']);

        $response = $this->actingAs($user)->getJson(self::CURRENT_USER_URL);

        $response->assertOk()->assertExactJson([
            'name' => 'Asha Mwangi',
            'role' => $role->value,
            'can_review_findings' => $canReviewFindings,
        ]);
    }

    /**
     * Only the three roles that sign in with a session: an api_client authenticates with
     * X-Api-Key and never reaches this route (FR-20, work pack K-11).
     *
     * @return array<string, array{Role, bool}>
     */
    public static function webAppRolesAndTheirReviewCapability(): array
    {
        return [
            'an admin may review findings' => [Role::Admin, true],
            'an analyst may review findings' => [Role::Analyst, true],
            'a viewer may not review findings' => [Role::Viewer, false],
        ];
    }

    public function test_the_payload_is_only_a_name_a_role_and_a_review_capability(): void
    {
        $user = User::factory()->make(['email' => 'asha@example.test']);

        $response = $this->actingAs($user)->getJson(self::CURRENT_USER_URL);

        $response->assertOk();
        $this->assertSame(self::EXPECTED_KEYS, array_keys((array) $response->json()));
    }
}
