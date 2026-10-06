<?php

// Creates core.entities: the scored entities (points, parcels, segments, areas) everything else
// points at. Python ingest loads them, so only `nv_ingest` writes here. The CHECKs make a wrong
// shape impossible whichever language writes the row.

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Query\Expression;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** Storage SRID for every geometry (Bible §10). Measurement uses 32737, never storage. */
    private const STORAGE_SRID = 4326;

    /**
     * Create the table, its CHECKs, its spatial index and its one writer's GRANT.
     *
     * Implements Bible §5 (four entity types and their geometry), Bible §10 (core.entities),
     * FR-02 (stable external IDs) and ADR-004a appendix (writer: nv_ingest).
     */
    public function up(): void
    {
        Schema::create('core.entities', function (Blueprint $table) {
            $table->uuid('id')->primary()->default(new Expression('public.uuid_generate_v7()'));
            $table->text('entity_type');
            // Null for shared areas such as wards, which belong to no single module.
            $table->text('module')->nullable();
            $table->text('name');
            // The ID in the source data set; stays the same across reloads (FR-02).
            $table->text('external_ref')->unique();
            // The column type fixes the SRID, so a geometry in any other SRID is rejected.
            $table->geometry('geom', subtype: 'geometry', srid: self::STORAGE_SRID);
            $table->double('radius_m')->nullable();
            $table->uuid('parent_area_id')->nullable();
            $table->jsonb('metadata')->default('{}');
            $table->timestampTz('created_at')->useCurrent();
            // Entities are retired, never deleted (ADR-004a §2: no role gets DELETE).
            $table->timestampTz('retired_at')->nullable();

            $table->spatialIndex('geom');
        });

        // Added after the create: a table can only point at its own primary key once it exists.
        Schema::table('core.entities', function (Blueprint $table) {
            $table->foreign('parent_area_id')->references('id')->on('core.entities');
        });

        $this->addEntityTypeCheck();
        $this->addGeometryMatchesTypeCheck();
        $this->addRadiusOnlyForPointsCheck();

        // The GRANT lives next to the CREATE so the two never drift (ADR-004a §2).
        DB::statement('GRANT INSERT, UPDATE ON core.entities TO nv_ingest');
    }

    /**
     * Drop the table. Its GRANT, CHECKs and index go with it.
     */
    public function down(): void
    {
        Schema::dropIfExists('core.entities');
    }

    /**
     * Only the four entity types from Bible §5.
     */
    private function addEntityTypeCheck(): void
    {
        DB::statement(
            "ALTER TABLE core.entities ADD CONSTRAINT entities_entity_type_check
             CHECK (entity_type IN ('point', 'parcel', 'segment', 'area'))"
        );
    }

    /**
     * Each entity type has one geometry type (Bible §5). Parcels and areas also accept
     * MultiPolygon, because ward and settlement boundaries often come in several pieces.
     */
    private function addGeometryMatchesTypeCheck(): void
    {
        DB::statement(
            "ALTER TABLE core.entities ADD CONSTRAINT entities_geometry_matches_type_check
             CHECK (
                 (entity_type = 'point' AND public.ST_GeometryType(geom) = 'ST_Point')
                 OR (entity_type = 'segment' AND public.ST_GeometryType(geom) = 'ST_LineString')
                 OR (entity_type IN ('parcel', 'area')
                     AND public.ST_GeometryType(geom) IN ('ST_Polygon', 'ST_MultiPolygon'))
             )"
        );
    }

    /**
     * A point is a location plus a radius in metres (Bible §5); other types have no radius.
     * "IS NOT NULL" is spelled out because a CHECK lets a NULL result through, and NULL > 0 is NULL.
     */
    private function addRadiusOnlyForPointsCheck(): void
    {
        DB::statement(
            "ALTER TABLE core.entities ADD CONSTRAINT entities_radius_only_for_points_check
             CHECK (
                 (entity_type = 'point' AND radius_m IS NOT NULL AND radius_m > 0)
                 OR (entity_type <> 'point' AND radius_m IS NULL)
             )"
        );
    }
};
