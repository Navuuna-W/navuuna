<?php

// Checks that a finding's narrative is filled from the water stub's templates exactly as
// docs/modules/findings.md asks: both sources, both dates in Nairobi time, whole numbers — and
// that a missing value means no narrative at all (rule 4). Pure unit tests.

declare(strict_types=1);

use App\Findings\FindingsFile;
use App\Findings\FindingTemplate;
use App\Findings\NarrativeFiller;
use App\Findings\NarrativeValues;

/**
 * The rows of a water point whose register promises 500 m³/day and reports 200 m³/day.
 *
 * @param  array<string, mixed>  $recordOverrides
 */
function waterPointValues(array $recordOverrides = []): NarrativeValues
{
    return new NarrativeValues(
        entity: ['name' => 'Kibera Water Scheme'],
        record: array_merge([
            'source_name' => 'Nairobi Water Register 2024',
            'rated_yield_m3d' => 500,
            'reported_production_m3d' => 200.4,
            'status_declared' => 'operational',
            'record_date' => '2024-03-01',
        ], $recordOverrides),
        observation: [
            'source_name' => 'Community report',
            'observed_at' => '2026-09-13T22:30:00+00:00',
            'payload' => ['operational' => 'no'],
        ],
        eoStat: null,
        score: ['value' => 0.5992, 'unit' => 'fraction'],
        newestScoresBySubId: ['1.2' => ['value' => 'no'], '2.5' => ['value' => 927.6]],
    );
}

function waterTemplate(string $subId): FindingTemplate
{
    $template = FindingsFile::fromFile(__DIR__.'/../../fixtures/modules/water/findings.yml')->templateFor($subId);
    assert($template !== null);

    return $template;
}

test('a magnitude-gap narrative names both figures in whole units and the gap as a percentage', function () {
    $filled = NarrativeFiller::fill(waterTemplate('2.2'), waterPointValues());

    expect($filled?->text)->toBe(
        'Nairobi Water Register 2024 lists a rated yield of 500 m³/day for Kibera Water Scheme '
        .'(record dated 1 Mar 2024). Nairobi Water Register 2024 reports 200 m³/day (1 Mar 2024) '
        .'— 60% below the rated figure.'
    );
});

test('dates are shown in Nairobi time, so a late-evening UTC report moves to the next day', function () {
    $filled = NarrativeFiller::fill(waterTemplate('2.4'), waterPointValues());

    expect($filled?->text)->toBe(
        'Nairobi Water Register 2024 lists Kibera Water Scheme as operational (record dated 1 Mar 2024). '
        .'Community report reported it as no on 14 Sep 2026.'
    );
});

test('declared and observed values are kept without their prefix', function () {
    $filled = NarrativeFiller::fill(waterTemplate('2.4'), waterPointValues());

    expect($filled?->declared)->toBe([
        'source' => 'Nairobi Water Register 2024', 'status' => 'operational', 'date' => '1 Mar 2024',
    ])
        ->and($filled?->observed)->toBe([
            'source' => 'Community report', 'status' => 'no', 'date' => '14 Sep 2026',
        ]);
});

test('the closing line reads another sub-variable\'s score', function () {
    $closingLine = FindingsFile::fromFile(__DIR__.'/../../fixtures/modules/water/findings.yml')->closingLine;
    assert($closingLine !== null);

    $filled = NarrativeFiller::fill($closingLine, waterPointValues());

    expect($filled?->text)->toBe('The official record is 928 days older than our latest observation.');
});

test('a jsonb field is read by walking into it', function () {
    $template = new FindingTemplate('Reported {state}.', ['state' => 'observation.payload.operational']);

    $filled = NarrativeFiller::fill($template, waterPointValues());

    expect($filled?->text)->toBe('Reported no.');
});

test('a missing value means no narrative, never a blank', function (array $recordOverrides) {
    $filled = NarrativeFiller::fill(waterTemplate('2.2'), waterPointValues($recordOverrides));

    expect($filled)->toBeNull();
})->with([
    'null column' => [['rated_yield_m3d' => null]],
    'empty text' => [['source_name' => '  ']],
    'not a date' => [['record_date' => 'last spring']],
    'not a number' => [['reported_production_m3d' => 'about two hundred']],
    'not plain text' => [['source_name' => ['nested' => 'value']]],
]);

test('a row the finding does not have means no narrative', function () {
    $template = new FindingTemplate('Seen by {source}.', ['source' => 'eo_stat.source_name']);

    $filled = NarrativeFiller::fill($template, waterPointValues());

    expect($filled)->toBeNull();
});

test('a score of a sub-variable the entity does not have means no narrative', function () {
    $template = new FindingTemplate('Stale by {days}.', ['days' => 'score[2.3].value|whole']);

    $filled = NarrativeFiller::fill($template, waterPointValues());

    expect($filled)->toBeNull();
});
