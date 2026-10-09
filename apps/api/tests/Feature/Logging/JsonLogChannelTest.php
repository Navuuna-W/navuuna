<?php

// Checks the `json` log channel the servers use (config/logging.php, work pack K-15): every log
// call becomes one line of JSON in a dated file, with the same field names as the Python logs.

declare(strict_types=1);

use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;

// A folder of its own, emptied before and after each test, so no other log line gets in.
const JSON_LOG_TEST_FOLDER = __DIR__.'/../../../storage/framework/testing/json-log';

beforeEach(function () {
    File::deleteDirectory(JSON_LOG_TEST_FOLDER);
    config(['logging.channels.json.path' => JSON_LOG_TEST_FOLDER.'/navuuna.json.log']);
});

afterEach(function () {
    File::deleteDirectory(JSON_LOG_TEST_FOLDER);
});

/**
 * Every line written to today's log file, each decoded from JSON.
 *
 * @return list<array<string, mixed>>
 */
function readJsonLogLines(): array
{
    $todaysFile = JSON_LOG_TEST_FOLDER.'/navuuna.json-'.date('Y-m-d').'.log';
    $lines = file($todaysFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);

    return array_map(fn (string $line) => json_decode($line, true, flags: JSON_THROW_ON_ERROR), $lines ?: []);
}

test('a log call becomes one json line with the same field names as the python logs', function () {
    Log::channel('json')->warning('Rolled up {count} entities.', ['count' => 4]);

    $lines = readJsonLogLines();

    expect($lines)->toHaveCount(1)
        ->and($lines[0])->toHaveKeys(['datetime', 'level_name', 'channel', 'message', 'context'])
        ->and($lines[0]['level_name'])->toBe('WARNING')
        ->and($lines[0]['message'])->toBe('Rolled up 4 entities.');
});

test('the context of a log call is kept as json fields', function () {
    Log::channel('json')->info('Finding raised.', ['entity_id' => 'entity-1', 'sub_id' => '2.2']);

    $lines = readJsonLogLines();

    expect($lines[0]['context'])->toBe(['entity_id' => 'entity-1', 'sub_id' => '2.2']);
});

test('two log calls become two lines', function () {
    Log::channel('json')->info('First.');
    Log::channel('json')->info('Second.');

    $lines = readJsonLogLines();

    expect(array_column($lines, 'message'))->toBe(['First.', 'Second.']);
});
