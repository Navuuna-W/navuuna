# NAVUUNA — Build Bible

**Version 1.1 · 20 September 2026 · Internal**

Owners: Devyan Jethwa A. (product, data layer) · Khillon (backend, infrastructure) · Austine (frontend)
Demo: **Thursday 15 October 2026**

> v1.1 folds in the six backend decisions raised on 20 September. Changes from v1.0 are marked **[NEW in 1.1]** and listed in §19.

---

## 0. How to use this document

This is the single source of truth. If it is not here, it is not in scope. A requirement changes here first, with a change-log entry (§19), and only then in code.

- Read **§1–§5** once.
- Keep **§6–§12** open while building.
- **§13** is the sprint plan and task breakdown. It changes weekly.
- **§14** is the engineering rulebook. Read it before your first PR.

> **Requirements freeze.** §6 (variable engine), §9 (functional requirements) and §10 (data model) are frozen until 15 October. New ideas go to §17 backlog, not into the sprint.

---

## 1. Decisions locked

| Decision | Value | Notes |
|---|---|---|
| Name | **NAVUUNA** | Company and product. "Urban Eye" and "Nuvola Atlas" are retired everywhere — code, UI, docs, pitches. |
| Product line | **LAND** | Modules in order: Water & Sanitation → Roads & Infrastructure → Land (incl. agriculture) → Energy → Safety & Security |
| Pilot geography | Nairobi County | No base layers loaded yet. Week 1 work. |
| Variable framework | 5 variables, 28 sub-variables, **frozen** | Safety & Security is scored as area-type entities on the same 28. No new sub-variables. |
| Sub-variable weights | **Engine-owned, fixed, versioned** | §6.7. Lenses may NOT reweight sub-variables. **[NEW in 1.1]** |
| Freedom Index | A Layer 4 lens over the 5 variables | Not a separate computation. §6.9 |
| Backend | Laravel 11 / PHP 8.3 | |
| Database | PostgreSQL 16 + PostGIS 3.4, **7 schemas, search_path set in migration 0001** | §10, §14.12 **[NEW in 1.1]** |
| Signal service | FastAPI / Python 3.12 | Writes `raw.*` and `scores.sub_variable_scores` only |
| Map delivery | **Vector tiles (ST_AsMVT) for the map; GeoJSON for the institutional API** | §11.2 **[NEW in 1.1]** |
| Frontend | MapLibre GL JS + TypeScript + Vite + (Vue 3 or React — Austine decides by 22 Sep, then fixed) | |
| Hosting | **Two VPS boxes** (app + worker), VitoDeploy | §8.2 **[NEW in 1.1]** |
| Aligner | **Deterministic parsing + Pydantic for v1.** LLM (Ollama/Llama 3) deferred off the demo path | §7.2 **[NEW in 1.1]** |
| Earth observation | **Cloud-processed zonal stats (openEO / DE Africa) where possible**, raster download only when unavoidable | §7.1 **[NEW in 1.1]** |
| Budget | ≤ USD 150/month infrastructure (was 100) | NFR-11 **[NEW in 1.1]** |
| Cut from v1 | Mapbox, CesiumJS/3D, blockchain, Gaussian Splatting, Smart Urban Pods hardware, Go backend | §16 |
| Team | Devyan (product + data), Khillon (backend + infra), Austine (frontend) | All full-time. §12 |
| First customer | Undecided; county government via grant-backed deployment is the working assumption | Build the county-planner lens first |

---

## 2. Current state (20 September 2026)

- Variable framework: defined and frozen.
- Data: manual collection from Kenyan government websites has started. No production scraper. **No Nairobi base layers loaded.**
- Code: monorepo being created this week. Week 1 is day 3.
- Customers: none signed.
- Hardware: none. Pods are roadmap only.
- Ideas Festival: not selected; appeal sent. Does not change the build.

Anything in older documents that contradicts this (pods first, owning the whole data lifecycle, 3D walkthroughs, Sovereign Ledger, Go backend) is superseded.

---

## 3. What we are building

### 3.1 The problem

Institutions that put money against physical assets in Kenya — counties, utilities, lenders, funds, donors — cannot ask the open spatial data a specific question about a specific asset and get a validated answer. So they survey, guess, or trust the paper record. Navuuna computes a fixed set of variables about each asset once, from open data plus scraped records plus ground observation, and lets each institution read those variables through its own lens.

### 3.2 The product

A web application and an API. The map shows Nairobi with scored entities. Clicking an entity shows its five variable scores — each with **coverage** and **confidence** — the sub-variables behind them, the sources behind each sub-variable, and any discrepancy flags with their evidence packs. A lens selector changes weighting and colour. The API serves the same data as JSON.

### 3.3 The 15 October demo — definition of success

1. Nairobi map with ≥ 200 water & sanitation entities loaded and scored.
2. Any entity: five variable scores with coverage, confidence and sub-variable breakdown.
3. ≥ 3 discrepancy flags, each with an evidence pack and a visible hold/release state.
4. One working lens (county planner) that re-colours the map.
5. ≥ 1 road segment scored from a scraped contract list — proves the engine is not water-specific.
6. A live update: new observation arrives → score changes on screen via Reverb.
7. The API returns the same entity as JSON.

**If it is not on this list, it is not in the demo.**

---

## 4. Architecture

### 4.1 Four logical layers

Sector logic lives in Layer 1 only. This is the anti-scope-creep mechanism.

```
LAYER 4   LENS              per customer, configurable
          Weights across the 5 VARIABLES, thresholds, direction of goodness
          NOT sub-variable weights                              [NEW in 1.1]
                ▲
LAYER 3   VARIABLES         FROZEN — exactly 5
                ▲
LAYER 2   SUB-VARIABLES     FROZEN — 28, with engine-owned fixed weights
                ▲
LAYER 1   SIGNAL ADAPTERS   GROWS — one per (sector × sub-variable)
                            The only sector-specific code in the system
```

| Layer | Changes when | Owner |
|---|---|---|
| 4 Lens | New customer or use case | Devyan |
| 3 Variables | Never | Khillon |
| 2 Sub-variables | Never | Khillon |
| 1 Adapters | New sector, entity type or data source | Devyan (spec) / Khillon (code) |

> **Rule.** If a new sector seems to need a new sub-variable, it is not cross-cutting. Either it is a lens threshold, or it is a custom build priced as one. It does not enter Layer 2.

### 4.2 Components

| Component | Tech | Responsibility | Owner |
|---|---|---|---|
| Scrapers / bots | Python, Playwright, Scrapy | Fetch and hash government documents and pages | Devyan |
| Parsers | pypdf, pdfplumber, BeautifulSoup | Documents → text blocks | Devyan |
| Aligner | Pydantic (+ Instructor/Ollama **deferred**) | Text blocks → typed records with confidence | Devyan |
| EO ingestion | openEO / DE Africa API, rasterio, GDAL | Zonal stats per entity (NDWI/NDVI/NDBI) | Devyan |
| Vector ingestion | ogr2ogr, GDAL | OSM, GRID3, boundaries → PostGIS | Devyan |
| Orchestrator | Prefect | Schedule, retry, skip-on-unchanged-hash | Khillon |
| Signal service | FastAPI | Runs adapters; writes sub-variable scores | Khillon |
| Engine + API | Laravel 11 | Rollup, gates, lenses, flags, evidence, auth, tiles | Khillon |
| Database | PostgreSQL 16 + PostGIS 3.4 | Everything persistent | Khillon |
| Real-time | Laravel Reverb | Push score/flag changes | Khillon |
| Frontend | MapLibre GL JS, TS, Vite | Map (MVT), entity panel, lens selector, admin | Austine |
| Base tiles | Protomaps PMTiles (self-hosted) | Base map, no paid provider | Austine |
| Deployment | VitoDeploy, Docker Compose | Two boxes | Khillon |

### 4.3 Data flow

