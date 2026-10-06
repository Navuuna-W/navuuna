# Finding content — narratives and candidate explanations

The words a finding uses. K-13 (findings engine) fills the narrative template when it raises
a finding; A-22 (review screen) shows the candidate explanations a reviewer picks from before
publishing or dismissing (ADR-010, Decision 3). Refs: D-20, PRD §9.4, US-401.

## Rules for every narrative

1. **Describe the gap, nothing else.** Never a cause, intent, blame or a person's name.
   "The register lists…; we observed…" — never "failed to", "neglected", "missing funds".
2. **Both sources, both dates,** every time: what was declared, by which source, on what date;
   what was observed, by which source, on what date.
3. **Plain numbers with units.** Round to whole units; percentages without decimals.
4. **Placeholders** in `{braces}` are filled from the finding's `declared` / `observed` jsonb.
   If a placeholder has no value, the finding is not raised — never a blank in the text.

## Narrative templates

| Sub-variable | Template |
|---|---|
| 2.1 Existence gap | `{declared_source} lists {entity_name} at this location (record dated {declared_date}). {observed_source} found no water point here ({observed_date}).` |
| 2.2 Magnitude gap | `{declared_source} lists a rated yield of {declared_value} m³/day for {entity_name} (record dated {declared_date}). {observed_source} reports {observed_value} m³/day ({observed_date}) — {gap_percent}% below the rated figure.` |
| 2.4 Status gap | `{declared_source} lists {entity_name} as {declared_status} (record dated {declared_date}). {observed_source} reported it as {observed_status} on {observed_date}.` |
| 2.3 Attribute gap (roads/later) | `{declared_source} lists {entity_name} as {declared_attribute} (record dated {declared_date}). {observed_source} shows {observed_attribute} ({observed_date}).` |

Roads use the same templates with road units (km, % complete) — D-23.

Record age (2.5) is never its own finding; every evidence pack ends with:
`The official record is {record_age_days} days older than our latest observation.`

## Candidate explanations (shown to the reviewer, US-401)

The reviewer must pick one, or write their own, before Dismiss. "None found" leads to Publish.

| Sub-variable | Candidate legitimate explanations |
|---|---|
| 2.1 Existence gap | Decommissioned after the record date · Relocated nearby (record not updated) · Record location is wrong (mapping error) · Underground or enclosed structure not visible · Satellite or report covers the wrong place |
| 2.2 Magnitude gap | Seasonal low yield (dry season) · Planned rationing schedule · Maintenance or repair period · Rated yield is design capacity, never meant as output · Different units in the two sources |
| 2.4 Status gap | Planned maintenance or shutdown · Seasonal closure · Temporary power outage · Recently repaired after the observation · Operating hours (observed while closed) |
| 2.3 Attribute gap | Upgrade or change after the record date · Record uses a different classification · Phased works not yet complete |
| Any | Observation was of a different entity · Duplicate record |

## What reviewers see vs what the public sees

- Candidate explanations and reviewer notes: analysts only.
- Published narrative + evidence pack: everyone.
- Held findings: **never** shown to a non-analyst in any form (CLAUDE.md §4).
