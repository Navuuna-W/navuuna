<?php

// Checks `php artisan outcomes:record` (FR-19, K-17): it saves an outcome and its audit row
// together for a public finding, refuses every bad input without writing anything, and keeps
// earlier outcomes when a new one is recorded.

declare(strict_types=1);

use App\Auth\Role;
use App\Findings\Outcome;
use App\Models\AuditEvent;
use App\Models\FlagOutcome;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;

uses(RefreshDatabase::class);

/**
 * Run outcomes:record with sensible defaults and return the exit code.
 *
 * @param  array<string, string>  $overrides
 */
function recordOutcome(string $flagId, string $recorderEmail, array $overrides = []): int
{
    return Artisan::call('outcomes:record', array_merge([
        'flag' => $flagId,
        'outcome' => 'confirmed',
        '--evidence' => 'Site visit 12 Oct: pump not working.',
        '--by' => $recorderEmail,
    ], $overrides));
}

test('records an outcome and its audit row for a published finding', function () {
    $flagId = insertFinding(['state' => 'published']);
    $analyst = User::factory()->withRole(Role::Analyst)->create();

    $exitCode = recordOutcome($flagId, $analyst->email);

    expect($exitCode)->toBe(0);
    $outcome = FlagOutcome::sole();
    expect($outcome->flag_id)->toBe($flagId);
    expect($outcome->outcome)->toBe(Outcome::Confirmed);
    expect($outcome->evidence)->toBe('Site visit 12 Oct: pump not working.');
    expect($outcome->recorded_by)->toBe($analyst->id);
    $auditEvent = AuditEvent::sole();
    expect($auditEvent->action)->toBe('outcome_recorded');
    expect($auditEvent->user_id)->toBe($analyst->id);
    expect($auditEvent->target_table)->toBe('flags.outcomes');
    expect($auditEvent->target_id)->toBe($outcome->id);
    expect($auditEvent->after)->toMatchArray(['flag_id' => $flagId, 'outcome' => 'confirmed']);
});

test('records an outcome for a resolved finding', function () {
    $flagId = insertFinding(['state' => 'resolved']);
    $admin = User::factory()->withRole(Role::Admin)->create();

    $exitCode = recordOutcome($flagId, $admin->email, ['outcome' => 'refuted']);

    expect($exitCode)->toBe(0);
    expect(FlagOutcome::sole()->outcome)->toBe(Outcome::Refuted);
});

test('refuses a finding that is not public', function (string $state) {
    $flagId = insertFinding(['state' => $state]);
    $analyst = User::factory()->withRole(Role::Analyst)->create();

    $exitCode = recordOutcome($flagId, $analyst->email);

    expect($exitCode)->toBe(1);
    expect(FlagOutcome::count())->toBe(0);
    expect(AuditEvent::count())->toBe(0);
})->with(['detected', 'held', 'explanation_checked', 'contested', 'dismissed']);

test('refuses a flag id that does not exist', function (string $flagId) {
    $analyst = User::factory()->withRole(Role::Analyst)->create();

    $exitCode = recordOutcome($flagId, $analyst->email);

    expect($exitCode)->toBe(1);
    expect(FlagOutcome::count())->toBe(0);
})->with(['0199c8a0-0000-7000-8000-000000000000', 'not-a-uuid']);

test('refuses an outcome that is not one of the four', function () {
    $flagId = insertFinding(['state' => 'published']);
    $analyst = User::factory()->withRole(Role::Analyst)->create();

    $exitCode = recordOutcome($flagId, $analyst->email, ['outcome' => 'maybe']);

    expect($exitCode)->toBe(1);
    expect(FlagOutcome::count())->toBe(0);
});

test('refuses blank evidence', function () {
    $flagId = insertFinding(['state' => 'published']);
    $analyst = User::factory()->withRole(Role::Analyst)->create();

    $exitCode = recordOutcome($flagId, $analyst->email, ['--evidence' => '   ']);

    expect($exitCode)->toBe(1);
    expect(FlagOutcome::count())->toBe(0);
});

test('refuses a person who is not an analyst or admin', function (Role $role) {
    $flagId = insertFinding(['state' => 'published']);
    $user = User::factory()->withRole($role)->create();

    $exitCode = recordOutcome($flagId, $user->email);

    expect($exitCode)->toBe(1);
    expect(FlagOutcome::count())->toBe(0);
})->with([Role::Viewer, Role::ApiClient]);

test('refuses an email that belongs to nobody', function () {
    $flagId = insertFinding(['state' => 'published']);

    $exitCode = recordOutcome($flagId, 'nobody@navuuna.test');

    expect($exitCode)->toBe(1);
    expect(FlagOutcome::count())->toBe(0);
});

test('keeps earlier outcomes when a new one is recorded', function () {
    $flagId = insertFinding(['state' => 'published']);
    $analyst = User::factory()->withRole(Role::Analyst)->create();
    recordOutcome($flagId, $analyst->email, ['outcome' => 'unknown']);

    recordOutcome($flagId, $analyst->email, ['outcome' => 'partial']);

    // Order isn't checked: both rows share one test transaction, so `at` is the same for both.
    expect(FlagOutcome::pluck('outcome')->all())->toEqualCanonicalizing([Outcome::Unknown, Outcome::Partial]);
    expect(AuditEvent::count())->toBe(2);
});

test('writes no outcome if the audit row fails', function () {
    $flagId = insertFinding(['state' => 'published']);
    $analyst = User::factory()->withRole(Role::Analyst)->create();
    AuditEvent::creating(fn () => throw new RuntimeException('audit insert failed'));

    expect(fn () => recordOutcome($flagId, $analyst->email))->toThrow(RuntimeException::class);

    expect(FlagOutcome::count())->toBe(0);
});
