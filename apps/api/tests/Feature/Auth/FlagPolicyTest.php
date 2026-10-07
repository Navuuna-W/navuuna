<?php

// Checks FlagPolicy against every role and finding state: published and resolved findings are
// public, every other state is for analysts and admins only (CLAUDE.md §4, Bible §11).
// No database: users and findings are built in memory.

declare(strict_types=1);

use App\Auth\Role;
use App\Models\Flag;
use App\Models\User;
use App\Policies\FlagPolicy;
use Illuminate\Support\Facades\Gate;

/**
 * Build an unsaved finding in the given state.
 */
function makeFlagInState(string $state): Flag
{
    $flag = new Flag;
    $flag->state = $state;

    return $flag;
}

/**
 * Build an unsaved user with the given role.
 */
function makeUserWithRole(Role $role): User
{
    return User::factory()->withRole($role)->make();
}

dataset('every role', Role::cases());
dataset('public states', Flag::PUBLIC_STATES);
dataset('unpublished states', ['detected', 'held', 'explanation_checked', 'dismissed', 'contested']);
dataset('reviewer roles', [Role::Admin, Role::Analyst]);
dataset('non-reviewer roles', [Role::Viewer, Role::ApiClient]);

test('every role can see a public finding', function (Role $role, string $state) {
    $policy = new FlagPolicy;

    $canView = $policy->view(makeUserWithRole($role), makeFlagInState($state));

    expect($canView)->toBeTrue();
})->with('every role')->with('public states');

test('admins and analysts can see an unpublished finding', function (Role $role, string $state) {
    $policy = new FlagPolicy;

    $canView = $policy->view(makeUserWithRole($role), makeFlagInState($state));

    expect($canView)->toBeTrue();
})->with('reviewer roles')->with('unpublished states');

test('viewers and api clients cannot see an unpublished finding', function (Role $role, string $state) {
    $policy = new FlagPolicy;

    $canView = $policy->view(makeUserWithRole($role), makeFlagInState($state));

    expect($canView)->toBeFalse();
})->with('non-reviewer roles')->with('unpublished states');

test('only admins and analysts can open the review queue or move a finding', function (Role $role) {
    $policy = new FlagPolicy;
    $user = makeUserWithRole($role);

    $canOpenQueue = $policy->viewAny($user);
    $canTransition = $policy->transition($user, makeFlagInState('held'));

    expect($canOpenQueue)->toBe($role->canSeeUnpublishedFindings());
    expect($canTransition)->toBe($role->canSeeUnpublishedFindings());
})->with('every role');

test('laravel finds the policy for a finding through the gate', function () {
    $viewer = makeUserWithRole(Role::Viewer);

    $isDenied = Gate::forUser($viewer)->denies('view', makeFlagInState('held'));

    expect($isDenied)->toBeTrue();
});

test('only admins and analysts can see unpublished findings', function (Role $role, bool $expected) {
    $canSee = $role->canSeeUnpublishedFindings();

    expect($canSee)->toBe($expected);
})->with([
    'admin' => [Role::Admin, true],
    'analyst' => [Role::Analyst, true],
    'viewer' => [Role::Viewer, false],
    'api client' => [Role::ApiClient, false],
]);