```
Sources (govt PDFs/portals, Sentinel-2, OSM, GRID3, DE Africa, people's own data)
   │  scrape / process / upload                       [Python + Prefect, Box B]
   ▼
raw.documents   raw.text_blocks   raw.eo_stats   raw.vectors     (hash, url, fetched_at)
   │  parse + align                                   [pypdf, Pydantic]
   ▼
records.*  (water_schemes, road_contracts …)  + provenance
   │  adapters, one per (module × sub_id)             [FastAPI signal service]
   ▼
scores.sub_variable_scores   (value, score, confidence, status, observed_at, source_ids)
   │  ── Redis event ──▶ Laravel rollup               [gates, guard, coverage, weights]
   ▼
scores.variable_scores  +  flags.flags  +  flags.evidence_packs
   │  lens weights, thresholds, direction             [Laravel]
   ▼
MVT tiles ──▶ map          GeoJSON/JSON API ──▶ institutions      Reverb ──▶ live updates
```

### 4.4 What "modular" means

A module is a directory: `services/signals/modules/<module>/` containing one adapter file per sub-variable it implements, a default `lens.json`, and a README listing its sources. **Nothing else in the codebase references a module by name** — the engine discovers adapters through `core.adapters`. Deleting a module directory must not break the build. Tested in CI (§14.8).

---

## 5. Scored entities

Do not say "asset". A **scored entity** is anything with a boundary and a persistent identity.

| Type | Geometry | Examples in v1 |
|---|---|---|
| `point` | Point + radius (m) | Water point, borehole, pump, treatment works, substation, police post |
| `parcel` | Polygon | Farm, plot, building footprint |
| `segment` | LineString | Road section, pipeline, transmission line, drain |
| `area` | Polygon | Ward, sub-county, catchment, estate, informal settlement |

Roads are segments. Water points are points. **Density and safety are areas** — you cannot score foot traffic on a parcel. All four types are scored by the same 28 sub-variables; the adapter decides what the signal is for each type.

---

## 6. The variable engine

### 6.1 What every sub-variable emits

| Field | Range | Meaning |
|---|---|---|
| `value` | native units | Raw measurement (litres/day, km, days, ratio) |
| `score` | 0–100 | Normalised, **direction-neutral** |
| `confidence` | 0–1 | Set by the adapter — only the adapter knows how good its input was |
| `status` | `measured` \| `null_not_measured` | |
| `null_reason` | text | Why it could not be measured |
| `observed_at` | timestamptz | When the observation was made, not when computed |
| `source_ids` | uuid[] | Every document/raster/vector that fed the value |

A sub-variable that could not be measured is `null_not_measured` with a reason. **Never 0. Never a default.** Under-mapped areas have absent attributes, not good ones.

### 6.2 Rollup

```
variable_score      = Σ(sub_score × weight × confidence) / Σ(weight × confidence)
                      over AVAILABLE sub-variables only

variable_coverage   = Σ(weight of available sub-variables) / Σ(weight of all sub-variables)

variable_confidence = Σ(weight × confidence) / Σ(weight)     over available sub-variables
```

The denominator renormalises over available sub-variables. **A missing sub-variable is excluded, never zeroed.** Coverage is stored and displayed beside every score. This cannot be switched off at the lens layer.

### 6.3 Gates, guards and the unmeasured-gate rule **[NEW in 1.1]**

- **Gate** (1.1 Presence, 2.1 Existence gap): if it fails, the parent variable returns `cannot_assess` regardless of everything else. **Gates veto; contributors average.**
- **Guard** (2.6 Explanation state): a discrepancy flag stays `held` until a human records whether a legitimate explanation exists.

Three gate states, not two:

| Gate state | Variable status | Behaviour |
|---|---|---|
| Measured, **passed** | `scored` | Normal rollup |
| Measured, **failed** | `cannot_assess` | No score. No contributors averaged. Final. |
| **Unmeasured** | `provisional` | Roll up available contributors, **multiply variable confidence × 0.5**, set `gate_status = 'unmeasured'`, and **never emit a V2 flag for this entity** |

Rationale: "we looked and it isn't there" and "we couldn't look" are different facts. Collapsing them is how you accuse a county of a phantom borehole because the imagery was cloudy. `provisional` keeps the demo populated without lying.

UI rule: `provisional` renders with a distinct badge and the null reason on hover. Never silently.

### 6.4 Direction of goodness

Lives in the **lens**, never in the sub-variable. High road utilisation is congestion to a planner and demand to a toll investor. Layer 2 measures; Layer 4 interprets. **An adapter that returns a good/bad judgement is a bug.**

### 6.5 Variable 2 is different

V1, V3, V4 and V5 produce scores. **V2 produces a claim about a named party**, so it emits a **flag with an evidence pack and a contest path**, never a bare number.

Lifecycle: `detected → held → explanation_checked → published | dismissed`, plus `contested → resolved`. Every transition records who, when, why.

> That single rule is the difference between a product a county will buy and one that gets you sued.

### 6.6 What a lens is

A lens is the customer's point of view on the same numbers. It holds exactly three things:

1. **Weights across the five variables** — a county water department weights Activity and Discrepancy; a lender weights Momentum and Resource Security.
2. **Direction of goodness** — 90% utilisation is healthy demand to a utility, drought risk to an insurer. The engine reports 90; the lens decides the colour.
3. **Thresholds** — what counts as amber, red, or flag-worthy for this customer.

A lens **cannot** change sub-variable weights, cannot change the rollup, cannot suppress coverage. Adding a lens never touches an adapter.

### 6.7 Sub-variable weights (engine-owned, fixed, versioned) **[NEW in 1.1]**

Weights live in `services/signals/engine/weights.yml`, versioned. Every `scores.variable_scores` row stores the `weight_version` that produced it. **Changing a weight requires an ADR**, because every historical score was computed under a version. Gates and guards carry no weight.

```yaml
weight_version: 1
V1_activity:            # 1.1 Presence is a GATE (no weight)
  "1.2": 0.40           # Operational state
  "1.3": 0.25           # Utilisation ratio
  "1.4": 0.25           # Continuity
  "1.5": 0.10           # Intensity
V2_discrepancy:         # 2.1 GATE, 2.6 GUARD (no weight) — ranks flag severity
  "2.2": 0.35           # Magnitude gap
  "2.4": 0.35           # Status gap
  "2.3": 0.15           # Attribute gap
  "2.5": 0.15           # Record staleness
V3_momentum:
  "3.5": 0.30           # Neighbourhood momentum — leads 3.1 in almost every case
  "3.1": 0.20           # Direction
  "3.2": 0.15           # Rate
  "3.6": 0.15           # Headroom
  "3.3": 0.10           # Consistency
  "3.4": 0.10           # Acceleration
V4_resource_security:
  "4.1": 0.25           # Availability
  "4.2": 0.25           # Reliability
  "4.3": 0.15           # Trend
  "4.5": 0.15           # Hazard exposure
  "4.4": 0.10           # Competition
  "4.6": 0.10           # Buffer
V5_accessibility:
  "5.3": 0.25           # Access reliability   ─┐ our differentiators:
  "5.5": 0.20           # Service availability ─┘ competitors compute 5.1 once and stop
  "5.1": 0.20           # Proximity
  "5.2": 0.15           # Connection quality
  "5.4": 0.10           # Cost of access
  "5.6": 0.10           # Redundancy
```

Each variable's weights sum to 1.00.

### 6.8 The 28 sub-variables (frozen)

IDs are canonical. Use them in code, tickets and commits (`feat(water): adapter for 1.2 operational state`).

#### V1 — Asset Activity & Utilization · *Is this entity working, and how hard?*

| ID | Sub-variable | Formula | Type | Water (v1) | Road (v1) | Area |
|---|---|---|---|---|---|---|
| 1.1 | Presence | Entity exists where the record says | ⛔ Gate | Structure visible / OSM record | Carriageway visible | Settlement present |
| 1.2 | Operational state | Functioning now: yes/no/intermittent | Contrib | Pump drawing (register, telemetry, NDWI at outflow) | Passable | Active day and night |
| 1.3 | Utilisation ratio | used ÷ total capacity | Contrib | Drawn ÷ rated yield | Traffic ÷ design capacity | Population ÷ planned capacity |
| 1.4 | Continuity | periods operating ÷ periods expected | Contrib | Uptime % | Days passable ÷ 365 | Persistence day/night |
| 1.5 | Intensity | throughput per unit capacity | Contrib | Litres/day/user | Vehicles/day | People/km² |

