# ADR-014 — Prefect 3.x, not 2.x

- **Number:** 014
- **Date:** 9 Oct 2026
- **Status:** Proposed (accepted on merge)
- **Decider:** Khillon (Core — owns orchestration, DEC-18)
- **Refs:** DEC-18, K-12, D-22, Bible §8.1, §8.2, NFR-10

## Context

Bible §8.1 locks orchestration to "Prefect 2.x". It was written before anyone compared
versions, and the work packs left DEC-18 open: "decide before the first flow is written".
No flow exists yet. There is no `services/flows/` and no Prefect dependency in the repo, so
switching versions costs nothing today, but it will cost something after the first flow.

What we checked on 9 Oct 2026 (PyPI and the Prefect docs):

- Prefect 3.0 has been out since 3 Sep 2024. The newest release is 3.8.8 (6 Oct 2026).
- 2.x gets only occasional patch releases (2.20.25 in Dec 2025, 2.20.26 in Sep 2026).
  Prefect says 2.x gets significant bugfixes but no new features, and it has published
  no end-of-life date.
- In 3.x, workers and work pools are the standard way to run flows, and agents are
  deprecated. K-12 already asks for "worker, work pools".
- 3.x is built on Pydantic 2, which the signal service already uses (`pydantic>=2.9,<3`).
  It supports Python 3.10 to 3.14, so our Python 3.12 is fine.
- A self-hosted Prefect server only works with clients on the same major version. Both
  lanes must therefore use one version: Devyan's ingest flows and Core's scoring flow.

## Decision

1. Navuuna uses **Prefect 3.x**, pinned `prefect>=3.8,<4`, everywhere: the server and
   worker on Box B (K-12), the local compose stack, and every flow in `services/flows/`.
2. Flows run on **workers in work pools**. Deployments are defined in code
   (`flow.deploy` / `prefect.yaml`), not with the 2.x `prefect deployment build`.
3. The Prefect server and every client get upgraded together, in one PR. A new major
   version (4.x) needs a new ADR.

This overrides the "Prefect 2.x" cell of Bible §8.1. The rest of §8.1 (Prefect as
the orchestrator, Python-native, not Airflow or cron alone) stands.

## Alternatives rejected

- **Prefect 2.x, as locked.** It is in maintenance mode, and we would have to migrate
  every flow later. Its agents and `prefect deployment build` were already deprecated in 2.x.
- **Cron alone.** Already rejected in Bible §8.1: no retries, no run history, and no
  failure alerts to the team channel (NFR-10).

## Consequences

- K-12 builds the server, worker, work pool and `score_all` flow on Prefect 3.
- Devyan's ingest flows (D-22, `services/flows/ingest_*`) use the same pin. Examples and
  answers written for Prefect 2 (agents, `Deployment.build_from_flow`) do not apply.
- The Prefect server keeps its own database. Moving to a new minor version means running
  `prefect server database upgrade` once on Box B.
