// The score ramp as code: which band a 0–100 score falls in, and the hex each band is
// painted in. Sits between the engine's score and anything that draws it — map paint
// (src/map/style/scale.ts) and the legend both read from here, so there is one ramp.
// The same hexes are declared in tokens.css; scoreRamp.test.ts fails if the two drift.

/** The four score bands, best to worst. DEC-15 and docs/contracts/tiles.md §5. */
export type ScoreBand = 'b1' | 'b2' | 'b3' | 'b4';

export const LOWEST_SCORE = 0;
export const HIGHEST_SCORE = 100;

/**
 * Each band with the lowest score that falls in it, highest band first.
 * Implements DEC-15: b1 80–100, b2 60–79, b3 40–59, b4 0–39.
 */
const BANDS_BY_FLOOR: readonly { band: ScoreBand; lowestScore: number }[] = [
  { band: 'b1', lowestScore: 80 },
  { band: 'b2', lowestScore: 60 },
  { band: 'b3', lowestScore: 40 },
  { band: 'b4', lowestScore: LOWEST_SCORE },
];

/**
 * Returns the band a lens score falls in.
 * Throws outside 0–100 rather than guessing: an out-of-range score is a bug upstream, and
 * silently banding it would put a wrong colour on the map (CLAUDE.md §4).
 */
export function scoreBandFor(score: number): ScoreBand {
  if (!Number.isFinite(score) || score < LOWEST_SCORE || score > HIGHEST_SCORE) {
    throw new RangeError(`Score ${String(score)} is outside ${LOWEST_SCORE}–${HIGHEST_SCORE}`);
  }

  const match = BANDS_BY_FLOOR.find((candidate) => score >= candidate.lowestScore);
  if (match === undefined) throw new RangeError(`No band for score ${String(score)}`);

  return match.band;
}

/**
 * One single-hue sequential ramp at OKLCH hue 153.9 — the hue of the reference's primary
 * accent, whose own hex is b3. Darker = higher score. Never red/amber/green (CLAUDE.md §4).
 * Verified by `npm run check:tokens`: ≥ 3:1 against the basemap and CVD-separated steps.
 */
export const SCORE_RAMP_HEX: Readonly<Record<ScoreBand, string>> = {
  b1: '#00371b',
  b2: '#005d31',
  b3: '#2b7a4b',
  b4: '#499565',
};

/** Hatch stroke for partly verified (`p`): the basemap colour, so the map shows through. */
export const SCORE_PATTERN_HEX = '#f2f4ea';

/** Off-ramp neutral for cannot assess (`ca`). Drawn with a pattern, and never as 0. */
export const CANNOT_ASSESS_HEX = '#787774';
