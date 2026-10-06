// Deterministic synthetic fixtures.
//
// Produces ~200 water points inside the Nairobi County bounding box, 1 road segment, and a
// matching EntityDetail for each. Same seed → same output on every run. Covers every status
// flavour the frontend must render: fully measured, provisional with halved confidence,
// cannot_assess, sub-variables null_not_measured with a reason, 3 published findings and 1
// held finding (DEC-08, A-19).

import { makeRng, pick, range, int } from './rand';
import {
  ATTRIBUTION_LINE,
  type EntityDetail,
  type FindingSummary,
  type FixtureEntity,
  type FixtureGeometry,
  type Severity,
  type Source,
  type SubVariableScore,
  type SubVariableStatus,
  type Variable,
  type VariableScore,
} from './types';

const SEED = '20261006';

// Nairobi County bounding box plus a small margin (matches basemap extraction).
const NAIROBI_BBOX = { minLng: 36.65, maxLng: 37.05, minLat: -1.45, maxLat: -1.15 };

const WATER_POINT_COUNT = 200;
const PROVISIONAL_COUNT = 30; // partly_verified entities
const CANNOT_ASSESS_COUNT = 20; // entities with the V1 1.1 gate failing
const PROVISIONAL_CONFIDENCE_FACTOR = 0.5; // Bible §6.1 A6
const BASE_COMPUTED_AT = '2026-10-05T16:00:00Z';

const WARD_NAMES = [
  'Kibra',
  'Dagoretti North',
  'Dagoretti South',
  'Westlands',
  'Kasarani',
  'Embakasi East',
  'Mathare',
  'Starehe',
  'Langata',
  'Ruaraka',
] as const;

const VARIABLE_NAMES: Record<Variable, string> = {
  V1: 'Is it working?',
  V2: 'Does the record match?',
  V3: 'Which way is it going?',
  V4: 'Will its inputs hold?',
  V5: 'Can people reach it?',
};

// Contributor counts from Bible §6.7 / weights.yml.
const VARIABLE_CONTRIBUTORS: Record<Variable, number> = {
  V1: 4,
  V2: 4,
  V3: 6,
  V4: 6,
  V5: 6,
};

// Sub-variables we actually model in the fixture — a subset covering every variable, so
// the panel's accordion has real rows under each one.
interface SubVarSpec {
  id: string;
  variable: Variable;
  label: string;
  unit: string | null;
  valueMin: number;
  valueMax: number;
}
const SUBVAR_SPECS: readonly SubVarSpec[] = [
  {
    id: '1.1',
    variable: 'V1',
    label: 'Does water come out?',
    unit: null,
    valueMin: 0,
    valueMax: 1,
  },
  {
    id: '1.2',
    variable: 'V1',
    label: 'How often is it on?',
    unit: 'hours/day',
    valueMin: 0,
    valueMax: 24,
  },
  {
    id: '2.1',
    variable: 'V2',
    label: 'Is the record present?',
    unit: null,
    valueMin: 0,
    valueMax: 1,
  },
  {
    id: '2.2',
    variable: 'V2',
    label: 'Does the recorded capacity match?',
    unit: 'm³/day',
    valueMin: 0,
    valueMax: 500,
  },
  {
    id: '2.4',
    variable: 'V2',
    label: 'Does the status match?',
    unit: null,
    valueMin: 0,
    valueMax: 1,
  },
  {
    id: '2.5',
    variable: 'V2',
    label: 'How old is the record?',
    unit: 'months',
    valueMin: 0,
    valueMax: 60,
  },
  {
    id: '3.1',
    variable: 'V3',
    label: 'How does uptime trend over 12 months?',
    unit: '%',
    valueMin: 0,
    valueMax: 100,
  },
  {
    id: '4.1',
    variable: 'V4',
    label: 'Does rainfall support the catchment?',
    unit: 'mm/year',
    valueMin: 100,
    valueMax: 1200,
  },
  {
    id: '4.4',
    variable: 'V4',
    label: 'Is the grid stable here?',
    unit: '%',
    valueMin: 0,
    valueMax: 100,
  },
  {
    id: '5.1',
    variable: 'V5',
    label: 'How many minutes to walk here?',
    unit: 'minutes',
    valueMin: 2,
    valueMax: 60,
  },
  {
    id: '5.2',
    variable: 'V5',
    label: 'How wide is the catchment?',
    unit: 'people',
    valueMin: 50,
    valueMax: 5000,
  },
];

