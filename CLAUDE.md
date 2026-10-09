# Navuuna — instructions for Claude Code

Spatial intelligence platform for Nairobi. Monorepo `github.com/Navuuna-W/navuuna` (the Bible says `navuuna/navuuna` — the org is `Navuuna-W`; use that everywhere, including CODEOWNERS and badges).
Key date: **Wed 7 Oct 2026**. Checkpoints on staging at 16:00: CP1 Wed 30 Sep · CP2 Fri 2 Oct · CP3 + freeze Mon 5 Oct · production Tue 6 Oct.

## 1. Source of truth — in this order
1. Merged ADRs in `docs/adr/` (newest wins).
2. Work packs rev 3 (27 Sep) in `docs/workpacks/` — owners, dates, task specs, scope trims.
3. `docs/BUILD_BIBLE.md` **v1.1** — rules, data model, contracts. Two known overrides:
   - Bible says Laravel 11 → use **Laravel 13, PHP 8.3** (ADR-009).
   - Bible says demo 15 Oct, sprint weeks from 18 Sep → use the 7 Oct checkpoints above.
4. PRD-001 v1.0.
Bible v1.0 (the .docx) is superseded. Never use it. If two sources conflict and the order above doesn't settle it, STOP and ask.

## Team GitHub usernames (for CODEOWNERS, reviewers, issue assignees)
- Khillon (Core): @khillon-makwana
- Devyan (Signal): @DevJ2005
- Austine (Access): @Austineigunza
If a value still says FILL-IN, STOP and ask before writing CODEOWNERS.

## 2. Do not read whole documents
The Bible is ~63 KB and each work pack ~45 KB. Jump to the section you need:
`grep -n "^### 6.3" docs/BUILD_BIBLE.md` → read only that line range.
`grep -n "K-09" docs/workpacks/khillon.md` → read only that row.

| Topic | Bible § |
|---|---|
| Rollup, gates, guard, direction | 6.2 · 6.3 · 6.4 |
| V2 / findings | 6.5 |
| Lens | 6.6 |
| Sub-variable weights | 6.7 |
| The 28 sub-variables | 6.8 |
| Ingestion + provenance | 7.2 · 7.3 |
| Stack + boxes | 8.1 · 8.2 |
| Requirements FR / NFR | 9.1 · 9.2 |
| Data model | 10 · 10.1 (superseded by ADR-004a / work pack A3) |
| API + tiles | 11.1 · 11.2 |
| Repo layout, branching, commits, PRs | 14.1 – 14.4 |
| Coding standards, principles, testing, DoD | 14.5 – 14.8 |
| Environments, multi-schema | 14.11 · 14.12 |

## 3. Lanes — edit only your lane's paths
| Path | Owner |
|---|---|
| `infra/**`, `.github/**`, migrations, `apps/api` Engine, Findings, Auth, Audit, Consumers, `services/signals/engine/**`, `services/flows/score_*` | Khillon (Core) |
| `services/ingest/**`, `data/**`, `services/signals/modules/**`, `services/flows/ingest_*`, `docs/CONTEXT.md`, `docs/modules/**`, `weights.yml` content, lens JSON | Devyan (Signal) |
| `apps/web/**`, `apps/api` Http, Resources, Tiles, Lens, Broadcasting, Observations | Austine (Access) |

Need something from another lane that isn't there yet? Do NOT write it in their path.
Build against a stub in `tests/fixtures/` or behind an interface, and record it in the progress file's stub register.

## 4. Hard rules — a PR that breaks one is not merged
- Unmeasured = `null_not_measured` with a reason. Never 0. Never a default.
- No score anywhere (API, tile, tooltip, panel) without coverage and confidence beside it.
- No held finding in front of a non-analyst in any form: no row, no count, no placeholder, no colour.
- No new sub-variables. No sub-variable weights in a lens. Weight changes need an ADR.
- One writer per table (work pack A3), enforced by DB role GRANTs: `nv_ingest`, `nv_signals`, `nv_app`.
- Every model schema-qualified: `protected $table = 'core.entities';`. Every migration has a real `down()`.
- Adapters are pure. The engine knows nothing about water or roads.
- PHP: `declare(strict_types=1)`, Pint, PHPStan L6, Pest. Python: type hints, Pydantic at boundaries, ruff, `mypy --strict` on engine + modules, pytest.
- Names come from `docs/CONTEXT.md`. Never "asset", "site" or "feature" for a scored entity.
- No TODOs in main. No secrets. No data files > 1 MB.

