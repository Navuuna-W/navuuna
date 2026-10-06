# Fixtures — illustrative, not real measurements

Everything in this folder is **synthetic**. Nothing here was observed, surveyed or scraped.
It exists so the frontend is clickable and reviewable before the Laravel API is up.

- Water points, road segment and ward IDs are made up (`wp-###`, `rd-###`, `ward-###`).
- Coordinates sit inside the Nairobi County bounding box but do not correspond to any real
  installation.
- Scores, coverage, confidence, findings and sources are generated from a seeded PRNG so the
  same build always produces the same values. The seed is `20261006` and the generator
  lives in `generate.ts`.
- The data set covers every status flavour the panel must render:
  - fully measured, scored entities
  - partly verified (provisional) entities with confidence halved
  - cannot_assess entities with `score: null`
  - sub-variables marked `null_not_measured` with a reason
  - published findings (visible to Viewer and Analyst)
  - one held finding (visible only to Analyst)

The dev mock server (`apps/web/vite-plugins/mock-server.ts`) imports from here, and the
app shows a non-dismissable **"Prototype — illustrative data, not real measurements"**
banner whenever `VITE_DATA_SOURCE=fixture` (the default).