const SOURCE_POOLS: Record<SubVarSpec['id'], readonly Source[]> = {
  '1.1': [{ name: 'Community observation (synthetic)', kind: 'community', date: '2026-09-15' }],
  '1.2': [{ name: 'Operator log (synthetic)', kind: 'register', date: '2026-09-14' }],
  '2.1': [{ name: 'WASREB scheme register', kind: 'register', date: '2026-03-12' }],
  '2.2': [{ name: 'WASREB scheme register', kind: 'register', date: '2026-03-12' }],
  '2.4': [{ name: 'WASREB scheme register', kind: 'register', date: '2026-03-12' }],
  '2.5': [{ name: 'WASREB scheme register', kind: 'register', date: '2026-03-12' }],
  '3.1': [{ name: 'Operator log (synthetic)', kind: 'register', date: '2026-09-01' }],
  '4.1': [{ name: 'CHIRPS 2.0', kind: 'satellite', date: '2026-08-31' }],
  '4.4': [{ name: 'KPLC public data (illustrative)', kind: 'open_data', date: '2026-06-30' }],
  '5.1': [{ name: 'OpenStreetMap routing', kind: 'open_data', date: '2026-09-10' }],
  '5.2': [{ name: 'GRID3 population raster', kind: 'satellite', date: '2026-07-01' }],
};

const NULL_REASON_POOL = [
  'no observation in the last 90 days',
  'satellite tile unavailable for this cell',
  'register row has no reading for this field',
  'ward-level only — not measured at this location',
  'walking-time network ends outside the service area',
] as const;

// --- generator ---

interface GenResult {
  entities: FixtureEntity[];
  byId: Map<string, FixtureEntity>;
}

export function generateFixtures(): GenResult {
  const rng = makeRng(SEED);

  // Decide which indices get which gate_status.
  // We pre-pick cannot_assess ids and provisional ids by index so the mix is exact.
  const gateByIndex = new Array<'cannot_assess' | 'provisional' | 'measured'>(WATER_POINT_COUNT);
  for (let i = 0; i < WATER_POINT_COUNT; i++) gateByIndex[i] = 'measured';
  const chosen = new Set<number>();
  while (chosen.size < CANNOT_ASSESS_COUNT) {
    const i = int(rng, 0, WATER_POINT_COUNT - 1);
    if (!chosen.has(i)) {
      chosen.add(i);
      gateByIndex[i] = 'cannot_assess';
    }
  }
  while (chosen.size < CANNOT_ASSESS_COUNT + PROVISIONAL_COUNT) {
    const i = int(rng, 0, WATER_POINT_COUNT - 1);
    if (!chosen.has(i)) {
      chosen.add(i);
      gateByIndex[i] = 'provisional';
    }
  }

  const entities: FixtureEntity[] = [];
  for (let i = 0; i < WATER_POINT_COUNT; i++) {
    const id = `wp-${String(i + 1).padStart(3, '0')}`;
    const gate = gateByIndex[i] ?? 'measured';
    entities.push(makeWaterPoint(rng, id, gate));
  }

  entities.push(makeRoadSegment(rng));

  // Findings: 3 published across 3 distinct measured entities (not the same as the held one).
  const measuredEntityIds = entities
    .filter((e) => e.detail.gate_status === 'measured' && e.detail.entity_type === 'water_point')
    .slice(0, 10)
    .map((e) => e.detail.id);
  const [m0, m1, m2, m3] = measuredEntityIds;
  if (!m0 || !m1 || !m2 || !m3) {
    throw new Error('Not enough measured water points to seed findings');
  }
  attachFinding(entities, m0, 'published', 'high', '2.4');
  attachFinding(entities, m1, 'published', 'medium', '2.2');
  attachFinding(entities, m2, 'published', 'low', '2.2');
  attachFinding(entities, m3, 'held', 'medium', '2.4');

  const byId = new Map<string, FixtureEntity>();
  for (const e of entities) byId.set(e.detail.id, e);

  return { entities, byId };
}