## 5. Code must be simple enough for a new developer
Bible §14.6: "Boring over clever. If a junior can't follow it in one read, rewrite it." This is a hard rule, not a style tip.

**Naming**
- Full, descriptive names: `subVariableScores`, `calculateCoverage()`, `is_entity_retired`. No abbreviations except the domain IDs (V1–V5, FR-07, K-09).
- Functions are verbs, variables are nouns, booleans start with `is_` / `has_` / `can_`.
- Use the words from `docs/CONTEXT.md`, so code and docs say the same thing.

**Structure**
- One job per function. Aim for under 30 lines; if it needs a comment saying "now do X", X is its own function.
- One class or concept per file. Folder names say what's inside.
- Early returns instead of deep nesting (max 2 levels of `if`).
- Explicit over magic: no clever one-liners, no nested comprehensions/ternaries, no metaprogramming, no hidden Laravel facade tricks where a plain injected class works.
- No abstraction until there are two real uses. No base class "for later".
- Named constants for every number that means something: `PROVISIONAL_CONFIDENCE_FACTOR = 0.5`, not `* 0.5`.

**Explaining the code**
- Every file starts with a 2–4 line comment: what this file is for and where it sits in the flow (e.g. "Runs after the signal service writes sub-variable scores; turns them into the five variable scores").
- Every public function/class has a docstring/PHPDoc: what it does, inputs, output, and which rule it implements (`Implements Bible §6.3 — three-state gates`).
- Comments explain **why**, not what. Link the rule: `// Never 0 for missing data — Bible §6.1, A6.`
- Every top-level folder you create gets a short `README.md`: what lives here, how to run it, how to test it.
- Tests are documentation: name them as sentences (`test_unmeasured_gate_makes_entity_provisional`) and use the Arrange / Act / Assert layout with one blank line between.

**Before calling a task done, self-review**
Read the diff as someone who joined today. For each file ask: could they say what it does in one sentence, and why it exists, without asking anyone? If not, rename, split or add the missing comment before committing.
Add this line to the PR template checklist (K-01): `- [ ] A new developer could follow this without asking the author`.

## 6. How to work (every session)
0. **GitHub Free plan: nothing on GitHub stops a bad push, so you must.** Never push to `main`. Never force-push (`--force`, `-f`). Never merge a PR — a human teammate reviews, approves and merges. Never merge with red CI. If a command would do any of these, stop and ask.
1. Start with `/resume`. The progress file and `git log` are the record of what is done.
   **Trust them. Do not re-explore the repo to rediscover finished work.** Verify a task only by running its Done check.
2. One task ID per session, one task per branch: `type/scope-short-description`.
3. Plan before code (files, tests, Done check, estimated changed lines). Wait for approval.
4. PR ≤ 400 changed lines. Bigger → split into parts (e.g. K-06a, K-06b) and record the split.
5. Commits: Conventional Commits, and always a footer `Refs: <task ID>, <FR/ADR>` so `git log --grep K-09` finds the work.
6. End every session with `/checkpoint`. If context gets long mid-task, `/checkpoint` first, then `/clear`, then `/resume`.

## 7. Commands
- PHP: `cd apps/api && ./vendor/bin/pest` · `./vendor/bin/phpstan analyse` · `./vendor/bin/pint --test`
- Python: `cd services/signals && pytest` · `mypy --strict engine modules` · `ruff check .`
- Stack: first time `infra/local/setup.sh`; after that `docker compose -f infra/docker-compose.yml up -d --build` (see `infra/README.md`)
(Update this section as tooling lands.)
