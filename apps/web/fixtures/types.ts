// Fixture types mirror the OpenAPI schema (components.schemas.EntityDetail).
// If the OpenAPI shape changes, both this file and the generator move together.
// The mock serves these as JSON for /api/v1/entities/{id} and projects them into GeoJSON
// for the MVT tile source.

export type Variable = 'V1' | 'V2' | 'V3' | 'V4' | 'V5';

export type GateStatus = 'measured' | 'provisional' | 'cannot_assess';
export type VariableStatus = 'measured' | 'partly_verified' | 'cannot_assess';
export type SubVariableStatus =
  'measured' | 'null_not_measured' | 'null_area_only' | 'null_not_applicable';
export type FindingState = 'held' | 'explanation_checked' | 'published' | 'dismissed' | 'resolved';
export type Severity = 'low' | 'medium' | 'high';

export interface Source {
  name: string;
  kind: 'register' | 'observation' | 'satellite' | 'open_data' | 'community';
  date: string; // YYYY-MM-DD
}

export interface SubVariableScore {
  sub_variable: string; // e.g. '1.1', '2.4'
  variable: Variable;
  label: string;
  value: number | null;
  unit: string | null;
  score: number | null;
  confidence: number | null;
  status: SubVariableStatus;
  null_reason: string | null;
  observed_at: string | null;
  sources: Source[];
}

export interface VariableScore {
  variable: Variable;
  name: string;
  score: number | null;
  coverage: number;
  confidence: number;
  status: VariableStatus;
  gate_status: GateStatus;
  measured_count: number;
  total_count: number;
  computed_at: string;
}

export interface FindingSummary {
  id: string;
  sub_variable: string;
  state: FindingState;
  severity: Severity;
  detected_at: string;
  record_says: string;
  we_observe: string;
  gap_summary: string;
  published_at: string | null;
}

export interface EntityDetail {
  id: string;
  entity_type: 'water_point' | 'road_segment' | 'ward';
  module: 'water' | 'roads' | 'land';
  name: string;
  ward: string;
  gate_status: GateStatus;
  last_observed_at: string | null;
  last_computed_at: string;
  variables: VariableScore[];
  sub_variables: SubVariableScore[];
  findings: FindingSummary[];
  attribution: string;
}

export interface FixtureGeometry {
  type: 'Point' | 'LineString';
  coordinates: [number, number] | [number, number][];
}

export interface FixtureEntity {
  detail: EntityDetail;
  geometry: FixtureGeometry;
}

export const ATTRIBUTION_LINE =
  'Contains modified Copernicus Sentinel data 2026 · © OpenStreetMap contributors · Digital Earth Africa · GRID3';
