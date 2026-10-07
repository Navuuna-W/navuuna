<?php

// Checks `php artisan keys:issue`: it prints a key once, stores only its hash, and refuses
// any user who is not an api_client.

declare(strict_types=1);

use App\Auth\Role;
use App\Models\ApiKey;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;

uses(RefreshDatabase::class);

/** What an issued key looks like: prefix plus 40 random letters and digits. */
const ISSUED_KEY_PATTERN = '/nv_[A-Za-z0-9]{40}/';

test('keys:issue shows the key once and stores only its hash', function () {
    $apiClient = User::factory()->withRole(Role::ApiClient)->create();

    $exitCode = Artisan::call('keys:issue', ['name' => 'county-gis', '--user' => $apiClient->email]);

    expect($exitCode)->toBe(0);
    expect(preg_match(ISSUED_KEY_PATTERN, Artisan::output(), $matches))->toBe(1);
    $plainKey = $matches[0];
    $storedKey = ApiKey::sole();
    expect($storedKey->name)->toBe('county-gis');
    expect($storedKey->user_id)->toBe($apiClient->id);
    expect($storedKey->key_hash)->toBe(ApiKey::hashKey($plainKey));
    expect(json_encode($storedKey->getAttributes()))->not->toContain($plainKey);
});

test('keys:issue refuses a user who is not an api_client', function (Role $role) {
    $user = User::factory()->withRole($role)->create();

    $exitCode = Artisan::call('keys:issue', ['name' => 'oops', '--user' => $user->email]);

    expect($exitCode)->toBe(1);
    expect(ApiKey::count())->toBe(0);
})->with([Role::Admin, Role::Analyst, Role::Viewer]);

test('keys:issue refuses an unknown email', function () {
    $exitCode = Artisan::call('keys:issue', ['name' => 'oops', '--user' => 'nobody@navuuna.test']);

    expect($exitCode)->toBe(1);
    expect(ApiKey::count())->toBe(0);
});
