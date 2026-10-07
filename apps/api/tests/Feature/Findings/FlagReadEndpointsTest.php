<?php

// Checks GET /api/v1/flags (review queue) and GET /api/v1/flags/{id}: analysts see every finding,
// newest first; everyone else sees published findings only and gets a 404 for any other.
// Written as a PHPUnit class: PHPStan can only type $this->getJson() inside a TestCase class.

declare(strict_types=1);

namespace Tests\Feature\Findings;

use App\Auth\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class FlagReadEndpointsTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_queue_lists_newest_findings_first(): void
    {
        $olderId = insertFinding(['detected_at' => '2026-10-01T08:00:00Z']);
        $newerId = insertFinding(['detected_at' => '2026-10-05T08:00:00Z', 'sub_id' => '2.1']);

        $response = $this->actingAs($this->createUser(Role::Analyst))->getJson('/api/v1/flags');

        $response->assertOk();
        $this->assertSame([$newerId, $olderId], $response->json('data.*.id'));
    }

    public function test_the_queue_can_be_filtered_by_state(): void
    {
        insertFinding(['state' => 'held']);
        $publishedId = insertFinding(['state' => 'published', 'sub_id' => '2.1']);

        $response = $this->actingAs($this->createUser(Role::Admin))->getJson('/api/v1/flags?state=published');

        $this->assertSame([$publishedId], $response->json('data.*.id'));
    }

    #[DataProvider('nonReviewerRoles')]
    public function test_non_reviewers_cannot_open_the_queue(Role $role): void
    {
        insertFinding();

        $response = $this->actingAs($this->createUser($role))->getJson('/api/v1/flags');

        $response->assertForbidden()->assertJsonMissing(['data']);
    }

    #[DataProvider('nonReviewerRoles')]
    public function test_a_non_reviewer_gets_a_404_for_a_held_finding(Role $role): void
    {
        $findingId = insertFinding(['state' => 'held']);

        $response = $this->actingAs($this->createUser($role))->getJson("/api/v1/flags/{$findingId}");

        $response->assertNotFound();
        $this->assertStringNotContainsString($findingId, (string) $response->getContent());
    }

    /**
     * @return array<string, array{Role}>
     */
    public static function nonReviewerRoles(): array
    {
        return ['viewer' => [Role::Viewer], 'api client' => [Role::ApiClient]];
    }

    #[DataProvider('everyRole')]
    public function test_every_role_can_read_a_published_finding(Role $role): void
    {
        $findingId = insertFinding(['state' => 'published']);

        $response = $this->actingAs($this->createUser($role))->getJson("/api/v1/flags/{$findingId}");

        $response->assertOk()->assertJson(['id' => $findingId, 'state' => 'published', 'evidence_pack' => null]);
    }

    /**
     * @return array<string, array{Role}>
     */
    public static function everyRole(): array
    {
        return [
            'admin' => [Role::Admin],
            'analyst' => [Role::Analyst],
            'viewer' => [Role::Viewer],
            'api client' => [Role::ApiClient],
        ];
    }

    public function test_an_analyst_sees_a_held_finding_with_its_evidence_pack(): void
    {
        $findingId = insertFinding(['state' => 'held']);
        $recordId = DB::selectOne('SELECT public.uuid_generate_v7() AS id')->id;
        DB::table('flags.evidence_packs')->insert([
            'flag_id' => $findingId,
            'record_ids' => "{{$recordId}}",
            'narrative' => 'The register says operational; residents report it broken.',
            'adapter_version' => '1.0.0',
        ]);

        $response = $this->actingAs($this->createUser(Role::Analyst))->getJson("/api/v1/flags/{$findingId}");

        $response->assertOk()->assertJson([
            'state' => 'held',
            'evidence_pack' => [
                'narrative' => 'The register says operational; residents report it broken.',
                'record_ids' => [$recordId],
                'observation_ids' => [],
            ],
        ]);
    }

    private function createUser(Role $role): User
    {
        return User::factory()->withRole($role)->create();
    }
}
