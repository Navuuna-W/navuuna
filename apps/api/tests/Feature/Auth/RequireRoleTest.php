<?php

// Checks the `role:` route middleware: 403 for every role not in the list, 401 for nobody.
// Uses a route that exists only in this test, guarded the way the review queue will be.
// Written as a PHPUnit class: PHPStan can only type $this->getJson() inside a TestCase class.

declare(strict_types=1);

namespace Tests\Feature\Auth;

use App\Auth\Role;
use App\Models\User;
use Illuminate\Support\Facades\Route;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class RequireRoleTest extends TestCase
{
    private const ANALYST_ONLY_URL = '/test-only/analyst-page';

    protected function setUp(): void
    {
        parent::setUp();

        Route::middleware('role:analyst,admin')
            ->get(self::ANALYST_ONLY_URL, fn () => response()->noContent());
    }

    #[DataProvider('everyRole')]
    public function test_each_role_gets_in_or_gets_403(Role $role, int $expectedStatus): void
    {
        $user = User::factory()->withRole($role)->make();

        $response = $this->actingAs($user)->getJson(self::ANALYST_ONLY_URL);

        $response->assertStatus($expectedStatus);
    }

    /**
     * @return array<string, array{Role, int}>
     */
    public static function everyRole(): array
    {
        return [
            'admin is let in' => [Role::Admin, 204],
            'analyst is let in' => [Role::Analyst, 204],
            'viewer gets 403' => [Role::Viewer, 403],
            'api client gets 403' => [Role::ApiClient, 403],
        ];
    }

    public function test_nobody_signed_in_gets_401(): void
    {
        $response = $this->getJson(self::ANALYST_ONLY_URL);

        $response->assertUnauthorized();
    }
}
