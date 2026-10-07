<?php

// Checks what the api_keys migration promises: only Laravel (nv_app) reads and writes keys,
// nobody deletes them, and two keys can never share a hash.

declare(strict_types=1);

use App\Auth\Role;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

test('only the laravel role may read or write api keys, and no role may delete them', function (string $roleName, bool $isWriter) {
    $privilege = fn (string $privilegeName) => DB::selectOne(
        "SELECT has_table_privilege(?, 'public.api_keys', ?) AS allowed", [$roleName, $privilegeName]
    )->allowed;

    $canSelect = $privilege('SELECT');

    expect($canSelect)->toBe($isWriter);
    expect($privilege('INSERT'))->toBe($isWriter);
    expect($privilege('DELETE'))->toBeFalse();
})->with([
    'laravel' => ['nv_app', true],
    'ingest' => ['nv_ingest', false],
    'signals' => ['nv_signals', false],
]);

test('two keys cannot share a hash', function () {
    $apiClient = User::factory()->withRole(Role::ApiClient)->create();
    $insertKey = fn () => DB::table('public.api_keys')->insert([
        'user_id' => $apiClient->id,
        'name' => 'duplicate',
        'key_hash' => str_repeat('a', 64),
    ]);
    $insertKey();

    $insertSameHashAgain = $insertKey;

    expect($insertSameHashAgain)->toThrow(QueryException::class, 'api_keys_key_hash_unique');
});
