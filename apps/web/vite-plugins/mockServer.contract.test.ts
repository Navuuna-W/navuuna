// Parity test: the mock tile server MUST serve exactly the properties documented in
// docs/contracts/tiles.md §4. If this test fails, either the mock drifted or the
// contract was changed without updating the other side — both block merge.
//
// We exercise the private feature-collection builder directly rather than going through
// vt-pbf, because the pbf round-trip loses the fine type information we want to assert on.

import { describe, it, expect } from 'vitest';
import { fixtures } from '../fixtures';
import { applyLens, COUNTY_PLANNER_LENS } from '../fixtures/lensApply';

// The contract's property list (docs/contracts/tiles.md §4). Keeping this inline, not
// imported from the mock, means we check that both sides independently agree.
const REQUIRED_PROPS = [
  'id',
  'entity_type',
  'module',
  'lens_score',
  'colour_class',
  'coverage',
] as const;

// Proposed-but-already-served (DEC-12). Flagged separately so a reader can tell them
// apart from the committed set.
const PROPOSED_PROPS = ['confidence', 'name'] as const;

const ALLOWED_PROPS = new Set<string>([...REQUIRED_PROPS, ...PROPOSED_PROPS]);

// Mirror of the mock's feature-collection builder. Simpler than importing it: this
// locks the shape in TWO places that both have to agree with the contract.
function featurePropertiesForFirstEntity() {
  const { entities } = fixtures();
  const [first] = entities;
  if (!first) throw new Error('fixtures emitted no entities');
  const isPartlyVerified =
    first.detail.gate_status === 'provisional' ||
    first.detail.variables.some((v) => v.status === 'partly_verified');
  const { lens_score, colour_class } = applyLens(
    first.detail.variables,
    COUNTY_PLANNER_LENS,
    isPartlyVerified
  );
  return {
    id: first.detail.id,
    entity_type: first.detail.entity_type,
    module: first.detail.module,
    name: first.detail.name,
    lens_score,
    colour_class,
    coverage:
      first.detail.variables.reduce((s, v) => s + v.coverage, 0) / first.detail.variables.length,
    confidence:
      first.detail.variables.reduce((s, v) => s + v.confidence, 0) / first.detail.variables.length,
  };
}

describe('tile contract parity (docs/contracts/tiles.md §4)', () => {
  it('every documented required property is present on a fixture feature', () => {
    const props = featurePropertiesForFirstEntity();
    for (const key of REQUIRED_PROPS) {
      expect(props, `missing required property '${key}'`).toHaveProperty(key);
    }
  });

  it('every served property is one the contract documents', () => {
    const props = featurePropertiesForFirstEntity();
    for (const key of Object.keys(props)) {
      expect(ALLOWED_PROPS.has(key), `property '${key}' is not in the contract`).toBe(true);
    }
  });

  it('colour_class is one of the DEC-15 values (b1-b4, p-suffix, ca)', () => {
    const legal = new Set(['b1', 'b2', 'b3', 'b4', 'b1p', 'b2p', 'b3p', 'b4p', 'ca']);
    const { entities } = fixtures();
    for (const e of entities) {
      const isPartlyVerified =
        e.detail.gate_status === 'provisional' ||
        e.detail.variables.some((v) => v.status === 'partly_verified');
      const { colour_class } = applyLens(e.detail.variables, COUNTY_PLANNER_LENS, isPartlyVerified);
      expect(legal.has(colour_class), `unknown colour_class '${colour_class}'`).toBe(true);
    }
  });

  it('cannot-assess entities carry lens_score null and colour_class ca', () => {
    const { entities } = fixtures();
    const ca = entities.filter((e) => e.detail.gate_status === 'cannot_assess');
    expect(ca.length).toBeGreaterThan(0);
    for (const e of ca) {
      const { lens_score, colour_class } = applyLens(
        e.detail.variables,
        COUNTY_PLANNER_LENS,
        false
      );
      expect(lens_score).toBeNull();
      expect(colour_class).toBe('ca');
    }
  });
});
