<?php

// Checks that the findings engine follows a score's source_ids to the right rows: records named
// directly or through their document, observations, satellite readings and documents — and never
// another entity's rows or an observation whose consent was withdrawn.

declare(strict_types=1);

use App\Findings\EvidenceRows;
use App\Findings\LoadEvidenceRows;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

function loadEvidenceFor(string $scoreRowId): EvidenceRows
{
    return (new LoadEvidenceRows(DB::connection()))->forScore($scoreRowId);
}

test('a record named through its document is found, with its table and source name', function () {
    $entityId = insertCoreEntity();
    $documentId = insertRawDocument();
    $recordId = insertMatchedWaterRecord($entityId, $documentId);
    $scoreRowId = addSubVariableScore($entityId, '2.2', 60.0, sourceIds: [$documentId]);

    $evidence = loadEvidenceFor($scoreRowId);

    $sourceName = DB::table('raw.documents')->join('core.sources', 'core.sources.id', '=', 'raw.documents.source_id')
        ->where('raw.documents.id', $documentId)->value('core.sources.name');
    expect($evidence->recordIds())->toBe([$recordId])
        ->and($evidence->records[0]['record_table'])->toBe('water_schemes')
        ->and($evidence->records[0]['source_name'])->toBe($sourceName)
        ->and($evidence->records[0]['rated_yield_m3d'])->toEqual(500)
        ->and($evidence->documentIds)->toBe([$documentId]);
});

test('a record named by its own id is found, and its document is counted once', function () {
    $entityId = insertCoreEntity();
    $documentId = insertRawDocument();
    $recordId = insertMatchedWaterRecord($entityId, $documentId);
    $scoreRowId = addSubVariableScore($entityId, '2.2', 60.0, sourceIds: [$recordId, $documentId]);

    $evidence = loadEvidenceFor($scoreRowId);

    expect($evidence->recordIds())->toBe([$recordId])
        ->and($evidence->documentIds)->toBe([$documentId]);
});

test('observations and satellite readings are found by their ids', function () {
    $entityId = insertCoreEntity();
    $observationId = insertObservation($entityId);
    $eoStatId = insertEoStat($entityId);
    $scoreRowId = addSubVariableScore($entityId, '2.4', 100.0, sourceIds: [$observationId, $eoStatId]);

    $evidence = loadEvidenceFor($scoreRowId);

    expect($evidence->observationIds())->toBe([$observationId])
        ->and($evidence->eoStatIds())->toBe([$eoStatId])
        ->and($evidence->observations[0]['source_name'])->toStartWith('Test source')
        ->and($evidence->recordIds())->toBe([])
        ->and($evidence->hasEvidence())->toBeTrue();
});

test('another entity\'s rows are never evidence', function () {
    $entityId = insertCoreEntity();
    $otherEntityId = insertCoreEntity();
    $documentId = insertRawDocument();
    insertMatchedWaterRecord($otherEntityId, $documentId);
    $otherObservationId = insertObservation($otherEntityId);
    $scoreRowId = addSubVariableScore($entityId, '2.2', 60.0, sourceIds: [$otherObservationId]);

    $evidence = loadEvidenceFor($scoreRowId);

    expect($evidence->recordIds())->toBe([])
        ->and($evidence->observationIds())->toBe([])
        ->and($evidence->hasEvidence())->toBeFalse();
});

test('an observation whose contributor withdrew consent is never evidence', function () {
    $entityId = insertCoreEntity();
    $withdrawnObservationId = insertObservation($entityId, isConsentWithdrawn: true);
    $scoreRowId = addSubVariableScore($entityId, '2.4', 100.0, sourceIds: [$withdrawnObservationId]);

    $evidence = loadEvidenceFor($scoreRowId);

    expect($evidence->observationIds())->toBe([]);
});

test('a source id that names only a source row is not evidence', function () {
    $entityId = insertCoreEntity();
    $scoreRowId = addSubVariableScore($entityId, '1.1', 100.0, sourceIds: [insertCoreSource()]);

    $evidence = loadEvidenceFor($scoreRowId);

    expect($evidence->hasEvidence())->toBeFalse()
        ->and($evidence->documentIds)->toBe([])
        ->and($evidence->eoStatIds())->toBe([]);
});
