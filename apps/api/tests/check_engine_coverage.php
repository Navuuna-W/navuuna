<?php

// CI gate for work pack K-10 ("100 % unit coverage of engine"): reads the Clover report Pest writes
// with --coverage-clover and fails if any line in app/Engine was never run by a test.
// Usage: php tests/check_engine_coverage.php coverage.xml

declare(strict_types=1);

/** Only files under this folder must be fully covered; the rest of the app is not gated. */
const ENGINE_DIRECTORY = '/app/Engine/';

$cloverPath = $argv[1] ?? 'coverage.xml';
$report = is_file($cloverPath) ? simplexml_load_file($cloverPath) : false;
if ($report === false) {
    fwrite(STDERR, "Cannot read the coverage report {$cloverPath}.\n");
    exit(1);
}

$engineFileCount = 0;
$uncoveredLines = [];
foreach ($report->xpath('//file') ?: [] as $file) {
    $path = (string) $file['name'];
    if (! str_contains($path, ENGINE_DIRECTORY)) {
        continue;
    }

    $engineFileCount++;
    foreach ($file->line as $line) {
        $isUncoveredStatement = (string) $line['type'] === 'stmt' && (int) $line['count'] === 0;
        if ($isUncoveredStatement) {
            $uncoveredLines[] = basename($path).':'.$line['num'];
        }
    }
}

// An empty report would otherwise "pass" with nothing checked.
if ($engineFileCount === 0) {
    fwrite(STDERR, "The coverage report has no app/Engine files.\n");
    exit(1);
}

if ($uncoveredLines !== []) {
    fwrite(STDERR, 'Lines in app/Engine with no test: '.implode(', ', $uncoveredLines)."\n");
    exit(1);
}

echo "app/Engine: all lines covered in {$engineFileCount} files.\n";
