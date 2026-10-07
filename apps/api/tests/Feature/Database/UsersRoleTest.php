<?php

// Checks what the users.role migration promises: every user has one of the four FR-20 roles,
// and the database refuses a missing or unknown role even if PHP validation is skipped.

declare(strict_types=1);

use App\Auth\Role;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

/**
 * Insert a user row directly, bypassing the model and its enum cast.
 */
function insertUserWithRole(?string $roleName): void
{
    DB::table('public.users')->insert([
        'name' => 'Direct Insert',
        'email' => 'direct@navuuna.test',
        'password' => 'not-a-real-hash',
        'role' => $roleName,
    ]);
}

test('each of the four roles can be stored and read back as a Role', function (Role $role) {
    $user = User::factory()->withRole($role)->create();

    $storedRole = $user->fresh()?->role;

    expect($storedRole)->toBe($role);
})->with(Role::cases());

test('the database rejects an unknown role', function () {
    $insertUnknownRole = fn () => insertUserWithRole('superuser');

    expect($insertUnknownRole)->toThrow(QueryException::class, 'users_role_check');
});

test('the database rejects a user without a role', function () {
    $insertWithoutRole = fn () => insertUserWithRole(null);

    expect($insertWithoutRole)->toThrow(QueryException::class, 'not-null');
});