#### V2 — Declared-vs-Observed Discrepancy · *Does the record match reality?*

| ID | Sub-variable | Formula | Type | Water (v1) | Road (v1) | Area |
|---|---|---|---|---|---|---|
| 2.1 | Existence gap | Record says exists; observation says no | ⛔ Gate | Registered scheme absent | Contract road not built | Reported settlement absent |
| 2.2 | Magnitude gap | (declared − observed) ÷ declared | Contrib | Rated 50 m³/d, drawing 12 | 12 km contracted, 7 built | Reported vs observed population |
| 2.3 | Attribute gap | Declared type/class/use vs observed | Contrib | Declared borehole, observed kiosk | Declared tarmac, observed gravel | Declared vs observed land use |
| 2.4 | Status gap | Declared completion/operation vs observed | Contrib | Reported operational, no draw | Reported 85%, unchanged 8 months | Reported serviced, no evidence |
| 2.5 | Record staleness | record date − latest observation | Contrib | Last inspection vs last telemetry | Report age vs last pass | Census year vs current imagery |
| 2.6 | Explanation state | Is there a legitimate known reason | ⛔ Guard | Seasonal shutdown? | Phased contract? | Boundary change? |

> **2.6 is a guard, not a score.** A discrepancy with an unchecked explanation must never leave the system as a finding.

#### V3 — Growth Momentum · *Which direction, how fast, how reliably?*

| ID | Sub-variable | Formula | Type | Water (v1) | Road (v1) | Area |
|---|---|---|---|---|---|---|
| 3.1 | Direction | Sign of trend over window | Contrib | Yield/demand trend | Traffic volume trend | Built-up/population trend |
| 3.2 | Rate | Magnitude of change per period | Contrib | same series | same series | same series |
| 3.3 | Consistency | Variance around trend line | Contrib | same series | same series | same series |
| 3.4 | Acceleration | Is the rate itself changing | Contrib | same series | same series | same series |
| 3.5 | Neighbourhood momentum | 3.1–3.4 within a radius | Contrib | Nearby scheme demand | Corridor traffic change | Adjacent area trend |
| 3.6 | Headroom | Distance from saturation | Contrib | Aquifer headroom | Capacity before congestion | Land remaining under plan |

Minimum window 3 years, 5 preferred — shorter reports weather as trend. For the demo, V3 runs on the Sentinel-2 archive (2017→) for area entities and is `null_not_measured` for most points. That is correct behaviour.

#### V4 — Resource Security · *The inputs it depends on, and how dependable they are*

| ID | Sub-variable | Formula | Type | Water (v1) | Road (v1) | Area |
|---|---|---|---|---|---|---|
| 4.1 | Availability | How much resource is present | Contrib | Aquifer yield/recharge, rainfall | Drainage capacity | Aggregate supply per capita |
| 4.2 | Reliability | Variance over time | Contrib | Yield variability | Consistency of passability | Interruption frequency |
| 4.3 | Trend | Improving or depleting | Contrib | Water table trend | Deterioration rate | Coverage trend |
| 4.4 | Competition | Others drawing the same resource | Contrib | Boreholes within radius | Competing traffic load | Growth vs capacity |
| 4.5 | Hazard exposure | Frequency/severity of disruption | Contrib | Drought frequency | Flood and washout frequency | Hazard incidents |
| 4.6 | Buffer | Storage, backup, alternative supply | Contrib | Tank capacity | Alternative routes | Alternative sources |

> ⚠️ **Resolution warning.** CHIRPS rainfall runs at ~5 km cells. At entity level that is inherited context, not a local measurement — two water points 3 km apart get the same answer. **Cap confidence on 4.1/4.2 at 0.5** when the only input is a gridded climate product. Never pitch it as field-level water intelligence.

#### V5 — Accessibility · *Can you reach it, dependably, at what cost?*

| ID | Sub-variable | Formula | Type | Water (v1) | Road (v1) | Area |
|---|---|---|---|---|---|---|
| 5.1 | Proximity | Travel time to destination | Contrib | Walking time for users | Time to trunk network | Mean time to services |
| 5.2 | Connection quality | Condition and class of link | Contrib | Path condition | Own surface and class | Network quality |
| 5.3 | Access reliability | Share of year usable | Contrib | Reachable year-round | Days closed per year | Area severed seasonally |
| 5.4 | Cost of access | Transport cost per unit | Contrib | Price per 20 L | Vehicle operating cost | Transport cost burden |
| 5.5 | Service availability | Does transport/service actually operate | Contrib | Staffed and open | Traffic actually uses it | Route/service density |
| 5.6 | Redundancy | Independent viable routes | Contrib | Alternative sources | Parallel corridors | Network redundancy |

> ⚠️ **Tagging bias.** A missing OSM road attribute is `NULL_NOT_MEASURED`, never a default. Left unhandled, the least-mapped places score best on 5.2 and 5.6.

### 6.9 How one variable feeds another decision

| Upstream | Feeds | How |
|---|---|---|
| 1.1 Presence (gate) | All of V2 | If the entity is not there, 2.1 fires and every other gap check is skipped. One observation, two variables. |
| 1.2–1.5 (Activity series) | 3.1–3.4 Momentum | Momentum is the derivative of Activity. Store every observation with `observed_at` so V3 is recomputable from history. |
| 3.5 Neighbourhood | 3.1 Direction | 3.5 leads 3.1 — hence weight 0.30 vs 0.20. |
| 3.6 Headroom | Lens: opportunity | Stops a saturated high-growth area scoring as high-opportunity. |
| 4.4 Competition | 1.3 Utilisation (context) | A water point at 90% with five new boreholes within 500 m is a different asset from one with none. |
| 4.5 Hazard exposure | 5.3 Access reliability | Flood frequency on the route is the direct input to seasonal reachability. |
| 5.5 Service availability | 1.2 Operational (cross-check) | Reported open + zero foot traffic + zero draw ⇒ raises confidence on a 2.4 Status gap flag. |
| 2.5 Record staleness | Confidence across all of V2 | Older records lower confidence on every gap computed against them. |
| V1 + V4 + V5 + area safety | **Freedom Index lens** | Below. |

#### The Freedom Index as a lens

A Layer 4 lens over **area-type entities**. It adds no sub-variables.

```
Wellbeing     = mean(V4 Resource Security, V5 Accessibility) for the area
Safety        = V1 Activity + V4 Hazard exposure of the area entity
                (Safety module adapters)
Participation = share of entities in the area with community-sourced
                observations in the last 90 days (people's own data)

Freedom Index = weighted mean(Wellbeing, Safety, Participation)
                published with coverage and confidence like any lens output
```

The old "÷ population density" form is **dropped**: density is already an Activity measurement on the area, and dividing by it made dense, well-served places score worse by construction. Weights live in the lens JSON.

---

## 7. Data sources and ingestion

### 7.1 Sources for v1 (Nairobi)

| Source | Type | Feeds | Access | Notes |
|---|---|---|---|---|
| OpenStreetMap (Geofabrik Kenya) | Vector | Base map, roads (class/surface), water points, buildings; 1.1, 5.1, 5.2, 5.6 | Open (ODbL) | Attribute gaps → `NULL_NOT_MEASURED` |
| Sentinel-2 L2A (Copernicus) | Raster 10 m, 5-day | NDWI/NDVI/NDBI series; 1.1, 1.2, 3.x, 2.4 | Open, attribution required | **Process via openEO; pull zonal stats, not tiles** |
| Digital Earth Africa | Raster products | WOfS, cropland, built-up; 1.x, 3.x, 4.1 | Open | Already processed continentally |
| GRID3 Kenya | Vector | Settlement extents, population; area entities | Open | |
| KNBS / IEBC boundaries (HDX) | Vector | Ward, sub-county polygons | Open | |
| CHIRPS rainfall | Raster 5 km | 4.1, 4.2, 4.5 | Open | Confidence capped 0.5 |
| WASREB / Nairobi Water / county water dept | PDF, web | Scheme registers, rated yields, tariffs; 1.3, 2.x | Public, scraped | **Scraper target #1** |
| KeNHA / KURA / county roads contracts | PDF, web | Length, value, % complete; 2.2, 2.4, 2.5 | Public, scraped | **Scraper target #2** |
| Kenya Power outage notices | Web | Energy module; 4.2 | Public, scraped | Phase 2 |
| People's own data (reports, photos, institutional) | Upload | Any sub-variable; Participation | Consent required | Manual form in v1; pods later |

