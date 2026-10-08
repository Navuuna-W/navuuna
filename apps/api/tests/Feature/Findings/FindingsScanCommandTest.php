<?php

// Checks `findings:scan`: one entity by id, every non-retired entity with --all, and a clear
// error for bad input. A PHPUnit class so PHPStan can type $this->artisan().

declare(strict_types=1);

namespace Tests\Feature\Findings;

use App\Models\Flag;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class FindingsScanCommandTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['engine.modules_path' => base_path('tests/fixtures/modules')]);
    }

    public function test_one_entity_is_scanned_by_id(): void
    {
        $entityId = waterPointWithMagnitudeGap();

        $this->artisan('findings:scan', ['entity' => $entityId])
            ->expectsOutput('Raised 1 findings for 1 entity.')
            ->assertSuccessful();

        $this->assertSame($entityId, Flag::sole()->entity_id);
    }

    public function test_all_scans_every_entity_that_is_not_retired(): void
    {
        waterPointWithMagnitudeGap();
        waterPointWithMagnitudeGap();
        $retiredEntityId = waterPointWithMagnitudeGap();
        DB::table('core.entities')->where('id', $retiredEntityId)->update(['retired_at' => now()]);

        $this->artisan('findings:scan', ['--all' => true])
            ->expectsOutput('Raised 2 findings across 2 entities.')
            ->assertSuccessful();

        $this->assertSame(0, Flag::where('entity_id', $retiredEntityId)->count());
    }

    public function test_an_id_and_all_together_is_refused(): void
    {
        $this->artisan('findings:scan', ['entity' => 'x', '--all' => true])
            ->expectsOutput('Give one entity id, or --all, but not both.')
            ->assertFailed();
    }

    public function test_neither_an_id_nor_all_is_refused(): void
    {
        $this->artisan('findings:scan')
            ->expectsOutput('Give one entity id, or --all, but not both.')
            ->assertFailed();
    }
}
