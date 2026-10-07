<?php

// Checks FlagWorkflow against every pair of states, for a person and for the system: the legal
// moves (Bible §6.5, ADR-013) work and write all three rows together; every other move is
// refused and writes nothing. In particular, nothing goes from detected to published.

declare(strict_types=1);

use App\Auth\Role;
use App\Findings\FlagState;
use App\Findings\FlagStateChanged;
use App\Findings\FlagWorkflow;
use App\Findings\IllegalFlagTransition;
use App\Models\AuditEvent;
use App\Models\Flag;
use App\Models\FlagTransition;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;

uses(RefreshDatabase::class);

/** The moves an analyst or admin may make, written out by hand so the test doesn't copy the code. */
const LEGAL_PERSON_MOVES = [
    ['held', 'explanation_checked'],
    ['explanation_checked', 'published'],
    ['explanation_checked', 'dismissed'],
    ['published', 'contested'],
    ['contested', 'resolved'],
    ['contested', 'published'],
];

/** The moves only the system may make (K-13 and E8). */
const LEGAL_SYSTEM_MOVES = [
    ['detected', 'held'],
    ['held', 'dismissed'],
    ['explanation_checked', 'dismissed'],
    ['published', 'resolved'],
];

/**
 * Every from → to pair of two different states that is not in $legalMoves.
 *
 * @param  list<array{string, string}>  $legalMoves
 * @return array<string, array{string, string}>
 */
function illegalMoves(array $legalMoves): array
{
    $illegal = [];
    foreach (FlagState::cases() as $from) {
        foreach (FlagState::cases() as $to) {
            $pair = [$from->value, $to->value];
            if ($from !== $to && ! in_array($pair, $legalMoves, true)) {
                $illegal["{$from->value} → {$to->value}"] = $pair;
            }
        }
    }

    return $illegal;
}

/**
 * Save a finding in the given state and return it.
 */
function createFindingInState(string $state): Flag
{
    return Flag::findOrFail(insertFinding(['state' => $state]));
}

/**
 * An analyst who can review findings.
 */
function createAnalyst(): User
{
    return User::factory()->withRole(Role::Analyst)->create();
}

test('a person can make each legal move', function (string $from, string $to) {
    $finding = createFindingInState($from);

    app(FlagWorkflow::class)->moveByPerson($finding, FlagState::from($to), createAnalyst(), 'Checked on site.', 'None found.');

    expect($finding->state)->toBe(FlagState::from($to));
})->with(LEGAL_PERSON_MOVES);

test('a person cannot make any other move', function (string $from, string $to) {
    $finding = createFindingInState($from);

    $move = fn () => app(FlagWorkflow::class)->moveByPerson($finding, FlagState::from($to), createAnalyst(), 'A note.', 'None found.');

    expect($move)->toThrow(IllegalFlagTransition::class);
    expect($finding->fresh()?->state)->toBe(FlagState::from($from));
    expect(FlagTransition::count())->toBe(0);
})->with(illegalMoves(LEGAL_PERSON_MOVES));

test('the system can make each legal move', function (string $from, string $to) {
    $finding = createFindingInState($from);

    app(FlagWorkflow::class)->moveBySystem($finding, FlagState::from($to), 'Gap closed on two runs.');

    expect($finding->state)->toBe(FlagState::from($to));
})->with(LEGAL_SYSTEM_MOVES);

test('the system cannot make any other move', function (string $from, string $to) {
    $finding = createFindingInState($from);

    $move = fn () => app(FlagWorkflow::class)->moveBySystem($finding, FlagState::from($to), 'A note.');

    expect($move)->toThrow(IllegalFlagTransition::class);
    expect($finding->fresh()?->state)->toBe(FlagState::from($from));
})->with(illegalMoves(LEGAL_SYSTEM_MOVES));

test('nothing moves a finding from detected to published', function () {
    $finding = createFindingInState('detected');
    $workflow = app(FlagWorkflow::class);

    $byPerson = fn () => $workflow->moveByPerson($finding, FlagState::Published, createAnalyst(), 'A note.');
    $bySystem = fn () => $workflow->moveBySystem($finding, FlagState::Published, 'A note.');

    expect($byPerson)->toThrow(IllegalFlagTransition::class);
    expect($bySystem)->toThrow(IllegalFlagTransition::class);
});