### 7.2 Scraper pipeline — four isolated stages

| Stage | Tool | In | Out | Rule |
|---|---|---|---|---|
| Scraper | Playwright (JS-heavy), Scrapy (static) | Source URL from `core.sources` | `raw.documents` (bytes in MinIO, MD5, URL, fetched_at) | **Exit immediately if MD5 unchanged.** Respect robots.txt. Never log in with personal credentials. |
| Parser | pypdf, pdfplumber, BeautifulSoup | `raw.documents` | `raw.text_blocks` (page, block, text, bbox) | No interpretation. Layout → text only. |
| Aligner | **Pydantic schemas + deterministic table extraction** | `raw.text_blocks` + target schema | `records.*` with `alignment_confidence` | Every field traceable to a block. Confidence < 0.6 → `records.review_queue`, not `records.*`. |
| Orchestrator | Prefect | Schedules | Flow runs, retries, alerts | Daily for documents; per-revisit for EO; on-demand for uploads. |

> **[NEW in 1.1] LLM deferred.** Both v1 scraper targets produce tabular PDFs. Deterministic parsing + Pydantic validation handles them, is more reliable, and is fully traceable. Instructor + Ollama (Llama 3 8B) stays in the design for unstructured sources later, and runs on Box B in batch — **never on the demo path**.

### 7.3 Provenance

Every `records.*` row carries `source_document_id`, `extracted_from_block_id`, `aligner_version`, `alignment_confidence`. Every `sub_variable_score` carries `adapter_version`, `weight_version` context and `source_ids`. Every flag carries the full chain.

**If a score cannot be traced back to raw bytes, it is a bug.**

---

## 8. Tech stack and infrastructure

### 8.1 Stack (locked)

| Layer | Choice | Why | Not this |
|---|---|---|---|
| Backend | Laravel 11 / PHP 8.3 | Auth, queues, admin, Reverb; team knows it | Django, Rails, Go |
| Database | PostgreSQL 16 + PostGIS 3.4 | Spatial queries, MVT generation, one DB for everything | MongoDB, MySQL |
| Real-time | Laravel Reverb | First-party WebSockets | Pusher |
| Signal/ingest services | FastAPI, Python 3.12 | Geospatial + ML libraries live in Python | rasterio in PHP |
| Geospatial Python | rasterio, GDAL, shapely, geopandas, xarray, openEO | Standard | |
| Orchestration | Prefect 2.x | Flows, retries, UI, Python-native | Airflow (too heavy), cron alone |
| Scraping | Playwright, Scrapy | JS-heavy and static respectively | Selenium |
| Parsing | pypdf, pdfplumber | Text and tables from PDFs | |
| Map rendering | MapLibre GL JS 4.x | Open fork of Mapbox GL; no token, no billing | Mapbox GL JS |
| Base tiles | Protomaps PMTiles (single file, self-hosted) | No tile service to run | Mapbox, Google |
| Frontend | TypeScript, Vite, Vue 3 or React, Tailwind | Fast, typed | Plain JS, Bootstrap |
| Object storage | MinIO (S3-compatible, self-hosted) | Raw documents, EO outputs | AWS S3 in v1 |
| Deploy | VitoDeploy on Ubuntu 24.04, Docker Compose for Python | Free, self-hosted, native PHP support | Forge, Kubernetes |
| CI | GitHub Actions | Lint, test, build, modularity check | |

### 8.2 Infrastructure — two boxes **[NEW in 1.1]**

Nothing that spikes CPU or RAM shares a box with the database.

| Box | Spec | Runs |
|---|---|---|
| **A — app** | 8 GB RAM, 4 vCPU, NVMe | PostgreSQL + PostGIS, Laravel, Reverb, MinIO, Redis |
| **B — worker** | 16 GB RAM, 8 vCPU, large disk | Prefect, scrapers, parsers, EO processing, (Ollama later) |

Budget ≤ USD 150/month (NFR-11 revised from 100). Private network between boxes; Postgres never exposed publicly.

Two cost levers already taken: no LLM on the demo path (§7.2), and EO processed in the cloud returning stats rather than rasters (§7.1).

**ADR-006.**

### 8.3 Deferred, with reasons

- **CesiumJS / 3D** — no demo value before 2D scoring is trusted. Phase 3.
- **Blockchain (Polygon/Celestia)** — provenance is solved by hashes + append-only `audit.events`. Revisit only if a customer demands third-party verifiability.
- **Gaussian Splatting / cultural heritage layer** — different product, data and buyers. Separate roadmap.
- **Smart Urban Pods** — a future signal source at key landmarks. Mentioned to customers; designed after the first paying user.

---

## 9. Requirements

### 9.1 Functional (frozen for v1)

Priority: **M** = must for 15 Oct · **S** = should by end of October · **C** = could, Phase 2

| ID | Requirement | Pri | Owner |
|---|---|---|---|
| FR-01 | Load Nairobi boundary, wards, OSM roads/water/buildings into PostGIS as entities with stable IDs | M | Devyan |
| FR-02 | Register a scored entity of any of the 4 types with geometry, type, module, external_ref, metadata | M | Khillon |
| FR-03 | Fetch Sentinel-2 coverage on a schedule; compute NDWI/NDVI/NDBI **zonal stats per entity** with observed_at | M | Devyan |
| FR-04 | Scrape, hash, store documents from ≥ 2 government sources; skip unchanged by hash | M | Devyan |
| FR-05 | Parse PDFs → text blocks → typed records with confidence; low-confidence → review queue | M | Devyan |
| FR-06 | Run adapters emitting (value, score, confidence, status, observed_at, source_ids); `null_not_measured` with reason when absent | M | Khillon |
| FR-07 | Water adapters: 1.1, 1.2, 1.3, 2.1, 2.2, 2.4, 2.5, 4.1, 4.4, 5.1, 5.2 | M | Devyan spec / Khillon code |
| FR-08 | Roads adapters: 1.1, 1.2, 2.2, 2.4, 2.5, 5.2 | M | Devyan spec / Khillon code |
| FR-09 | Roll up to 5 variable scores with coverage + confidence; apply gates incl. **provisional** rule; store `weight_version`, `engine_version`, `computed_at` | M | Khillon |
| FR-10 | Generate V2 flags with evidence packs and the §6.5 lifecycle | M | Khillon |
| FR-11 | Admin UI: move a flag `held → explanation_checked → published/dismissed`, recording user, time, note | M | Austine |
| FR-12 | Lenses: JSON with **variable-level** weights, thresholds, direction; county-planner lens applied server-side → lens_score + colour_class | M | Khillon |
| FR-13 | **Map via MVT**: Nairobi base map, entities coloured by lens score, filter by module/type, click → panel | M | Austine |
| FR-14 | Entity panel: 5 variables with score/coverage/confidence; sub-variable accordion with value, confidence, sources; flags with evidence; **provisional badge** | M | Austine |
| FR-15 | Reverb: score/flag change pushes to clients; map updates without reload | M | Khillon + Austine |
| FR-16 | API: `/entities`, `/entities/{id}`, `/entities/{id}/scores`, `/entities/{id}/flags`, `/lenses`; API-key auth | M | Khillon |
| FR-17 | Manual observation form with recorded consent → `core.observations` (source kind `community`), triggers adapter re-run | S | Austine + Khillon |
| FR-18 | Momentum (3.x) for area entities from the Sentinel-2 archive, 5-year window | S | Devyan |
| FR-19 | Outcome log: what happened after a flag was published (confirmed/refuted/partial) → adapter calibration input | S | Khillon |
| FR-20 | Users, roles (admin, analyst, viewer, api_client), audit log of every write to flags and lenses | S | Khillon |
| FR-21 | Land module adapters (parcels, cropland via DE Africa) | C | Devyan |
| FR-22 | Freedom Index lens on ward entities | C | Devyan |
| FR-23 | Export (GeoJSON, CSV) per lens for licensed clients | C | Khillon |

