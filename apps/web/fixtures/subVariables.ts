// Fixture-only module metadata layered on top of the canonical sub-variable catalogue
// (which lives in src/copy/subVariables.ts, imported by browser code).

export {
  SUB_VARIABLES,
  weightedContributorCount,
  roleOf,
  type SubVariable,
  type SubVariableRole,
  type SubVariableSpec,
} from '../src/copy/subVariables';

// Sub-variables the water module measures today (CLAUDE.md §4 / DEC-23 trim).
// Everything else is reported as 'not measured — not part of the water module yet'.
export const WATER_MODULE_MEASURED = new Set<string>([
  '1.1',
  '1.2',
  '2.1',
  '2.2',
  '2.4',
  '2.5',
  '4.4',
  '5.2',
]);

export const NOT_PART_OF_WATER_YET = 'not part of the water module yet';
