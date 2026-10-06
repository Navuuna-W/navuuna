// Applies a lens to five variable scores, producing an integer lens score and a
// colour_class. The county-planner lens from DEC-15 is the only lens today.
//
// Rules (Bible §6.6, DEC-15):
//   - Weighted mean over available (non-null) variable scores. 'cannot_assess' excluded.
//   - Weights are renormalised across the included variables so missing contributors do not
//     silently push the score toward zero.
//   - Direction is "higher is better" for every variable in the county planner lens.
//   - Bands 80/60/40 → colour_class b1/b2/b3/b4.
//   - Partly verified entities get a 'p' suffix: b1p, b2p, b3p, b4p.
//   - Cannot assess (every variable null) returns null + 'ca'.

import type { VariableScore } from './types';

export type ColourClass = 'b1' | 'b2' | 'b3' | 'b4' | 'b1p' | 'b2p' | 'b3p' | 'b4p' | 'ca';

export interface LensConfig {
  id: string;
  name: string;
  weights: Record<'V1' | 'V2' | 'V3' | 'V4' | 'V5', number>;
}

export const COUNTY_PLANNER_LENS: LensConfig = {
  id: 'county_planner',
  name: 'County planner',
  weights: { V1: 0.4, V2: 0.3, V3: 0.0, V4: 0.1, V5: 0.2 },
};

const BAND_HIGH = 80;
const BAND_MEDIUM = 60;
const BAND_LOW = 40;

export interface LensResult {
  lens_score: number | null;
  colour_class: ColourClass;
}

export function applyLens(
  variables: readonly VariableScore[],
  lens: LensConfig,
  isPartlyVerified: boolean
): LensResult {
  let weightedSum = 0;
  let weightTotal = 0;

  for (const v of variables) {
    if (v.score === null) continue;
    const weight = lens.weights[v.variable];
    if (weight === 0) continue;
    weightedSum += v.score * weight;
    weightTotal += weight;
  }

  if (weightTotal === 0) {
    return { lens_score: null, colour_class: 'ca' };
  }

  const score = Math.round(weightedSum / weightTotal);
  const band = bandFor(score);
  const colour_class = (isPartlyVerified ? `${band}p` : band) as ColourClass;
  return { lens_score: score, colour_class };
}

function bandFor(score: number): 'b1' | 'b2' | 'b3' | 'b4' {
  if (score >= BAND_HIGH) return 'b1';
  if (score >= BAND_MEDIUM) return 'b2';
  if (score >= BAND_LOW) return 'b3';
  return 'b4';
}
