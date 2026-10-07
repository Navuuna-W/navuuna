<?php

// Checks POST /api/v1/flags/{id}/transition: an analyst's button press moves the finding; a bad
// action or note is a 422; anyone who may not review gets a 404, never a hint the finding exists.
// Written as a PHPUnit class: PHPStan can only type $this->postJson() inside a TestCase class.

declare(strict_types=1);

namespace Tests\Feature\Findings;

use App\Auth\Role;
use App\Models\ApiKey;
use App\Models\Flag;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\HttpFoundation\Response;
use Tests\TestCase;

class FlagTransitionEndpointTest extends TestCase
{
    use RefreshDatabase;

    public function test_an_analyst_publishes_a_held_finding(): void
    {
        $findingId = insertFinding(['state' => 'held']);

        $response = $this->actingAs($this->createUser(Role::Analyst))
            ->transition($findingId, ['action' => 'publish', 'note' => 'No explanation found.']);

        $response->assertOk()->assertExactJson(['id' => $findingId, 'state' => 'published']);
    }

    public function test_an_action_that_does_not_fit_the_finding_gets_a_422_and_changes_nothing(): void
    {
        $findingId = insertFinding(['state' => 'detected']);

        $response = $this->actingAs($this->createUser(Role::Admin))
            ->transition($findingId, ['action' => 'publish', 'note' => 'Trying to skip review.']);

        $response->assertUnprocessable()->assertJsonStructure(['message']);
        $this->assertSame('detected', Flag::findOrFail($findingId)->state->value);
    }

    /**
     * @param  array<string, string>  $body
     */
    #[DataProvider('badBodies')]
    public function test_a_bad_body_gets_a_422(array $body, string $badField): void
    {
        $findingId = insertFinding(['state' => 'held']);

        $response = $this->actingAs($this->createUser(Role::Analyst))->transition($findingId, $body);

        $response->assertUnprocessable()->assertJsonValidationErrors($badField);
    }

    /**
     * @return array<string, array{array<string, string>, string}>
     */
    public static function badBodies(): array
    {
        return [
            'unknown action' => [['action' => 'approve', 'note' => 'A note.'], 'action'],
            'missing note' => [['action' => 'publish'], 'note'],
        ];
    }

    #[DataProvider('nonReviewerRoles')]
    public function test_a_signed_in_non_reviewer_gets_a_404(Role $role): void
    {
        $findingId = insertFinding(['state' => 'held']);

        $response = $this->actingAs($this->createUser($role))
            ->transition($findingId, ['action' => 'publish', 'note' => 'A note.']);

        $response->assertNotFound();
        $this->assertSame('held', Flag::findOrFail($findingId)->state->value);
    }

    /**
     * @return array<string, array{Role}>
     */
    public static function nonReviewerRoles(): array
    {
        return ['viewer' => [Role::Viewer], 'api client' => [Role::ApiClient]];
    }

    public function test_an_api_client_with_a_key_gets_a_404(): void
    {
        $findingId = insertFinding(['state' => 'held']);
        $apiClient = $this->createUser(Role::ApiClient);
        ApiKey::create(['user_id' => $apiClient->id, 'name' => 'test', 'key_hash' => ApiKey::hashKey('nv_test_key')]);

        $response = $this->withHeader('X-Api-Key', 'nv_test_key')
            ->transition($findingId, ['action' => 'publish', 'note' => 'A note.']);

        $response->assertNotFound();
    }

    public function test_nobody_signed_in_gets_a_401(): void
    {
        $findingId = insertFinding(['state' => 'held']);

        $response = $this->transition($findingId, ['action' => 'publish', 'note' => 'A note.']);

        $response->assertUnauthorized();
    }

    public function test_an_unknown_or_malformed_id_gets_a_404(): void
    {
        $analyst = $this->createUser(Role::Analyst);

        $unknown = $this->actingAs($analyst)->transition('01900000-0000-7000-8000-000000000000', ['action' => 'publish', 'note' => 'A note.']);
        $malformed = $this->actingAs($analyst)->transition('not-a-uuid', ['action' => 'publish', 'note' => 'A note.']);

        $unknown->assertNotFound();
        $malformed->assertNotFound();
    }

    private function createUser(Role $role): User
    {
        return User::factory()->withRole($role)->create();
    }

    /**
     * @param  array<string, string>  $body
     * @return TestResponse<Response>
     */
    private function transition(string $findingId, array $body): TestResponse
    {
        return $this->postJson("/api/v1/flags/{$findingId}/transition", $body);
    }
}
