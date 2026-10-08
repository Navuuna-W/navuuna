<?php

// Checks that saving a finding writes the flags.flags row and its evidence pack and holds it
// through FlagWorkflow, all in one transaction — and that a finding another run saved first is
// skipped, not an error (DEC-09 rule 5).

declare(strict_types=1);

use App\Findings\EvidenceRows;
use App\Findings\FilledNarrative;
use App\Findings\FindingToRaise;
use App\Findings\FlagState;
use App\Findings\SaveHeldFinding;
use App\Findings\Severity;
use App\Models\Flag;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

/**
 * Save a medium 2.2 finding for the entity, backed by one record, one observation and one
 * document. Returns what save() returned.
 *
 * @param  array<string, mixed>  $scoreRow
 */
function saveMagnitudeFinding(string $entityId, array $scoreRow = ['value' => 0.6, 'adapter_version' => '1.2.0']): bool
{
    $documentId = insertRawDocument();
    $evidence = new EvidenceRows(
        records: [['id' => insertMatchedWaterRecord($entityId, $documentId), 'document_id' => $documentId]],
        observations: [['id' => insertObservation($entityId)]],
        eoStats: [],
        documentIds: [$documentId],
    );
    $filledNarrative = new FilledNarrative('The register lists 500 m³/day; it reports 200.', ['value' => '500'], ['value' => '200']);

    return app(SaveHeldFinding::class)->save(
        $entityId,
        new FindingToRaise('2.2', Severity::Medium, measured('2.2', 60, 0.9)),
        $evidence,
        'The register lists 500 m³/day; it reports 200. The record is 50 days old.',
        $filledNarrative,
        $scoreRow,
    );
}

test('the finding is saved with its severity, values and gap, and held', function () {
    $entityId = insertCoreEntity();

    $isSaved = saveMagnitudeFinding($entityId);

    $finding = Flag::sole();
    expect($isSaved)->toBeTrue()
        ->and($finding->entity_id)->toBe($entityId)
        ->and($finding->sub_id)->toBe('2.2')
        ->and($finding->severity)->toBe('medium')
        ->and($finding->state)->toBe(FlagState::Held)
        ->and($finding->declared)->toMatchArray(['value' => '500'])
        ->and($finding->observed)->toMatchArray(['value' => '200'])
        ->and($finding->gap)->toEqual(0.6);
});

test('a yes/no gap is saved with no gap size', function () {
    $entityId = insertCoreEntity();

    saveMagnitudeFinding($entityId, ['value' => 'operational → no', 'adapter_version' => '1.0.0']);

    expect(Flag::sole()->gap)->toBeNull();
});

test('the evidence pack holds the narrative, every evidence id and the adapter version', function () {
    $entityId = insertCoreEntity();

    saveMagnitudeFinding($entityId);

    $pack = DB::table('flags.evidence_packs')->sole();
    $recordId = DB::table('records.water_schemes')->where('entity_id', $entityId)->value('id');
    $observationId = DB::table('core.observations')->where('entity_id', $entityId)->value('id');
    expect($pack->flag_id)->toBe(Flag::sole()->id)
        ->and($pack->narrative)->toBe('The register lists 500 m³/day; it reports 200. The record is 50 days old.')
        ->and($pack->record_ids)->toBe("{{$recordId}}")
        ->and($pack->observation_ids)->toBe("{{$observationId}}")
        ->and($pack->eo_stat_ids)->toBe('{}')
        ->and($pack->adapter_version)->toBe('1.2.0');
});

test('the move to held is recorded as a system move with a note', function () {
    $entityId = insertCoreEntity();

    saveMagnitudeFinding($entityId);

    $transition = DB::table('flags.transitions')->sole();
    expect($transition->from_state)->toBe('detected')
        ->and($transition->to_state)->toBe('held')
        ->and($transition->user_id)->toBeNull()
        ->and($transition->note)->toBe('Raised by the findings engine: 2.2 scored 60 with confidence 0.9 (ADR-010 DEC-09).');
});

test('if holding the finding fails, the finding and its evidence pack are rolled back too', function () {
    $entityId = insertCoreEntity();
    DB::unprepared("
        CREATE FUNCTION pg_temp.refuse_audit() RETURNS trigger AS \$\$
        BEGIN RAISE EXCEPTION 'audit refused for test'; END \$\$ LANGUAGE plpgsql;
        CREATE TRIGGER refuse_audit BEFORE INSERT ON audit.events
        FOR EACH ROW EXECUTE FUNCTION pg_temp.refuse_audit();
    ");

    $save = fn () => saveMagnitudeFinding($entityId);

    expect($save)->toThrow(Exception::class, 'audit refused for test');
    expect(Flag::count())->toBe(0)
        ->and(DB::table('flags.evidence_packs')->count())->toBe(0);
});

test('a finding another run saved first is skipped, not an error', function () {
    $entityId = insertCoreEntity();
    DB::unprepared("
        CREATE FUNCTION pg_temp.save_first() RETURNS trigger AS \$\$
        BEGIN
            INSERT INTO flags.flags (entity_id, sub_id, severity, state, declared, observed)
            VALUES (NEW.entity_id, NEW.sub_id, 'low', 'held', '{}', '{}');
            RETURN NEW;
        END \$\$ LANGUAGE plpgsql;
        CREATE TRIGGER save_first BEFORE INSERT ON flags.flags
        FOR EACH ROW WHEN (pg_trigger_depth() = 0) EXECUTE FUNCTION pg_temp.save_first();
    ");

    $isSaved = saveMagnitudeFinding($entityId);

    // The trigger's row lives in our transaction, so it rolls back with it; in production the
    // other run's row is already committed. What matters here: no exception, reported as skipped.
    expect($isSaved)->toBeFalse();
});