test('a legal move writes the history row and the audit row', function () {
    $finding = createFindingInState('held');
    $analyst = createAnalyst();

    app(FlagWorkflow::class)->moveByPerson($finding, FlagState::ExplanationChecked, $analyst, 'Asked the county.', 'Scheme under repair.');

    $transition = FlagTransition::sole();
    expect([$transition->flag_id, $transition->from_state, $transition->to_state, $transition->user_id, $transition->note])
        ->toBe([$finding->id, 'held', 'explanation_checked', $analyst->id, 'Asked the county.']);
    $auditEvent = AuditEvent::sole();
    expect([$auditEvent->action, $auditEvent->target_table, $auditEvent->target_id, $auditEvent->user_id])
        ->toBe(['flag_transition', 'flags.flags', $finding->id, $analyst->id]);
    expect($auditEvent->before)->toBe(['state' => 'held']);
    // toEqual, not toBe: jsonb does not keep key order.
    expect($auditEvent->after)->toEqual(['state' => 'explanation_checked', 'note' => 'Asked the county.', 'explanation' => 'Scheme under repair.']);
});

test('a system move records no user', function () {
    $finding = createFindingInState('detected');

    app(FlagWorkflow::class)->moveBySystem($finding, FlagState::Held, 'Raised by the findings engine.');

    expect(FlagTransition::sole()->user_id)->toBeNull();
    expect(AuditEvent::sole()->user_id)->toBeNull();
});

test('if the audit row fails, the state and history row are rolled back too', function () {
    $finding = createFindingInState('held');
    DB::unprepared("
        CREATE FUNCTION pg_temp.refuse_audit() RETURNS trigger AS \$\$
        BEGIN RAISE EXCEPTION 'audit refused for test'; END \$\$ LANGUAGE plpgsql;
        CREATE TRIGGER refuse_audit BEFORE INSERT ON audit.events
        FOR EACH ROW EXECUTE FUNCTION pg_temp.refuse_audit();
    ");

    $move = fn () => app(FlagWorkflow::class)->moveByPerson($finding, FlagState::ExplanationChecked, createAnalyst(), 'A note.', 'None found.');

    expect($move)->toThrow(Exception::class, 'audit refused for test');
    expect($finding->fresh()?->state)->toBe(FlagState::Held);
    expect(FlagTransition::count())->toBe(0);
});

test('a legal move announces FlagStateChanged', function () {
    Event::fake([FlagStateChanged::class]);
    $finding = createFindingInState('explanation_checked');
    $analyst = createAnalyst();

    app(FlagWorkflow::class)->moveByPerson($finding, FlagState::Published, $analyst, 'No explanation found.');

    Event::assertDispatched(fn (FlagStateChanged $event) => $event->flagId === $finding->id
        && $event->entityId === $finding->entity_id
        && $event->fromState === FlagState::ExplanationChecked
        && $event->toState === FlagState::Published
        && $event->userId === $analyst->id);
});

test('a refused move announces nothing', function () {
    Event::fake([FlagStateChanged::class]);
    $finding = createFindingInState('detected');

    $move = fn () => app(FlagWorkflow::class)->moveBySystem($finding, FlagState::Published, 'A note.');

    expect($move)->toThrow(IllegalFlagTransition::class);
    Event::assertNotDispatched(FlagStateChanged::class);
});

test('a move needs a note', function (string $blankNote) {
    $finding = createFindingInState('detected');

    $move = fn () => app(FlagWorkflow::class)->moveBySystem($finding, FlagState::Held, $blankNote);

    expect($move)->toThrow(IllegalFlagTransition::class, 'A note is required');
})->with(['empty' => [''], 'spaces' => ['   ']]);

test('marking explanation_checked needs the explanation', function () {
    $finding = createFindingInState('held');

    $move = fn () => app(FlagWorkflow::class)->moveByPerson($finding, FlagState::ExplanationChecked, createAnalyst(), 'A note.');

    expect($move)->toThrow(IllegalFlagTransition::class, 'An explanation is required');
});

test('a viewer or api client cannot move a finding', function (Role $role) {
    $finding = createFindingInState('explanation_checked');
    $user = User::factory()->withRole($role)->create();

    $move = fn () => app(FlagWorkflow::class)->moveByPerson($finding, FlagState::Published, $user, 'A note.');

    expect($move)->toThrow(IllegalFlagTransition::class, 'Only analysts and admins');
})->with([Role::Viewer, Role::ApiClient]);
