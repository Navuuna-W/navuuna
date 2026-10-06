// Lens arithmetic. Known input → known output, as the Bible asks (A-11 unit test).

import { describe, it, expect } from 'vitest';
import { applyLens, COUNTY_PLANNER_LENS } from './lensApply';
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

describe('applyLens — county planner', () => {
  it('weights V1 .40 V2 .30 V4 .10 V5 .20 (V3 = 0)', () => {
    // Expected: 100*.40 + 80*.30 + <ignored> + 60*.10 + 50*.20 = 40 + 24 + 6 + 10 = 80
    const result = applyLens(
      [v('V1', 100), v('V2', 80), v('V3', 10), v('V4', 60), v('V5', 50)],
      COUNTY_PLANNER_LENS,
      false
    );
    expect(result.lens_score).toBe(80);
    expect(result.colour_class).toBe('b1');
  });

  it('renormalises when a variable is cannot_assess', () => {
    // V5 null → remaining weights .40 + .30 + .10 = .80; weighted mean = (100*.4 + 80*.3 + 60*.1)/.8 = 87.5 → 88
    const result = applyLens(
      [v('V1', 100), v('V2', 80), v('V3', 0), v('V4', 60), v('V5', null)],
      COUNTY_PLANNER_LENS,
      false
    );
    expect(result.lens_score).toBe(88);
    expect(result.colour_class).toBe('b1');
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
    expect(result.colour_class).toBe('b1p');
  });

  it('bands correctly: >=80 b1, >=60 b2, >=40 b3, else b4', () => {
    expect(
      applyLens(
        [v('V1', 100), v('V2', 100), v('V3', 0), v('V4', 100), v('V5', 100)],
        COUNTY_PLANNER_LENS,
        false
      ).colour_class
    ).toBe('b1');
    expect(
      applyLens(
        [v('V1', 70), v('V2', 70), v('V3', 0), v('V4', 70), v('V5', 70)],
        COUNTY_PLANNER_LENS,
        false
      ).colour_class
    ).toBe('b2');
    expect(
      applyLens(
        [v('V1', 50), v('V2', 50), v('V3', 0), v('V4', 50), v('V5', 50)],
        COUNTY_PLANNER_LENS,
        false
      ).colour_class
    ).toBe('b3');
    expect(
      applyLens(
        [v('V1', 20), v('V2', 20), v('V3', 0), v('V4', 20), v('V5', 20)],
        COUNTY_PLANNER_LENS,
        false
      ).colour_class
    ).toBe('b4');
  });
});
