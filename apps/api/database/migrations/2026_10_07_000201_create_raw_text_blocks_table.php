<?php

// Creates raw.text_blocks: the text a parser cut out of one document, block by block, with no
// interpretation (Bible §7.2). Every record field points back at one of these blocks.
// Only Python ingest (`nv_ingest`) writes here.

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Query\Expression;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Create the table, its CHECKs and its one writer's GRANT.
     *
     * Implements Bible §10 (raw.text_blocks), Bible §7.2 (parser) and ADR-004a appendix (writer: nv_ingest).
     */
    public function up(): void
    {
        Schema::create('raw.text_blocks', function (Blueprint $table) {
            $table->uuid('id')->primary()->default(new Expression('public.uuid_generate_v7()'));
            $table->foreignUuid('document_id')->constrained('raw.documents');
            $table->integer('page');
            $table->integer('block_index');
            $table->text('text');

            // A block appears once per page of its document.
            $table->unique(['document_id', 'page', 'block_index']);
        });

        // Blueprint has no array column type, so this one is added in SQL.
        // [x0, top, x1, bottom] in PDF points; null for HTML, which has no layout box.
        DB::statement('ALTER TABLE raw.text_blocks ADD COLUMN bbox double precision[]');

        DB::statement(
            'ALTER TABLE raw.text_blocks ADD CONSTRAINT text_blocks_position_check
             CHECK (page >= 1 AND block_index >= 0)'
        );
        DB::statement(
            'ALTER TABLE raw.text_blocks ADD CONSTRAINT text_blocks_bbox_has_four_numbers_check
             CHECK (bbox IS NULL OR cardinality(bbox) = 4)'
        );

        // The GRANT lives next to the CREATE so the two never drift (ADR-004a §2).
        DB::statement('GRANT INSERT, UPDATE ON raw.text_blocks TO nv_ingest');
    }

    /**
     * Drop the table. Its GRANT and CHECKs go with it.
     */
    public function down(): void
    {
        Schema::dropIfExists('raw.text_blocks');
    }
};
