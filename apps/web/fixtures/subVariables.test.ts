// Pins the X-of-Y rule from DEC-11 and verifies the canonical catalogue stays complete.

import { describe, it, expect } from 'vitest';
import {
  SUB_VARIABLES,
  WATER_MODULE_MEASURED,
  roleOf,
  weightedContributorCount,
} from './subVariables';

describe('SUB_VARIABLES', () => {
  it('contains the 29 canonical rows from CONTEXT.md (28 scored + 1 guard)', () => {
    // Bible §6.8 is titled "The 28 sub-variables"; CONTEXT.md ships 29 rows because the
    // 2.6 guard is enumerated alongside the 28 scored contributors. We mirror
    // CONTEXT.md verbatim so a Devyan-side change is a one-file update here.
    expect(SUB_VARIABLES).toHaveLength(29);
  });

  it('groups into V1=5, V2=6, V3=6, V4=6, V5=6', () => {
    const counts: Record<string, number> = { V1: 0, V2: 0, V3: 0, V4: 0, V5: 0 };
    for (const s of SUB_VARIABLES) {
      const prev = counts[s.variable] ?? 0;
      counts[s.variable] = prev + 1;
    }
    expect(counts).toEqual({ V1: 5, V2: 6, V3: 6, V4: 6, V5: 6 });
  });

  it('marks exactly two gates (1.1, 2.1) and exactly one guard (2.6)', () => {
    const gates = SUB_VARIABLES.filter((s) => s.role === 'gate').map((s) => s.id);
    const guards = SUB_VARIABLES.filter((s) => s.role === 'guard').map((s) => s.id);
    expect(gates).toEqual(['1.1', '2.1']);
    expect(guards).toEqual(['2.6']);
  });
});

describe('weightedContributorCount (DEC-11)', () => {
  it('returns V1 4, V2 4, V3 6, V4 6, V5 6 — gates and the guard are not counted', () => {
    expect(weightedContributorCount('V1')).toBe(4);
    expect(weightedContributorCount('V2')).toBe(4);
    expect(weightedContributorCount('V3')).toBe(6);
    expect(weightedContributorCount('V4')).toBe(6);
    expect(weightedContributorCount('V5')).toBe(6);
  });

  it('agrees with roleOf for every sub-variable id in the catalogue', () => {
    for (const s of SUB_VARIABLES) {
      expect(roleOf(s.id)).toBe(s.role);
    }
  });
});

describe('WATER_MODULE_MEASURED', () => {
  it('names the eight water sub-variables from CLAUDE.md §4 / DEC-23 trim', () => {
    expect([...WATER_MODULE_MEASURED].sort()).toEqual(
      ['1.1', '1.2', '2.1', '2.2', '2.4', '2.5', '4.4', '5.2'].sort()
    );
  });

  it('every id in the water measured set is in the canonical catalogue', () => {
    const ids = new Set(SUB_VARIABLES.map((s) => s.id));
    for (const id of WATER_MODULE_MEASURED) {
      expect(ids.has(id), `${id} not in canonical SUB_VARIABLES`).toBe(true);
    }
  });
});
