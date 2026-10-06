<?php

// Adds public.uuid_generate_v7(), the default for every `id` column in the database.
// PostgreSQL 16 has no built-in UUIDv7, and IDs must be time-ordered whether Laravel or the
// Python services insert the row, so the database makes them (Bible §10, ADR-012 §2).

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Builds a UUIDv7 as laid out in RFC 9562 §5.7:
     * bytes 0–5 are the Unix time in milliseconds, the other 10 bytes are random,
     * then the version (7) and variant (binary 10) bits are written over the random ones.
     */
    private const CREATE_FUNCTION_SQL = <<<'SQL'
        CREATE OR REPLACE FUNCTION public.uuid_generate_v7() RETURNS uuid
        LANGUAGE plpgsql VOLATILE
        AS $$
        DECLARE
            unix_time_milliseconds bytea;
            uuid_bytes bytea;
        BEGIN
            -- int8send gives 8 big-endian bytes; the timestamp is the last 6 of them.
            unix_time_milliseconds := substring(
                int8send(floor(extract(epoch FROM clock_timestamp()) * 1000)::bigint) FROM 3
            );

            -- The remaining 10 bytes come from a random v4 UUID (gen_random_uuid is built in).
            uuid_bytes := unix_time_milliseconds || substring(uuid_send(gen_random_uuid()) FROM 7);

            -- Byte 6: keep the low 4 bits (mask 0x0F = 15), set the version nibble to 7 (0x70 = 112).
            uuid_bytes := set_byte(uuid_bytes, 6, (get_byte(uuid_bytes, 6) & 15) | 112);

            -- Byte 8: keep the low 6 bits (mask 0x3F = 63), set the variant bits to 10 (0x80 = 128).
            uuid_bytes := set_byte(uuid_bytes, 8, (get_byte(uuid_bytes, 8) & 63) | 128);

            RETURN encode(uuid_bytes, 'hex')::uuid;
        END
        $$
        SQL;

    /**
     * Create the UUIDv7 function.
     *
     * Implements ADR-012 §2 — UUIDv7 as a database default.
     */
    public function up(): void
    {
        DB::statement(self::CREATE_FUNCTION_SQL);
    }

    /**
     * Drop the UUIDv7 function. Runs after every table that uses it as a default is gone.
     */
    public function down(): void
    {
        DB::statement('DROP FUNCTION IF EXISTS public.uuid_generate_v7()');
    }
};