### 9.2 Non-functional

| ID | Requirement | Target |
|---|---|---|
| NFR-01 | Provenance | Every score traceable to raw bytes. Nightly automated walk on a random sample. |
| NFR-02 | Honest output | No score displayed without coverage and confidence. **Enforced in the API serialiser**, not the frontend. |
| NFR-03 | Performance | **Map: first tiles visible < 1.5 s, full viewport < 3 s on 10 Mbps.** Entity panel < 500 ms. Full Nairobi rollup < 10 min. |
| NFR-04 | Availability | Nightly Postgres dump to MinIO; **restore tested before 15 Oct**. |
| NFR-05 | Security | HTTPS only; API keys hashed; secrets in `.env`, never git; 60 req/min per key; OWASP top 10 pass before demo. |
| NFR-06 | Data protection | KDPA 2019 + GDPR. Consent per contribution. PII fields tagged and excluded from API/exports by default. |
| NFR-07 | Modularity | A module directory can be deleted and the build still passes. Tested in CI. |
| NFR-08 | Reproducibility | Any historical score recomputable from stored raw data + `adapter_version` + `weight_version`. |
| NFR-09 | Attribution | Copernicus, OSM, DE Africa, GRID3 credited in UI footer and API responses. |
| NFR-10 | Observability | Structured logs; Prefect status visible; failed scrape alerts to team channel < 15 min. |
| NFR-11 | Cost | ≤ **USD 150**/month infrastructure. |

---

## 10. Data model

One PostgreSQL database. **Seven schemas: `core`, `raw`, `records`, `scores`, `flags`, `lens`, `audit`.** IDs are UUIDv7. Timestamps `timestamptz` UTC. Geometry SRID 4326 for storage, 32737 (UTM 37S) for measurement.

### core
| Table | Key columns |
|---|---|
| `core.entities` | id, entity_type (point\|parcel\|segment\|area), module, name, external_ref, geom, radius_m, parent_area_id, metadata jsonb, created_at, retired_at |
| `core.sources` | id, name, kind (scrape\|raster\|vector\|upload\|community), base_url, licence, attribution, schedule, active |
| `core.observations` | id, entity_id, source_id, kind, payload jsonb, observed_at, ingested_at, contributor_id, consent_id |
| `core.consents` | id, contributor_id, scope, granted_at, withdrawn_at, text_version |
| `core.adapters` | id, module, sub_id, entity_types[], version, enabled, signal_description, code_ref |

### raw
| Table | Key columns |
|---|---|
| `raw.documents` | id, source_id, url, md5, storage_path, mime, fetched_at, http_status |
| `raw.text_blocks` | id, document_id, page, block_index, text, bbox |
| `raw.eo_stats` | id, source_id, entity_id, product, index_name, value, acquired_at, cloud_pct |
| `raw.vectors` | id, source_id, layer, feature_count, loaded_at, storage_path |

### records
| Table | Key columns |
|---|---|
| `records.water_schemes` | id, entity_id, name, scheme_type, rated_yield_m3d, status_declared, operator, record_date, document_id, block_id, aligner_version, alignment_confidence |
| `records.road_contracts` | id, entity_id, contractor, length_km_declared, value_kes, start_date, end_date, pct_complete_declared, report_date, document_id, block_id, aligner_version, alignment_confidence |
| `records.review_queue` | id, target_table, candidate jsonb, alignment_confidence, reason, reviewed_by, decision |

### scores
| Table | Key columns |
|---|---|
| `scores.sub_variable_scores` | id, entity_id, sub_id, value, unit, score, confidence, **status** (measured\|null_not_measured), null_reason, observed_at, computed_at, adapter_id, adapter_version, source_ids uuid[] |
| `scores.variable_scores` | id, entity_id, variable_id (1–5), score, coverage, confidence, **status** (scored\|cannot_assess\|**provisional**), **gate_status** (passed\|failed\|unmeasured), gate_failed_sub_id, **weight_version**, engine_version, computed_at, inputs jsonb |

### flags
| Table | Key columns |
|---|---|
| `flags.flags` | id, entity_id, sub_id (2.1–2.5), severity, state, declared jsonb, observed jsonb, gap, detected_at, state_changed_at |
| `flags.evidence_packs` | id, flag_id, record_ids, observation_ids, document_ids, eo_stat_ids, narrative, generated_at, adapter_version |
| `flags.transitions` | id, flag_id, from_state, to_state, user_id, note, at |
| `flags.outcomes` | id, flag_id, outcome (confirmed\|refuted\|partial\|unknown), evidence, recorded_by, at |

### lens & audit
| Table | Key columns |
|---|---|
| `lens.lenses` | id, name, customer_type, config jsonb **{variable_weights[1..5], thresholds, direction}** — *no `sub_weights`* | version, active |
| `lens.lens_scores` | entity_id, lens_id, score, colour_class, computed_at |
| `audit.events` | id, user_id, action, target_table, target_id, before jsonb, after jsonb, at |

### 10.1 Write ownership **[NEW in 1.1 — ADR-004]**

**One writer per table. No exceptions.**

| Schema | Writer | Reader |
|---|---|---|
| `raw.*` | **Python** | Laravel, Python |
| `scores.sub_variable_scores` | **Python** | Laravel |
| `core.*`, `records.*`, `scores.variable_scores`, `flags.*`, `lens.*`, `audit.*` | **Laravel** | Both |

- **Laravel owns all migrations**, including the schemas Python writes to. Python gets no migration tool. One schema history, one place to look.
- Rollup trigger: Python publishes a lightweight **Redis** event after writing a batch; Laravel consumes it and rolls up. **No Python→Laravel HTTP in the hot path.**

---

## 11. API and tile contract

Base path `/api/v1`. JSON only. API key in `X-Api-Key`. Every score object includes `coverage` and `confidence` — the serialiser will not emit one without the others (NFR-02).

### 11.1 JSON endpoints

| Method + path | Returns |
|---|---|
| `GET /entities?module=&type=&bbox=&lens=` | GeoJSON FeatureCollection, **bbox required, capped at 500 features**. For institutional clients, not the map. |
| `GET /entities/{id}` | Entity + geometry + metadata + latest 5 variable scores (score, coverage, confidence, status, gate_status) |
| `GET /entities/{id}/scores?variable=` | Sub-variable rows: value, unit, score, confidence, status, null_reason, observed_at, sources |
| `GET /entities/{id}/flags` | Flags in `published`/`resolved` with evidence-pack summaries |
| `GET /flags/{id}` | Full evidence pack (admin/analyst only for `held`) |
| `POST /flags/{id}/transition` | Body: to_state, note. Writes `flags.transitions` + `audit.events` |
| `GET /lenses` | Active lenses and configs |
| `POST /observations` | Community/manual observation with consent_id; triggers adapter re-run |
| WS `entities.{id}` (Reverb) | Events: `score.updated`, `flag.changed` |

### 11.2 Map tiles **[NEW in 1.1 — ADR-005]**

```
GET /tiles/{z}/{x}/{y}.mvt?lens={id}&module={name}
```

- Generated in PostGIS with `ST_AsMVT`. Binary, per-viewport, fetched automatically by MapLibre as the user pans.
- **Tile properties limited to:** `id`, `entity_type`, `module`, `lens_score`, `colour_class`, `coverage`. Nothing else. Tiles are requested hundreds of times while panning; every extra field is paid for every time.
- Click → frontend takes `id` from the tile → `GET /entities/{id}` for the full picture. One request, at the moment it is needed.
- **Cache key:** `lens_id + weight_version + last_computed_at`. Invalidated when a rollup finishes.

Two consumers, two formats: **the map uses tiles, machines use JSON.**

> Austine builds the map layer against a MapLibre `vector` source from Week 2. Not a `geojson` source. Switching later means rewriting the layer, the styling and the click handling.

