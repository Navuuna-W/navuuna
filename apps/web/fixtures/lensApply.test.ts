// Lens arithmetic. Known input → known output, as the Bible asks (A-11 unit test).
// Mirrors origin/feat/signals-county-planner-lens:lenses/county_planner.json — V2 is
// lower-is-better; the other four are higher-is-better.

import { describe, it, expect } from 'vitest';
import { applyLens, COUNTY_PLANNER_LENS, type LensConfig } from './lensApply';
import type { VariableScore } from './types';

function v(variable: VariableScore['variable'], score: number | null): VariableScore {
  return {
    variable,
    name: variable,
    score,
    coverage: 0.8,
    confidence: 0.7,
    status: score === null ? 'cannot_assess' : 'measured',
    gate_status: 'measured',
    measured_count: 1,
    total_count: 1,
    computed_at: '2026-10-01T00:00:00Z',
  };
}

// Variant with all-higher-is-better directions, used to keep the banding test readable.
const HIGHER_IS_BETTER_LENS: LensConfig = {
  id: 'test_higher',
  name: 'test higher',
  weights: { V1: 0.4, V2: 0.3, V3: 0.0, V4: 0.1, V5: 0.2 },
  direction: {
    V1: 'higher_is_better',
    V2: 'higher_is_better',
    V3: 'higher_is_better',
    V4: 'higher_is_better',
    V5: 'higher_is_better',
  },
};

describe('applyLens — county planner (V2 lower_is_better)', () => {
  it('flips V2 before weighting: V1 100, V2 80, V3 10 (w=0), V4 60, V5 50 → 62 (b2)', () => {
    // V2 lower_is_better: effective score = 100 − 80 = 20.
    // 100*.40 + 20*.30 + <ignored> + 60*.10 + 50*.20 = 40 + 6 + 6 + 10 = 62.
    const result = applyLens(
      [v('V1', 100), v('V2', 80), v('V3', 10), v('V4', 60), v('V5', 50)],
      COUNTY_PLANNER_LENS,
      false
    );
    expect(result.lens_score).toBe(62);
    expect(result.colour_class).toBe('b2');
  });

  it('renormalises when a variable is cannot_assess', () => {
    // V5 null → remaining weights .40 + .30 + .10 = .80.
    // (100*.40 + (100-80)*.30 + 60*.10) / .80 = (40 + 6 + 6) / .80 = 65 → b2.
    const result = applyLens(
      [v('V1', 100), v('V2', 80), v('V3', 0), v('V4', 60), v('V5', null)],
      COUNTY_PLANNER_LENS,
      false
    );
    expect(result.lens_score).toBe(65);
    expect(result.colour_class).toBe('b2');
  });

  it('returns cannot_assess when every weighted variable is null', () => {
    const result = applyLens(
      [v('V1', null), v('V2', null), v('V3', 60), v('V4', null), v('V5', null)],
      COUNTY_PLANNER_LENS,
      false
    );
    expect(result.lens_score).toBeNull();
    expect(result.colour_class).toBe('ca');
  });

  it('suffixes p when partly verified', () => {
    const result = applyLens(
      [v('V1', 100), v('V2', 80), v('V3', 10), v('V4', 60), v('V5', 50)],
      COUNTY_PLANNER_LENS,
      true
    );
    expect(result.colour_class).toBe('b2p');
  });
});

describe('applyLens — banding (all higher_is_better, uniform scores)', () => {
  it('>=80 → b1', () => {
    const r = applyLens(
      [v('V1', 100), v('V2', 100), v('V3', 0), v('V4', 100), v('V5', 100)],
      HIGHER_IS_BETTER_LENS,
      false
    );
    expect(r.colour_class).toBe('b1');
  });

  it('>=60 → b2', () => {
    const r = applyLens(
      [v('V1', 70), v('V2', 70), v('V3', 0), v('V4', 70), v('V5', 70)],
      HIGHER_IS_BETTER_LENS,
      false
    );
    expect(r.colour_class).toBe('b2');
  });

  it('>=40 → b3', () => {
    const r = applyLens(
      [v('V1', 50), v('V2', 50), v('V3', 0), v('V4', 50), v('V5', 50)],
      HIGHER_IS_BETTER_LENS,
      false
    );
    expect(r.colour_class).toBe('b3');
  });

  it('<40 → b4', () => {
    const r = applyLens(
      [v('V1', 20), v('V2', 20), v('V3', 0), v('V4', 20), v('V5', 20)],
      HIGHER_IS_BETTER_LENS,
      false
    );
    expect(r.colour_class).toBe('b4');
  });
});
