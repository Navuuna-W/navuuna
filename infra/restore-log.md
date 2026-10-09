# Restore log

Every real restore of a backup, newest last (work pack K-16, NFR-04, Bible K3.5).
`infra/backup/restore.sh` adds a row each time a restore passes its check: the backup is
loaded into a scratch database and every table must hold the same number of rows as when the
backup was taken. Commit the new row so the team can see when a restore last worked.

| When | Machine | Backup restored | Dump size | Restore time | Check | Run by |
|---|---|---|---|---|---|---|
| 2026-10-09 17:32 UTC | Khillons-MacBook-Air | backups/navuuna-backups/navuuna-20261009-173212 | 92K | 1s | 68 tables, 17440 rows match | Khillon Makwana |
| 2026-10-09 17:33 UTC | Khillons-MacBook-Air | copy/navuuna-backups-box-b/navuuna-20261009-173307 | 92K | 1s | 68 tables, 17440 rows match | Khillon Makwana |
| 2026-10-09 17:34 UTC | Khillons-MacBook-Air | backups/navuuna-backups/navuuna-20261009-173422 | 92K | 1s | 68 tables, 17440 rows match | Khillon Makwana |