---

## 12. Roles, ownership and decisions

| Person | Title | Owns (code) | Final say on |
|---|---|---|---|
| **Devyan Jethwa A.** | Founder, CTIPSO | `services/ingest`, `services/signals/modules/*`, `data/`, lens JSON, `weights.yml` | Product, roadmap, scope, variable framework, sources, what is in the demo. **Tie-break between Khillon and Austine.** |
| **Khillon** | CTO (Backend) | `apps/api`, `services/signals/engine`, `infra/`, all migrations | Anything in the database, the engine, the server |
| **Austine** | CTO (Frontend) | `apps/web` | Anything the user sees |

Within one person's area, that person decides. Across areas, Devyan decides and it becomes an ADR the same day. **Nobody re-opens a written decision without a new ADR.**

### 12.1 Rhythm

- **Monday 09:00 (30 min)** — plan the week against §13. Each person names their three deliverables.
- **Daily, async by 10:00** — yesterday / today / blocked.
- **Thursday 16:00 (45 min)** — demo what works **on staging**. Not slides. Not localhost.
- **Friday 15:00** — update §13 and §19, merge to main, deploy.

---

## 13. Sprint plan and task breakdown

Four sprints, Friday→Thursday. **Deliverable = merged to main and visible on staging.**
Today is Saturday 20 September: Week 1, day 3.

### Week 1 · 18–24 Sep — Foundations

**Khillon**

| # | Task | Done when |
|---|---|---|
| K1.1 | Monorepo created (§14.1), branch protection on `main`, PR template, commitlint | First PR merges with CI green |
| K1.2 | CI: Pint, PHPStan L6, ruff, mypy, eslint, tsc, tests, **modularity check** | `ci.yml` green on a trivial PR |
| K1.3 | **Box A + Box B** provisioned via VitoDeploy; private networking; Postgres not publicly exposed | SSH + `psql` from Box B to Box A works |
| K1.4 | **Migration 0001: create 7 schemas, set `search_path`** (§14.12) | `\dn` shows 7 schemas; a model resolves `core.entities` |
| K1.5 | Migrations for `core`, `raw`, `records`, `scores`, `flags`, `lens`, `audit` per §10 incl. `status`, `gate_status`, `weight_version` | All migrations up **and down** cleanly |
| K1.6 | Laravel + Reverb + Redis deployed to staging; FastAPI `/health` on Box B | `staging.navuuna.*` serves; `/health` 200 |
| K1.7 | **ADR-001** monorepo, **ADR-004** write ownership, **ADR-005** tiles, **ADR-006** two boxes | Merged in `docs/adr/` |

**Devyan**

| # | Task | Done when |
|---|---|---|
| D1.1 | `weights.yml` v1 committed (§6.7) | File in repo, referenced by engine config |
| D1.2 | Nairobi boundary + wards (IEBC/HDX) loaded as `area` entities | `SELECT count(*) FROM core.entities WHERE entity_type='area'` > 80 |
| D1.3 | OSM roads / water / buildings loaded as `segment`, `point`, `parcel` entities via `ogr2ogr` scripts in `data/` | Total entities > 5,000 |
| D1.4 | `core.sources` populated for all §7.1 sources | Rows exist with licence + attribution |
| D1.5 | Scraper #1 (water scheme register) fetching + hashing into `raw.documents` | ≥ 1 document stored; re-run exits on unchanged MD5 |
| D1.6 | openEO request returning NDWI zonal stats for a sample of Nairobi water entities | ≥ 1 row in `raw.eo_stats` |
| D1.7 | `CONTEXT.md` glossary: entity, sub-variable, lens, gate, guard, provisional, coverage, confidence | Merged |

**Austine**

| # | Task | Done when |
|---|---|---|
| A1.1 | **ADR-002**: Vue 3 or React. Decide by **22 Sep**, then fixed | ADR merged |
| A1.2 | Vite + TS app scaffolded, eslint/prettier/vitest wired to CI | Builds in CI |
| A1.3 | MapLibre map with self-hosted **PMTiles** basemap of Nairobi | Basemap renders on staging |
| A1.4 | **Vector (`vector`) source** wired to `/tiles/{z}/{x}/{y}.mvt` — stub tiles are fine this week | Entities render as dots from MVT, not GeoJSON |
| A1.5 | Empty entity panel opens on click, reading `id` from the tile feature | Click logs the correct entity id |

---

### Week 2 · 25 Sep–1 Oct — Water module and engine

**Khillon**

| # | Task | Done when |
|---|---|---|
| K2.1 | Adapter registry + runner in FastAPI; writes `scores.sub_variable_scores` | Sub-variable rows exist for real entities |
| K2.2 | Redis event on batch write; Laravel consumer triggers rollup | Rollup runs without manual command |
| K2.3 | Rollup engine: weights from `weights.yml`, coverage, confidence, **3-state gate logic (§6.3)** | Unit tests cover passed / failed / unmeasured |
| K2.4 | V2 flag generation + evidence packs + lifecycle states | ≥ 3 flags in `held` with packs |
| K2.5 | JSON endpoints FR-16 live with the NFR-02 serialiser guard | `GET /entities/{id}` returns 5 variables with coverage |
| K2.6 | **`ST_AsMVT` tile endpoint** live with real geometry and lens_score | Tiles render the actual Nairobi water layer |

**Devyan**

| # | Task | Done when |
|---|---|---|
| D2.1 | Parser + Pydantic aligner on the water register; `records.water_schemes` populated | ≥ 200 rows; low-confidence rows in review queue |
| D2.2 | Match records → entities (name + proximity) | ≥ 80% matched; unmatched logged, not guessed |
| D2.3 | Adapter specs for FR-07 (11 adapters), one page each: signal, formula, confidence rule, null conditions | Specs merged in `docs/modules/water.md` |
| D2.4 | Implement FR-07 adapters with Khillon | ≥ 200 water entities with ≥ 3 measured sub-variables |
| D2.5 | NDWI/NDVI zonal stats scheduled in Prefect | Flow runs daily on Box B |

**Austine**

| # | Task | Done when |
|---|---|---|
| A2.1 | Entity panel: 5 variables with score, **coverage bar, confidence bar**, status badge | Real staging data renders |
| A2.2 | Sub-variable accordion: value, unit, confidence, sources, `null_reason` where unmeasured | Expands for any variable |
| A2.3 | **`provisional` badge** + tooltip explaining the unmeasured gate | Visible on a provisional entity |
| A2.4 | Flag list + evidence pack view | Held vs published visually distinct |
| A2.5 | Lens selector reading `GET /lenses`; re-requests tiles with `?lens=` | Map recolours on selection |

---

### Week 3 · 2–8 Oct — Roads, lens, real-time

**Khillon**

| # | Task | Done when |
|---|---|---|
| K3.1 | Lens engine (FR-12) + county-planner lens; `lens.lens_scores` populated | Tiles carry lens_score for that lens |
| K3.2 | Tile cache keyed `lens_id + weight_version + last_computed_at`, invalidated on rollup | Second load of a tile is served from cache |
| K3.3 | Reverb events on score/flag change | Change in one browser appears in another |
| K3.4 | Flag transition endpoint + `audit.events` | Transitions recorded with user and note |
| K3.5 | Nightly backup **and one tested restore** (NFR-04) | Restore log in `infra/` |

**Devyan**

| # | Task | Done when |
|---|---|---|
| D3.1 | Scraper #2 (roads contracts) + aligner; `records.road_contracts` | ≥ 20 contracts parsed |
| D3.2 | Adapter specs + implementation for FR-08 | Road segments scored |
| D3.3 | NDBI change along contract segments → 2.4 Status gap | ≥ 1 road flag with evidence on the map |
| D3.4 | Seed `flags.outcomes` with 2 manually checked flags | Outcome rows exist |

**Austine**