// --- helpers ---

function makeWaterPoint(
  rng: () => number,
  id: string,
  gate: 'measured' | 'provisional' | 'cannot_assess'
): FixtureEntity {
  const lng = range(rng, NAIROBI_BBOX.minLng, NAIROBI_BBOX.maxLng);
  const lat = range(rng, NAIROBI_BBOX.minLat, NAIROBI_BBOX.maxLat);
  const geometry: FixtureGeometry = { type: 'Point', coordinates: [lng, lat] };

  const ward = pick(rng, WARD_NAMES);
  const name = `Water point ${id.split('-')[1]} (${ward})`;

  const detail = makeEntityDetail(rng, id, 'water_point', 'water', name, ward, gate);
  return { detail, geometry };
}

function makeRoadSegment(rng: () => number): FixtureEntity {
  const id = 'rd-001';
  // A short illustrative line across Nairobi.
  const coords: [number, number][] = [
    [36.82, -1.3],
    [36.835, -1.295],
    [36.85, -1.288],
    [36.864, -1.282],
    [36.878, -1.278],
  ];
  const geometry: FixtureGeometry = { type: 'LineString', coordinates: coords };
  const detail = makeEntityDetail(
    rng,
    id,
    'road_segment',
    'roads',
    'Thika super highway — segment 1 (illustrative)',
    'Kasarani',
    'measured'
  );
  return { detail, geometry };
}

function makeEntityDetail(
  rng: () => number,
  id: string,
  entity_type: 'water_point' | 'road_segment' | 'ward',
  module: 'water' | 'roads' | 'land',
  name: string,
  ward: string,
  gate: 'measured' | 'provisional' | 'cannot_assess'
): EntityDetail {
  const confidenceFactor = gate === 'provisional' ? PROVISIONAL_CONFIDENCE_FACTOR : 1.0;

  // Generate sub-variables first; variables roll up from them.
  const sub_variables = SUBVAR_SPECS.map((spec) =>
    makeSubVariable(rng, spec, gate, confidenceFactor)
  );

  const variables: VariableScore[] = (['V1', 'V2', 'V3', 'V4', 'V5'] as const).map((v) =>
    rollUpVariable(v, sub_variables, gate, confidenceFactor)
  );

  const last_observed_at = gate === 'cannot_assess' ? null : isoRecent(rng, 60);
  const last_computed_at = BASE_COMPUTED_AT;

  return {
    id,
    entity_type,
    module,
    name,
    ward,
    gate_status: gate,
    last_observed_at,
    last_computed_at,
    variables,
    sub_variables,
    findings: [],
    attribution: ATTRIBUTION_LINE,
  };
}

function makeSubVariable(
  rng: () => number,
  spec: SubVarSpec,
  gate: 'measured' | 'provisional' | 'cannot_assess',
  confidenceFactor: number
): SubVariableScore {
  // When the entity failed its V1 gate, no sub-variable is measured.
  // Otherwise ~25% of sub-variables are null_not_measured with a reason.
  const isMeasured = gate !== 'cannot_assess' && rng() > 0.25;

  const sources: Source[] = (SOURCE_POOLS[spec.id] ?? []).map((s) => ({ ...s }));

  if (!isMeasured) {
    const reason =
      gate === 'cannot_assess'
        ? 'the activity gate (1.1) could not be measured for this entity'
        : (pick(rng, NULL_REASON_POOL) as string);
    const status: SubVariableStatus = reason.startsWith('ward-level')
      ? 'null_area_only'
      : 'null_not_measured';
    return {
      sub_variable: spec.id,
      variable: spec.variable,
      label: spec.label,
      value: null,
      unit: spec.unit,
      score: null,
      confidence: null,
      status,
      null_reason: reason,
      observed_at: null,
      sources,
    };
  }

  const value = round(range(rng, spec.valueMin, spec.valueMax), 2);
  const score = int(rng, 20, 95);
  const confidence = round(range(rng, 0.5, 0.95) * confidenceFactor, 2);

  return {
    sub_variable: spec.id,
    variable: spec.variable,
    label: spec.label,
    value,
    unit: spec.unit,
    score,
    confidence,
    status: 'measured',
    null_reason: null,
    observed_at: isoRecent(rng, 30),
    sources,
  };
}

