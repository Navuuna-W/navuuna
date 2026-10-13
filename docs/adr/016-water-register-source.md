# ADR-016 — Water register: hand-curated from public documents, data requests in parallel

- **Number:** 016
- **Date:** 10 Oct 2026
- **Status:** Proposed (accepted on merge)
- **Decider:** Devyan (Signal — owns assumption A3)
- **Refs:** D-02, D-07, D-15, D-16, Bible §7.1, §7.3, §18 (A3), PRD R1, FR-04

## Context

Bible §18 assumption A3 says: "WASREB / Nairobi Water publish a scrapable scheme register
with rated yields. If not, fall back to county water department documents." Every 2.x
sub-variable needs that register: `records.water_schemes` holds the rated yield, reported
production, declared status and record date the adapters compare with what is observed.

What we checked on 10 Oct 2026 (web search of the publishers' own sites and news coverage):

- **WASREB** publishes the annual IMPACT report (edition 17, June 2025). It reports
  performance per utility, such as Nairobi Water as a whole. We found no figures per scheme,
  borehole or kiosk.
- **Nairobi City Water and Sewerage Company** publishes press releases and supply-status
  PDFs with system totals. We found no borehole or scheme inventory.
- **Athi Water Works Development Agency**: news coverage of a batch of 29 city boreholes
  names the sites and a combined yield, not a yield per borehole.
- **Water Resources Authority** issues abstraction permits. We found no public permit
  register or yield data set for Nairobi.

This was a search, not a full audit of each site, and no county water department document
is in hand yet. A3 is therefore treated as **not confirmed**. Work pack D-02 already says
what happens then: if no register is confirmed by Mon 28 Sep, hand-curate it and store it
as an uploaded document so provenance holds.

## Decision

1. **The v1 water register is hand-curated from named public documents.** Each source
   document is fetched, hashed and stored in `raw.documents`, with a `core.sources` row
   that carries its publisher, licence and attribution (D-06).
2. **The curated register is itself one document**: a file with one row per scheme, stored
   and hashed like any other and given a `core.sources` row of kind `upload`. The parser
   and aligner (D-15, D-16) read it the same way they would read a scraped register, so
   nothing downstream knows it was curated by hand.
3. **Every row cites the document and page it came from.** A row with no citation is not
   loaded.
4. **Nothing is estimated.** A rated yield, production figure, status or date that is not
   in a source stays empty. The adapters then return `null_not_measured` with a reason
   (Bible §6.1); an empty cell is never filled with a typical value or a share of a total.
5. **Data requests go out in parallel** to Nairobi City Water and Sewerage Company, the
   Nairobi City County water department and the Water Resources Authority, asking for a
   scheme or borehole list with rated yields. If one arrives, it becomes a new source
   document and replaces curated rows for the schemes it covers; that swap needs no ADR.

## Consequences

- **D-07 changes.** "Water scraper #1" has no register page to scrape. It fetches and
  hashes the named public documents (re-run exits on an unchanged hash, as D1.5 asks).
- **Coverage will be thin.** Many water points will have no register row, and many rows
  will have no rated yield. 2.1 to 2.5 will be "not measured" for those, and their V2 will
  be provisional. That is the honest picture, and the map already shows it (coverage and
  confidence sit beside every score).
- **The "200 entities" target is restated** as "as many schemes as the sources support".
  The count reached is reported with the D-18 Done check, not forced.
- **Bible §18 A3** is marked resolved and points here.
- **A real register can still replace this.** If a publisher starts a scrapable register,
  D-07's scraper is pointed at it and the curated file is retired for those schemes.

## Alternatives rejected

- **Wait for a publisher to answer.** No date we control, and every 2.x adapter and the
  findings engine would have nothing to run on.
- **Synthetic register values for the map.** Rejected outright: a made-up rated yield
  would raise real findings against real schemes. Synthetic rows belong only in the test
  fixture (D-14), which is never loaded beside real entities.
- **Drop V2 from v1.** The findings, and the reason the product exists, come from V2.
