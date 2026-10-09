-- Exact row count of every table in the database, one "schema.table|count" line each.
-- backup.sh saves this next to each dump; restore.sh runs it on the restored copy and compares.
-- query_to_xml runs a count(*) per table inside one query, so no table list is kept by hand.
SELECT table_schema || '.' || table_name AS table_full_name,
       (xpath('/row/row_count/text()',
              query_to_xml(format('SELECT count(*) AS row_count FROM %I.%I', table_schema, table_name),
                           false, true, '')))[1]::text::bigint AS row_count
FROM information_schema.tables
WHERE table_type = 'BASE TABLE'
  AND table_schema NOT IN ('pg_catalog', 'information_schema')
ORDER BY table_full_name;