function rollUpVariable(
  variable: Variable,
  subVariables: readonly SubVariableScore[],
  gate: 'measured' | 'provisional' | 'cannot_assess',
  confidenceFactor: number
): VariableScore {
  const relevant = subVariables.filter((s) => s.variable === variable);
  const measured = relevant.filter((s) => s.status === 'measured' && s.score !== null);
  const total_count = VARIABLE_CONTRIBUTORS[variable];
  const measured_count = Math.min(measured.length, total_count);
  const coverage = round(measured_count / total_count, 2);

  if (measured_count === 0 || gate === 'cannot_assess') {
    return {
      variable,
      name: VARIABLE_NAMES[variable],
      score: null,
      coverage,
      confidence: 0,
      status: 'cannot_assess',
      gate_status: gate,
      measured_count,
      total_count,
      computed_at: BASE_COMPUTED_AT,
    };
  }

  const avgScore = Math.round(
    measured.reduce((sum, s) => sum + (s.score ?? 0), 0) / measured_count
  );
  const avgConfidence = round(
    (measured.reduce((sum, s) => sum + (s.confidence ?? 0), 0) / measured_count) * confidenceFactor,
    2
  );

  const status = gate === 'provisional' || coverage < 1 ? 'partly_verified' : 'measured';

  return {
    variable,
    name: VARIABLE_NAMES[variable],
    score: avgScore,
    coverage,
    confidence: avgConfidence,
    status,
    gate_status: gate,
    measured_count,
    total_count,
    computed_at: BASE_COMPUTED_AT,
  };
}

function attachFinding(
  entities: FixtureEntity[],
  entityId: string,
  state: FindingSummary['state'],
  severity: Severity,
  subVariable: '2.1' | '2.2' | '2.4'
): void {
  const entity = entities.find((e) => e.detail.id === entityId);
  if (!entity) throw new Error(`attachFinding: unknown entity ${entityId}`);

  const stories: Record<string, { record_says: string; we_observe: string; gap_summary: string }> =
    {
      '2.1': {
        record_says: 'The register lists no capacity for this point.',
        we_observe: 'The point is operating and serving a visible catchment.',
        gap_summary: 'Register row is missing a capacity figure for an operating point.',
      },
      '2.2': {
        record_says: 'Capacity recorded as 300 m³/day.',
        we_observe: 'Operator log for the last 14 days shows a median of 180 m³/day.',
        gap_summary: 'Observed capacity is 40% below the recorded figure.',
      },
      '2.4': {
        record_says: 'Status recorded as "fully operational".',
        we_observe: 'Last three community observations report intermittent flow.',
        gap_summary: 'Observed status differs from the recorded status.',
      },
    };
  const story = stories[subVariable];
  if (!story) throw new Error(`attachFinding: no story for sub-variable ${subVariable}`);
  const detected_at = '2026-10-04T09:00:00Z';
  const published_at = state === 'published' ? '2026-10-04T15:00:00Z' : null;

  entity.detail.findings.push({
    id: `${entityId}-finding-${entity.detail.findings.length + 1}`,
    sub_variable: subVariable,
    state,
    severity,
    detected_at,
    record_says: story.record_says,
    we_observe: story.we_observe,
    gap_summary: story.gap_summary,
    published_at,
  });
}

function round(n: number, places: number): number {
  const f = Math.pow(10, places);
  return Math.round(n * f) / f;
}

function isoRecent(rng: () => number, maxDaysAgo: number): string {
  const base = Date.UTC(2026, 9, 5, 16, 0, 0); // 2026-10-05 fixed base
  const offset = int(rng, 0, maxDaysAgo) * 24 * 60 * 60 * 1000;
  return new Date(base - offset).toISOString();
}
