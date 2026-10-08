<?php

// Checks that a module's findings.yml is read into narrative templates, and that a file which
// would leave a blank in a finding's text is refused up front (docs/modules/findings.md rule 4).
// Unit tests: they read the water stub and small YAML files written to a temporary folder.

declare(strict_types=1);

use App\Findings\FindingsFile;
use App\Findings\InvalidFindingsFile;

const WATER_FINDINGS_STUB = __DIR__.'/../../fixtures/modules/water/findings.yml';

/**
 * Write YAML to a temporary findings.yml and return its path.
 */
function writeFindingsFile(string $yaml): string
{
    $path = tempnam(sys_get_temp_dir(), 'findings').'.yml';
    file_put_contents($path, $yaml);

    return $path;
}

test('the water stub has a template for 2.1, 2.2 and 2.4 and none for 2.5', function () {
    $findingsFile = FindingsFile::fromFile(WATER_FINDINGS_STUB);

    expect($findingsFile->templateFor('2.1'))->not->toBeNull()
        ->and($findingsFile->templateFor('2.2'))->not->toBeNull()
        ->and($findingsFile->templateFor('2.4'))->not->toBeNull()
        ->and($findingsFile->templateFor('2.5'))->toBeNull();
});

test('a template lists its placeholders and the value path behind each', function () {
    $findingsFile = FindingsFile::fromFile(WATER_FINDINGS_STUB);

    $template = $findingsFile->templateFor('2.2');

    expect($template?->placeholders())->toBe([
        'declared_source', 'declared_value', 'entity_name', 'declared_date',
        'observed_source', 'observed_value', 'observed_date', 'gap_percent',
    ])
        ->and($template?->fields['declared_date'])->toBe('record.record_date|date')
        ->and($template?->fields['gap_percent'])->toBe('score.value|percent');
});

test('the closing line is read like a template', function () {
    $findingsFile = FindingsFile::fromFile(WATER_FINDINGS_STUB);

    $closingLine = $findingsFile->closingLine;

    expect($closingLine?->placeholders())->toBe(['record_age_days'])
        ->and($closingLine?->fields['record_age_days'])->toBe('score[2.5].value|whole');
});

test('a file without a closing line has none', function () {
    $path = writeFindingsFile("sub_variables:\n  '2.2':\n    narrative: 'A gap of {gap}.'\n    fields:\n      gap: score.value\n");

    $findingsFile = FindingsFile::fromFile($path);

    expect($findingsFile->closingLine)->toBeNull();
});

test('a missing file is refused', function () {
    $readMissingFile = fn () => FindingsFile::fromFile('/nowhere/findings.yml');

    expect($readMissingFile)->toThrow(InvalidFindingsFile::class, 'not found');
});

test('a file that is not a usable findings file is refused', function (string $yaml, string $expectedMessage) {
    $path = writeFindingsFile($yaml);

    $readFile = fn () => FindingsFile::fromFile($path);

    expect($readFile)->toThrow(InvalidFindingsFile::class, $expectedMessage);
})->with([
    'not a map' => ['just text', 'no sub_variables'],
    'no sub-variables' => ["sub_variables: {}\n", 'no sub_variables'],
    'no narrative' => ["sub_variables:\n  '2.2':\n    fields: {}\n", 'needs a narrative'],
    'fields not a map' => ["sub_variables:\n  '2.2':\n    narrative: 'x'\n    fields: 'x'\n", 'needs a narrative'],
    'placeholder without a field' => [
        "sub_variables:\n  '2.2':\n    narrative: 'A gap of {gap}.'\n    fields: {}\n",
        '{gap} has no valid value path',
    ],
    'unknown value path' => [
        "sub_variables:\n  '2.2':\n    narrative: 'A gap of {gap}.'\n    fields:\n      gap: database.secret\n",
        '{gap} has no valid value path',
    ],
    'unknown filter' => [
        "sub_variables:\n  '2.2':\n    narrative: 'A gap of {gap}.'\n    fields:\n      gap: score.value|shout\n",
        '{gap} has no valid value path',
    ],
    'bad closing line' => [
        "sub_variables:\n  '2.2':\n    narrative: 'x'\nclosing_line:\n  narrative: '{age}'\n",
        'closing_line: {age} has no valid value path',
    ],
]);
