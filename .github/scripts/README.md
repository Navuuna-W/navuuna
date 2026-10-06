# .github/scripts

Helper scripts that CI jobs in `../workflows/ci.yml` run. Each one has its tests in `tests/`.

| Script | CI job | What it checks |
|---|---|---|
| `lint_migrations.py` | `migration-lint` | Every Laravel migration has a real `down()` and every `Schema::create` names a schema (`core.entities`, not `entities`) — CLAUDE.md §4 |

## Run

```sh
python .github/scripts/lint_migrations.py                 # checks apps/api/database/migrations
python .github/scripts/lint_migrations.py path/to/folder  # checks another folder
```

## Test

```sh
pytest .github/scripts/tests
```
