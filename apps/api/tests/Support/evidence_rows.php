<?php

// Helpers that insert the rows a score's source_ids can name — a register record, a community
// observation, a satellite reading — for the findings engine's database tests.
// Loaded once from tests/Pest.php.

declare(strict_types=1);

use Illuminate\Support\Facades\DB;

/**
 * Insert a water-register record matched to the entity and return its id.
 *
 * @param  array<string, mixed>  $overrides
 */
function insertMatchedWaterRecord(string $entityId, string $documentId, array $overrides = []): string
{
    return (string) DB::table('records.water_schemes')->insertGetId(array_merge([
        'entity_id' => $entityId,
        'name' => 'Kibera Water Scheme',
        'rated_yield_m3d' => 500,
        'reported_production_m3d' => 200,
        'status_declared' => 'operational',
        'record_date' => '2024-03-01',
        'document_id' => $documentId,
        'block_id' => insertRawTextBlock($documentId),
        'aligner_version' => '1.0.0',
        'alignment_confidence' => 0.9,
    ], $overrides));
}

/**
 * Insert a community observation of the entity and return its id. With $isConsentWithdrawn the
 * contributor has since withdrawn consent (DEC-14), so nothing may use the observation.
 */
function insertObservation(string $entityId, bool $isConsentWithdrawn = false): string
{
    $contributorId = (string) DB::selectOne('SELECT public.uuid_generate_v7() AS id')->id;
    $consentId = DB::table('core.consents')->insertGetId([
        'contributor_id' => $contributorId,
        'scope' => 'observations',
        'text_version' => 'v1',
        'granted_at' => '2026-09-01 10:00:00+00',
        'withdrawn_at' => $isConsentWithdrawn ? '2026-10-01 10:00:00+00' : null,
    ]);

    return (string) DB::table('core.observations')->insertGetId([
        'entity_id' => $entityId,
        'source_id' => insertCoreSource(),
        'kind' => 'ground_report',
        'payload' => '{"existence": "exists", "operational": "no"}',
        'observed_at' => '2026-09-14 10:00:00+00',
        'contributor_id' => $contributorId,
        'consent_id' => $consentId,
    ]);
}

/**
 * Insert a satellite reading of the entity and return its id.
 */
function insertEoStat(string $entityId): string
{
    return (string) DB::table('raw.eo_stats')->insertGetId([
        'source_id' => insertCoreSource(),
        'entity_id' => $entityId,
        'product' => 'sentinel-2',
        'index_name' => 'ndwi',
        'value' => 0.12,
        'acquired_at' => '2026-09-10 08:00:00+00',
    ]);
}