| # | Task | Done when |
|---|---|---|
| A3.1 | Map recolours by lens; module + entity-type filters as tile query params | Filters work without page reload |
| A3.2 | Admin flag review screen (FR-11) with the held→published workflow | Full transition works end to end |
| A3.3 | Manual observation form (FR-17) with consent checkbox → `POST /observations` | Submission creates an observation |
| A3.4 | Reverb subscription updates the panel and re-fetches affected tiles | Score change visible live |
| A3.5 | Mobile-width layout | Usable at 390 px |

---

### Week 4 · 9–15 Oct — Hardening and demo

| Who | Tasks | Done when |
|---|---|---|
| **Khillon** | NFR-03 performance pass on tiles and panel; NFR-05 security pass; API key issuing; error states; **data frozen Tue 13 Oct**; production deploy **Wed 14 Oct** | Demo runs on the production URL, not staging |
| **Devyan** | Demo script with exact entity IDs; attribution text; open-items list; rehearsals Tue + Wed; offline laptop fallback with a local copy | Two clean rehearsals, no blockers |
| **Austine** | Visual polish, loading/empty states, coverage + confidence explained in-UI, attribution footer; **recorded video fallback** | Video exists; no console errors on the demo path |
| **All** | **Thursday 15 October — demo** | |

> **Cut order if behind:** FR-17 manual form → FR-18 Momentum → FR-15 live update (fall back to page refresh) → roads module reduced to one segment.
> **Never cut:** coverage/confidence display, evidence packs, the `held` state, the `provisional` badge.

---

## 14. Engineering rules

Not suggestions. A PR that breaks one is not merged, whoever wrote it.

### 14.1 Repository layout — one monorepo: `github.com/navuuna/navuuna`

```
navuuna/
  apps/
    api/                  Laravel 11. Engine, rollup, lenses, flags, tiles, auth, API.
    web/                  Vite + TS. Map (MVT), panels, admin.
  services/
    ingest/               Python. scrapers/ parsers/ aligners/ eo/ vectors/
    signals/
      engine/             runner, registry client, rollup helpers, weights.yml
      modules/
        water/            adapters/1_1_presence.py … lens.json README.md
        roads/
        land/             (empty until Phase 2)
    flows/                Prefect flow definitions
  data/                   ogr2ogr + SQL load scripts. No data files committed.
  infra/                  VitoDeploy notes, docker-compose.yml, backup + restore scripts
  docs/
    BUILD_BIBLE.md        this file
    CONTEXT.md            domain glossary
    adr/                  ADR-001-monorepo.md …
    modules/<name>.md     per module: sources, adapters, known gaps
  .github/workflows/      ci.yml, deploy.yml
  README.md               run everything locally in under 15 minutes
```

### 14.2 Branching

- `main` is always deployable. Protected: no direct pushes, one approving review, CI green.
- Short-lived feature branches off `main`, merged within **3 days**. Name: `type/scope-short-description` — `feat/water-1-2-operational`, `fix/api-coverage-null`, `chore/ci-phpstan`.
- No `develop`, no release branches in v1. Tags mark releases: `v0.1.0` = the demo build.
- Rebase on `main` before opening the PR. **Squash-merge.** The squash message follows §14.3.

### 14.3 Commits — Conventional Commits, enforced by commitlint

```
<type>(<scope>): <imperative summary, ≤72 chars>

<body: what and why, not how. Reference the sub-variable ID and the FR.>

Refs: FR-07, ADR-004

types:  feat | fix | refactor | perf | test | docs | chore | ci | data
scopes: api | web | ingest | signals | engine | tiles | water | roads | land |
        energy | safety | infra | docs

examples:
  feat(water): adapter for 1.2 operational state from NDWI and register status
  fix(engine): exclude null_not_measured from rollup denominator (FR-09)
  feat(tiles): ST_AsMVT endpoint with lens_score property (ADR-005)
  data(nairobi): load IEBC ward boundaries into core.entities
  docs(adr): ADR-006 two-box infrastructure
```

- One logical change per commit. Never mix a refactor with a feature.
- **Never commit:** `.env`, credentials, API keys, data files > 1 MB, raw documents, rasters.
- Write for the person reading `git log` in six months, who may be none of us.

### 14.4 Pull requests

`.github/pull_request_template.md`, every section filled:

```markdown
## What
One paragraph. Which FR / sub-variable / module.

## Why
The requirement or decision this serves. Link the ADR if one exists.

## Where
Files and directories touched. Which layer (L1/L2/L3/L4, or app).

## How to test
Exact commands or clicks. Include a staging entity ID if relevant.

## Checklist
- [ ] Tests added or updated
- [ ] No sub-variable added or removed (Layer 2 frozen)
- [ ] No sub-variable weights in a lens config (Layer 4 weights variables only)
- [ ] Scores emit value, score, confidence, status, observed_at, source_ids
- [ ] Missing data is null_not_measured with a reason — never 0, never a default
- [ ] Only the owning service writes to the touched tables (ADR-004)
- [ ] Migration is reversible (down() is implemented)
- [ ] docs/modules or ADR updated if behaviour changed
- [ ] No secrets, no data files
```

- **Size ≤ 400 changed lines.** Bigger, split it.
- Reviewer = the owner of the area (§12). Author cannot approve their own PR. Review within 24 h; if you can't, say so in the channel.
- Review looks for: does it do what the FR says · does it break a §6 rule · is it in the right layer · is it tested · will someone else understand it.
- Draft PRs early are encouraged. A draft is not reviewed until marked ready.

### 14.5 Coding standards

| Language | Standard | Tooling (CI + pre-commit) |
|---|---|---|
| PHP | PSR-12, Laravel conventions, `declare(strict_types=1)`, typed properties, no raw SQL outside repositories, Eloquent for CRUD and query builder for spatial | Pint, PHPStan **level 6**, Pest |
| Python | PEP 8, type hints everywhere, Pydantic at every boundary, **pure adapters** (in: observations/records → out: ScoreResult), no DB calls inside adapter logic | ruff, `mypy --strict` on `engine/` and `modules/`, pytest |
| TypeScript | strict mode, no `any`, components < 200 lines, API types **generated** from the OpenAPI spec — never hand-written | eslint, prettier, vitest, `tsc --noEmit` |
| SQL | Migrations only via Laravel, every migration has `down()`, GIST index on every geometry column, **schema-qualified table names**, no business logic in triggers | CI migration lint: fails on empty `down()` or unqualified `Schema::create` |

### 14.6 Principles

- **Adapters are pure.** An adapter takes observations and records for **one entity** and returns a `ScoreResult`. It does not fetch, does not write, does not know about other entities. The runner does the plumbing.
- **The engine is dumb on purpose.** It knows the 28 IDs, the weights, the rollup formula, the gates and the guard. It knows nothing about water or roads.
- **Boring over clever.** If a junior can't follow it in one read, rewrite it.
- **Names come from `CONTEXT.md`.** Not in the glossary? Add it there first. One concept, one name — no "asset", no "site", no "feature" for a scored entity.
- Every function producing a number a customer will see has a test with a known input and a known output.
- Feature flags per module (`config/modules.php`): switch a module off in production without a deploy.
- **No TODOs in `main`.** A TODO becomes an issue or it gets done.

### 14.7 Testing

| Level | What | Minimum |
|---|---|---|
| Unit | Adapters (known observation → known score), rollup math, **3-state gate logic**, guard, lens application, Pydantic schemas | Every adapter; 100% of `engine/` |
| Integration | Scraper → parser → aligner on a fixture PDF; API endpoints against a seeded PostGIS test DB; tile endpoint returns valid MVT | Every endpoint in §11 |
| Provenance | Walk a random score back to raw bytes | Nightly on staging |
| Modularity | Delete `services/signals/modules/roads/` in CI and build | Every PR touching `modules/` |
| End-to-end | Playwright: map → entity → flag → evidence → transition | The demo path, before every production deploy |

### 14.8 Definition of Done

Merged to `main` · CI green · deployed to staging · shown in the Thursday demo · tests per §14.7 · `docs/modules` or ADR updated · the §13 row ticked by its owner. **Not before.**

### 14.9 ADRs

Any decision costing more than a day to reverse gets `docs/adr/NNN-title.md`: number, date, context, decision, consequences, status.

