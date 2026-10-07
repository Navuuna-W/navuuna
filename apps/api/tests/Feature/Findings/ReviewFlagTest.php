<?php

// Checks each review-screen button (ADR-010 DEC-10, ADR-013): Publish and Dismiss record two
// moves with the same note, user and time, or none at all; Needs more evidence only adds an
// audit note; Contest, Resolve and Reject contest make one move each.

declare(strict_types=1);

use App\Auth\Role;
use App\Findings\FlagState;
use App\Findings\IllegalFlagTransition;
use App\Findings\ReviewAction;
use App\Findings\ReviewFlag;
use App\Models\AuditEvent;
use App\Models\Flag;
use App\Models\FlagTransition;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

/**
 * Apply $action to a new finding in $state as a new analyst, and return the finding.
 */
function reviewFinding(string $state, ReviewAction $action, ?string $explanation = null): Flag
{
    $finding = Flag::findOrFail(insertFinding(['state' => $state]));
    $analyst = User::factory()->withRole(Role::Analyst)->create();

    app(ReviewFlag::class)->apply($finding, $analyst, $action, 'Reviewed with the county.', $explanation);

    return $finding;
}

test('publish records held → explanation_checked → published with one note and time', function () {
    $finding = reviewFinding('held', ReviewAction::Publish);

    expect($finding->state)->toBe(FlagState::Published);
    $transitions = FlagTransition::orderBy('to_state')->get();
    expect($transitions->pluck('to_state')->all())->toBe(['explanation_checked', 'published']);
    expect($transitions->pluck('note')->unique()->all())->toBe(['Reviewed with the county.']);
    expect($transitions->pluck('at')->unique()->count())->toBe(1);
    $firstAudit = AuditEvent::where('after->state', 'explanation_checked')->sole();
    expect($firstAudit->after['explanation'] ?? null)->toBe(ReviewFlag::NO_EXPLANATION_FOUND);
});

test('dismiss records held → explanation_checked → dismissed with the explanation', function () {
    $finding = reviewFinding('held', ReviewAction::Dismiss, 'Scheme decommissioned in 2024.');

    expect($finding->state)->toBe(FlagState::Dismissed);
    expect(FlagTransition::count())->toBe(2);
    $firstAudit = AuditEvent::where('after->state', 'explanation_checked')->sole();
    expect($firstAudit->after['explanation'] ?? null)->toBe('Scheme decommissioned in 2024.');
});

test('dismiss without an explanation is refused and writes nothing', function () {
    $dismiss = fn () => reviewFinding('held', ReviewAction::Dismiss);

    expect($dismiss)->toThrow(IllegalFlagTransition::class, 'An explanation is required');
    expect(Flag::sole()->state)->toBe(FlagState::Held);
    expect(FlagTransition::count())->toBe(0);
});

test('if the second publish move fails, the first is undone too', function () {
    DB::unprepared("
        CREATE FUNCTION pg_temp.refuse_publish() RETURNS trigger AS \$\$
        BEGIN RAISE EXCEPTION 'publish refused for test'; END \$\$ LANGUAGE plpgsql;
        CREATE TRIGGER refuse_publish BEFORE INSERT ON flags.transitions
        FOR EACH ROW WHEN (NEW.to_state = 'published') EXECUTE FUNCTION pg_temp.refuse_publish();
    ");

    $publish = fn () => reviewFinding('held', ReviewAction::Publish);

    expect($publish)->toThrow(Exception::class, 'publish refused for test');
    expect(Flag::sole()->state)->toBe(FlagState::Held);
    expect(FlagTransition::count())->toBe(0);
    expect(AuditEvent::count())->toBe(0);
});

test('needs more evidence keeps the finding held and only adds an audit note', function () {
    $finding = reviewFinding('held', ReviewAction::NeedsMoreEvidence);

    expect($finding->fresh()?->state)->toBe(FlagState::Held);
    expect(FlagTransition::count())->toBe(0);
    $auditEvent = AuditEvent::sole();
    expect($auditEvent->action)->toBe('note_added');
    expect($auditEvent->after)->toEqual(['state' => 'held', 'note' => 'Reviewed with the county.']);
});

test('needs more evidence is refused for a finding that is not held', function () {
    $addNote = fn () => reviewFinding('published', ReviewAction::NeedsMoreEvidence);

    expect($addNote)->toThrow(IllegalFlagTransition::class, 'Only a held finding');
    expect(AuditEvent::count())->toBe(0);
});

test('contest, resolve and reject contest each make one move', function (string $from, ReviewAction $action, FlagState $to) {
    $finding = reviewFinding($from, $action);

    expect($finding->state)->toBe($to);
    expect(FlagTransition::count())->toBe(1);
})->with([
    'contest' => ['published', ReviewAction::Contest, FlagState::Contested],
    'resolve' => ['contested', ReviewAction::Resolve, FlagState::Resolved],
    'reject contest' => ['contested', ReviewAction::RejectContest, FlagState::Published],
]);

test('publish is refused for a finding that is still detected', function () {
    $publish = fn () => reviewFinding('detected', ReviewAction::Publish);

    expect($publish)->toThrow(IllegalFlagTransition::class);
    expect(Flag::sole()->state)->toBe(FlagState::Detected);
    expect(FlagTransition::count())->toBe(0);
});
