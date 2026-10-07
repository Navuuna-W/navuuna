<?php

// Checks FlagState: only published and resolved findings are public (Bible §11), and a stored
// state reads back from flags.flags as the matching enum case.

declare(strict_types=1);

use App\Findings\FlagState;
use App\Models\Flag;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('only published and resolved are public', function (FlagState $state, bool $isPublic) {
    $result = $state->isPublic();

    expect($result)->toBe($isPublic);
})->with([
    'detected' => [FlagState::Detected, false],
    'held' => [FlagState::Held, false],
    'explanation checked' => [FlagState::ExplanationChecked, false],
    'published' => [FlagState::Published, true],
    'dismissed' => [FlagState::Dismissed, false],
    'contested' => [FlagState::Contested, false],
    'resolved' => [FlagState::Resolved, true],
]);

test('a stored state reads back as a FlagState', function (FlagState $state) {
    $findingId = insertFinding(['state' => $state->value]);

    $storedState = Flag::findOrFail($findingId)->state;

    expect($storedState)->toBe($state);
})->with(FlagState::cases());