| ADR | Subject | Status |
|---|---|---|
| 001 | Monorepo | Accepted |
| 002 | Frontend framework (Vue 3 vs React) | **Due 22 Sep — Austine** |
| 003 | Adapter interface | Accepted |
| 004 | Write ownership: Python writes sub-variable scores, Laravel writes everything else and owns all migrations | Accepted |
| 005 | Vector tiles for the map, GeoJSON for the API | Accepted |
| 006 | Two-box infrastructure; LLM off the demo path; EO processed in cloud | Accepted |
| 007 | Sub-variable weights engine-owned; lenses weight variables only | Accepted |

Introducing a paid service, a new language, a weight change, or any change to the 28 requires an ADR approved by all three.

### 14.10 Issues and labels

- GitHub Issues, **one per task row in §13**, labelled `module:*`, `layer:L1–L4|app`, `pri:M|S|C`, `owner:*`.
- Issue title starts with the task ID and the FR it serves — e.g. `K2.3 — FR-09 rollup with 3-state gate logic`.
- A PR closes its issue with `Closes #n`.

### 14.11 Environments

| Env | Where | Deploys | Data |
|---|---|---|---|
| local | Docker Compose per laptop | n/a | Seeded 500-entity Nairobi subset, committed as SQL fixture |
| staging | Box A, `staging.*` | Every merge to `main`, automatic | Full Nairobi |
| production | Box A, separate DB + app dir | Manual, tagged release, Khillon or Devyan | Full Nairobi; demo data frozen 13 Oct |

### 14.12 Multi-schema setup **[NEW in 1.1]**

```php
// config/database.php — pgsql connection
'search_path' => 'core,records,scores,flags,lens,audit,public',
```

- Migration `0001_create_schemas` issues `CREATE SCHEMA IF NOT EXISTS` for all seven.
- **Every model names its schema explicitly:** `protected $table = 'core.entities';`. Never rely on `search_path` resolution.
- PostGIS stays in `public`.
- CI check fails any migration with an unqualified `Schema::create`.

Cheap now. Miserable to retrofit after forty migrations.

---

## 15. Data protection and user data rights

- **Legal basis:** Kenya Data Protection Act 2019; GDPR for non-Kenyan users. Devyan is the named contact until a DPO is appointed.
- **Minimisation by design.** Scored entities are places and assets, not people. Where a record names a natural person, the field is tagged `pii=true` and excluded from API responses and exports by default.
- **Consent.** Community contributions require recorded consent (`core.consents`) with the consent text version. Withdrawal removes the contribution from scoring within 24 hours and is logged.
- **Media.** Contributor photos/audio are stripped of EXIF location and device data; the coordinates the contributor chose are kept.
- **Flags.** Never public while `held`. Publication requires a human transition with a note. A contest path (email in v1) is shown with every published flag.
- **Retention.** Raw documents indefinitely (public records); community contributions 5 years or until withdrawn; access logs 12 months.
- **Scraping.** Publicly published documents only. robots.txt honoured. No login walls. No personal social media.
- **Future pods.** Edge anonymisation is a design requirement, not an option. Only aggregate telemetry leaves the device.

---

## 16. Later phases

### 16.1 The agentic layer

**Not before the engine has three months of validated outputs.** Agents sit above Layer 4 and operate the system; they replace nothing below it.

| Agent | Does | May NOT |
|---|---|---|
| Source scout | Watches portals for new document types; proposes a `core.sources` row and a draft schema | Activate a source |
| Adapter proposer | Drafts adapter code for **existing** sub-variable IDs, with tests, for a new sector | Add a sub-variable; merge without review |
| Explainer | Answers "why is this water point 41?" from score rows, coverage and evidence (RAG over our own data only) | Invent a source; every sentence cites a row |
| Discrepancy analyst | For a `held` flag, gathers candidate explanations and drafts the 2.6 note | Transition the flag |
| Outcome checker | Watches for resolution signals after publication; drafts the outcome record | Record an outcome unreviewed |
| Lens tuner | Suggests weight changes per customer from outcome data | Change a lens in production |

**Guardrails:** local models where data is sensitive; every agent action is a PR or a draft, never a production write; agent output labelled as such in the UI; `audit.events` records model and prompt version.

The agentic layer is the reason the provenance rules in §7.3 exist. **Agents can only be trusted over data that can be traced.**

### 16.2 Other deferred work

- **Smart Urban Pods** — solar edge kiosks at key landmarks feeding foot traffic, air quality and community reports as one more source kind. Design starts after the first paying customer.
- **Energy** (Phase 2) and **Safety & Security** (Phase 3) modules. Safety needs a mature community observation channel.
- **Other counties** — ordered by open base-layer availability. Mombasa and Kisumu are candidates.
- **3D (CesiumJS)** and the **cultural heritage layer** (Gaussian Splatting) — separate product line.
- **Navuuna World Investor Games** — a lightweight web app over the same API for financial and spatial literacy. Its own small project once the API is stable.
- **Global scrapers** — the pipeline is source-agnostic; pointing it outside Kenya is a `core.sources` change, not a code change.

---

## 17. Backlog — parked, not scheduled

- Underground infrastructure layer (pipes, cables, drains) as `segment` entities
- Space layer (tasking, commercial imagery) when open-data resolution is the binding limit
- Cross-border entities and the diaspora audience
- Heritage Health Scores as a lens on cultural sites
- Paid tiers and licensing tiers for the API
- **Anything a customer asks for that would need a 29th sub-variable.** It lands here first and is priced as custom work.

---

## 18. Assumptions register

| # | Assumption | Confirm | By |
|---|---|---|---|
| A1 | Devyan has final say across areas; Khillon backend, Austine frontend | All three | 21 Sep |
| A2 | ~~One VPS is enough~~ **Resolved: two boxes, ≤ USD 150/mo (ADR-006)** | Khillon | ✅ 20 Sep |
| A3 | WASREB / Nairobi Water publish a scrapable scheme register with rated yields. If not, fall back to county water department documents | Devyan | 22 Sep |
| A4 | Frontend framework chosen by Austine in Week 1 and not revisited | Austine | 22 Sep |
| A5 | Freedom Index §6.9 definition replaces the population-density formula | Devyan | 25 Sep |
| A6 | Agriculture is a Land module adapter set, not its own module | Devyan | 25 Sep |
| A7 | Community observation form (FR-17) is the v1 stand-in for pods | Devyan | 25 Sep |
| A8 | No customer-specific feature before 15 October | Devyan | standing |
| A9 | Deterministic parsing handles both v1 scraper targets without an LLM. If a target proves unstructured, it moves to Box B batch, never the demo path | Devyan | 27 Sep |

---

## 19. Change log

| Date | Section | Change | By |
|---|---|---|---|
| 18 Sep 2026 | All | v1.0. Name locked to Navuuna. Scope, stack, requirements, sprint plan, rules. | Devyan |
| 20 Sep 2026 | §1, §6.7 | **Sub-variable weights published and engine-owned.** `sub_weights` removed from lens config; lenses weight variables only. ADR-007. | Devyan |
| 20 Sep 2026 | §6.3, §10 | **Three gate states.** `provisional` added for unmeasured gates: contributors scored, confidence ×0.5, no V2 flags. `gate_status` column added. | Devyan |
| 20 Sep 2026 | §10.1 | **ADR-004 write ownership.** Python writes `raw.*` and `scores.sub_variable_scores`; Laravel writes everything else and owns all migrations. Redis event triggers rollup. | Khillon |
| 20 Sep 2026 | §11.2, NFR-03 | **ADR-005 vector tiles.** MVT for the map, GeoJSON capped at 500 features for the API. Perf target restated as first-tiles/full-viewport. | Khillon |
| 20 Sep 2026 | §8.2, NFR-11 | **ADR-006 two boxes.** LLM off the demo path; EO processed in cloud. Budget 100 → 150 USD/mo. | Khillon |
| 20 Sep 2026 | §14.12 | **Multi-schema `search_path` in migration 0001**; schema-qualified table names mandatory; CI check. | Khillon |

---

> **Last line.** Build the water module. Show it on 15 October. Log what happened.
> Everything else in this document exists so we do not have to argue about it while doing that.
