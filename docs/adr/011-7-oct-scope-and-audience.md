# ADR-011 — 7 Oct: checkpoints, audience, trimmed scope, pitch graphics

- **Number:** 011
- **Date:** 6 Oct 2026
- **Status:** Proposed (accepted on merge by Devyan)
- **Decider:** Devyan (Signal — owns DEC-02, DEC-21, DEC-22, DEC-23)
- **Refs:** work packs rev 3, Bible §3.3, §13, §17

## Context

The key date moved from 15 Oct to **Wed 7 Oct 2026**. The work packs (rev 3) re-planned
around it and proposed trims that need the product owner's confirmation. Four decisions
were open past their due dates.

## Decisions

**DEC-02 — Checkpoints.** Bible §13 is re-baselined to: CP1 Wed 30 Sep · CP2 Fri 2 Oct ·
CP3 + freeze Mon 5 Oct · production Tue 6 Oct · key date Wed 7 Oct, all on staging at 16:00.

**DEC-22 — Audience.** 7 Oct is shown to a **county planner**. It matches the one lens we
built (`lenses/county_planner.json`), the persona and the grant path. Investor framing is
shown only as roadmap.

**DEC-23 — Trimmed scope: confirmed as proposed.**

| Area | For 7 Oct | Moved after 7 Oct (due 31 Oct) |
|---|---|---|
| Water adapters | 1.1, 2.1, 2.2, 2.4, 2.5, 1.2, 5.2, 4.4 | 5.1, 4.1, 1.3 |
| Ingest | Scripts run by hand, logged | Scheduled Prefect flows |
| Provenance | One manual walk of 20 scores before freeze | Nightly flow |
| Findings | ≥ 3 reviewed findings | Outcome seeding (with K-17) |
| Roads | One hand-picked contract, one segment | Full FR-08 set |
| Momentum | — | V3 on wards (FR-18) |
| Tiles | Water points, wards, the one road; SQL clusters only if > ~1,000 features | — |

**DEC-21 — Pitch graphics (22 Sep).** Pitch and roadmap only, not scope. Multi-module Parcel
Passport, Scenario Engine, SMI and the Energy stamp are logged as Bible §17 backlog. The A7
fix list is applied before any external use. Vocabulary follows DEC-20 (`docs/CONTEXT.md`).

## Consequences

- Nothing on the "moved" list is a 7 Oct blocker; owners stop work on it until 8 Oct.
- Anything not trimmed above stays P0 for 7 Oct.
- Bible v1.2 (D-01) copies this table into §13 and §17.
