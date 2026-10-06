// The 29 canonical sub-variables (28 scored contributors + the 2.6 guard) with their
// on-screen labels, roles (gate / guard / contributor), and tooltips, verbatim from
// origin/docs/docs-context-glossary:docs/CONTEXT.md, commit
// 31d682e54e35836d46ae7dedc58893c9df629b49 (owner: Devyan, DEC-20).
//
// Role semantics (Bible §6.2, DEC-11):
//   - 'gate' vetos its variable when it fails; never counted in X-of-Y.
//   - 'guard' is the 2.6 explanation state; never counted in X-of-Y.
//   - 'contributor' is weighted and averaged; counted in X-of-Y.
//
// Why this lives in src/copy/ (not fixtures/): the panel, accordion and tile-label code
// all read it, and src/ cannot import from fixtures/ (project boundary — fixtures are
// Node-only, served by the dev mock). The fixture generator re-exports from here.

export type SubVariable = 'V1' | 'V2' | 'V3' | 'V4' | 'V5';
export type SubVariableRole = 'gate' | 'guard' | 'contributor';

export interface SubVariableSpec {
  id: string;
  variable: SubVariable;
  label: string;
  tooltip: string;
  role: SubVariableRole;
}

export const SUB_VARIABLES: readonly SubVariableSpec[] = [
  // V1 — Activity
  {
    id: '1.1',
    variable: 'V1',
    label: 'Present',
    tooltip: 'Does it exist where the record says?',
    role: 'gate',
  },
  {
    id: '1.2',
    variable: 'V1',
    label: 'Working now',
    tooltip: 'Is it functioning now: yes, no or on and off?',
    role: 'contributor',
  },
  {
    id: '1.3',
    variable: 'V1',
    label: 'How much it is used',
    tooltip: 'Used ÷ total capacity',
    role: 'contributor',
  },
  {
    id: '1.4',
    variable: 'V1',
    label: 'Uptime',
    tooltip: 'Time operating ÷ time expected',
    role: 'contributor',
  },
  {
    id: '1.5',
    variable: 'V1',
    label: 'Intensity',
    tooltip: 'Throughput per unit of capacity',
    role: 'contributor',
  },

  // V2 — Record vs reality
  {
    id: '2.1',
    variable: 'V2',
    label: 'Missing from the ground',
    tooltip: 'The record says it exists; the observation says it does not',
    role: 'gate',
  },
  {
    id: '2.2',
    variable: 'V2',
    label: 'Size gap',
    tooltip: '(declared − observed) ÷ declared',
    role: 'contributor',
  },
  {
    id: '2.3',
    variable: 'V2',
    label: 'Type gap',
    tooltip: 'Declared type, class or use vs observed',
    role: 'contributor',
  },
  {
    id: '2.4',
    variable: 'V2',
    label: 'Status gap',
    tooltip: 'Declared completion or operation vs observed',
    role: 'contributor',
  },
  {
    id: '2.5',
    variable: 'V2',
    label: 'Record age',
    tooltip: 'How far the record date lags the latest observation',
    role: 'contributor',
  },
  {
    id: '2.6',
    variable: 'V2',
    label: 'Explanation checked',
    tooltip: 'Is there a legitimate known reason for the gap?',
    role: 'guard',
  },

  // V3 — Momentum
  {
    id: '3.1',
    variable: 'V3',
    label: 'Direction',
    tooltip: 'Is the trend up or down?',
    role: 'contributor',
  },
  {
    id: '3.2',
    variable: 'V3',
    label: 'Rate',
    tooltip: 'How fast is it changing?',
    role: 'contributor',
  },
  {
    id: '3.3',
    variable: 'V3',
    label: 'Steadiness',
    tooltip: 'How much does it vary around the trend?',
    role: 'contributor',
  },
  {
    id: '3.4',
    variable: 'V3',
    label: 'Acceleration',
    tooltip: 'Is the rate itself changing?',
    role: 'contributor',
  },
  {
    id: '3.5',
    variable: 'V3',
    label: 'Neighbourhood trend',
    tooltip: 'What is changing nearby?',
    role: 'contributor',
  },
  {
    id: '3.6',
    variable: 'V3',
    label: 'Headroom',
    tooltip: 'How far from saturation?',
    role: 'contributor',
  },

  // V4 — Resource security
  {
    id: '4.1',
    variable: 'V4',
    label: 'Availability',
    tooltip: 'How much of the resource is there?',
    role: 'contributor',
  },
  {
    id: '4.2',
    variable: 'V4',
    label: 'Reliability',
    tooltip: 'How much does supply vary over time?',
    role: 'contributor',
  },
  {
    id: '4.3',
    variable: 'V4',
    label: 'Resource trend',
    tooltip: 'Is the resource improving or depleting?',
    role: 'contributor',
  },
  {
    id: '4.4',
    variable: 'V4',
    label: 'Competition',
    tooltip: 'Who else draws on the same resource?',
    role: 'contributor',
  },
  {
    id: '4.5',
    variable: 'V4',
    label: 'Hazard exposure',
    tooltip: 'How often and how badly is it disrupted?',
    role: 'contributor',
  },
  {
    id: '4.6',
    variable: 'V4',
    label: 'Buffer',
    tooltip: 'Storage, backup or alternative supply',
    role: 'contributor',
  },

  // V5 — Access
  {
    id: '5.1',
    variable: 'V5',
    label: 'Distance',
    tooltip: 'Travel time to get there',
    role: 'contributor',
  },
  {
    id: '5.2',
    variable: 'V5',
    label: 'Route condition',
    tooltip: 'Condition and class of the link',
    role: 'contributor',
  },
  {
    id: '5.3',
    variable: 'V5',
    label: 'Reachable all year',
    tooltip: 'Share of the year it can be reached',
    role: 'contributor',
  },
  {
    id: '5.4',
    variable: 'V5',
    label: 'Cost to reach',
    tooltip: 'Transport cost per unit',
    role: 'contributor',
  },
  {
    id: '5.5',
    variable: 'V5',
    label: 'Open and staffed',
    tooltip: 'Does the service actually operate?',
    role: 'contributor',
  },
  {
    id: '5.6',
    variable: 'V5',
    label: 'Alternatives',
    tooltip: 'Independent viable routes or sources',
    role: 'contributor',
  },
];

// Weighted-contributor count per variable (DEC-11): gates and the guard are NEVER counted.
export function weightedContributorCount(variable: SubVariable): number {
  return SUB_VARIABLES.filter((s) => s.variable === variable && s.role === 'contributor').length;
}

export function roleOf(id: string): SubVariableRole | undefined {
  return SUB_VARIABLES.find((s) => s.id === id)?.role;
}
