<?php

// Creates raw.documents: one row per file a scraper fetched, with its MD5 and where the bytes
// sit in MinIO. Parsers read it next (raw.text_blocks). Only Python ingest (`nv_ingest`) writes here.

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
     * Implements Bible §10 (raw.documents), Bible §7.2 (scraper: exit if the MD5 is unchanged)
     * and ADR-004a appendix (writer: nv_ingest).
     */
    public function up(): void
    {
        Schema::create('raw.documents', function (Blueprint $table) {
            $table->uuid('id')->primary()->default(new Expression('public.uuid_generate_v7()'));
            $table->foreignUuid('source_id')->constrained('core.sources');
            $table->text('url');
            $table->char('md5', 32);
            // Path of the bytes in MinIO; the database never holds the file itself.
            $table->text('storage_path');
            $table->text('mime');
            $table->timestampTz('fetched_at')->useCurrent();
            $table->smallInteger('http_status');

            // The same bytes from the same URL are stored once. The index also serves the
            // scraper's "is this MD5 new for this URL?" lookup.
            $table->unique(['url', 'md5']);
        });

        DB::statement(
            "ALTER TABLE raw.documents ADD CONSTRAINT documents_md5_is_hex_check
             CHECK (md5 ~ '^[0-9a-f]{32}$')"
        );
        DB::statement(
            'ALTER TABLE raw.documents ADD CONSTRAINT documents_http_status_check
             CHECK (http_status BETWEEN 100 AND 599)'
        );

        // The GRANT lives next to the CREATE so the two never drift (ADR-004a §2).
        DB::statement('GRANT INSERT, UPDATE ON raw.documents TO nv_ingest');
    }

    /**
     * Drop the table. Its GRANT and CHECKs go with it.
     */
    public function down(): void
    {
        Schema::dropIfExists('raw.documents');
    }
};
