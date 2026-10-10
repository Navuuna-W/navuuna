// Re-measures the committed design tokens and exits non-zero if any pair fails.
// Reads the hexes out of src/styles/tokens.css, so changing a token re-runs the real check
// rather than a stale copy of it. Two things are proved: WCAG contrast for every pair that
// carries meaning, and that adjacent score-ramp steps stay apart under deuteranopia and
// protanopia. Run with `npm run check:tokens`. Thresholds: DESIGN.md §1.3 and §1.7.

import { readFileSync } from 'node:fs';
import { dirname, join } from 'node:path';
import { fileURLToPath } from 'node:url';
import {
  filterDeficiencyDeuter,
  filterDeficiencyProt,
  formatHex,
  differenceCiede2000,
  wcagContrast,
} from 'culori';

const TEXT_MINIMUM = 4.5; // WCAG 2.1 AA, normal-size text
const UI_MINIMUM = 3.0; // WCAG 2.1 AA, non-text contrast: borders, map fills, focus ring
// Two adjacent ramp steps must read as different fills. 1.35:1 is the floor we hold them
// to, under normal vision and under both simulations; CIEDE2000 10 is the second opinion.
const ADJACENT_STEP_MINIMUM = 1.35;
const ADJACENT_STEP_DELTA_E_MINIMUM = 10;

const TOKENS_CSS = readFileSync(
  join(dirname(fileURLToPath(import.meta.url)), '..', 'src', 'styles', 'tokens.css'),
  'utf8'
);

/** Returns the hex a token is set to in tokens.css. Throws if the token is not declared. */
function token(name) {
  const found = new RegExp(`--${name}:\\s*(#[0-9a-f]{3,8})`, 'i').exec(TOKENS_CSS);
  if (found === null) throw new Error(`tokens.css does not declare --${name}`);
  return found[1];
}

const deuteranopia = filterDeficiencyDeuter(1);
const protanopia = filterDeficiencyProt(1);
const deltaE = differenceCiede2000();

const PAGE = token('color-page');
const SURFACE = token('color-surface');
const SUNKEN = token('color-sunken');
const MAP = token('color-map');
const RAMP = ['b1', 'b2', 'b3', 'b4'].map((band) => ({
  name: `score-${band}`,
  hex: token(`color-score-${band}`),
}));

/** Every pair where failing the ratio would hide something a user needs to see. */
const PAIRS = [
  ...pairsAgainst('color-ink', [PAGE, SURFACE, SUNKEN], TEXT_MINIMUM),
  ...pairsAgainst('color-muted', [PAGE, SURFACE, SUNKEN], TEXT_MINIMUM),
  ...pairsAgainst('color-accent', [PAGE, SURFACE], TEXT_MINIMUM),
  ...pairsAgainst('color-change', [PAGE, SURFACE, SUNKEN], TEXT_MINIMUM),
  ...pairsAgainst('color-notice', [PAGE, SURFACE, SUNKEN], TEXT_MINIMUM),
  ...pairsAgainst('color-error', [PAGE, SURFACE, SUNKEN], TEXT_MINIMUM),
  ...pairsAgainst('color-line', [PAGE, SURFACE], UI_MINIMUM),
  ...pairsAgainst('color-water', [SURFACE, MAP], UI_MINIMUM),
  ...pairsAgainst('color-observed', [SURFACE, MAP], UI_MINIMUM),
  ...pairsAgainst('color-score-cannot-assess', [MAP], UI_MINIMUM),
  // The whole point of the ramp: four fills that each stand off the basemap land colour.
  ...RAMP.map((step) => ({
    foreground: step.hex,
    name: step.name,
    background: MAP,
    minimum: UI_MINIMUM,
  })),
  // The `p` hatch is stroked over the band fills, so it must stand off the lightest one.
  {
    foreground: token('color-score-pattern'),
    name: 'score-pattern',
    background: RAMP[3].hex,
    minimum: UI_MINIMUM,
  },
];

function pairsAgainst(name, backgrounds, minimum) {
  return backgrounds.map((background) => ({ foreground: token(name), name, background, minimum }));
}

let failures = 0;

console.log('\nContrast — measured from src/styles/tokens.css\n');
console.log('  on        colour     ratio   needs  ');
for (const pair of PAIRS) {
  const ratio = wcagContrast(pair.foreground, pair.background);
  const passed = ratio >= pair.minimum;
  if (!passed) failures += 1;
  console.log(
    `  ${pair.background}  ${pair.foreground}  ${ratio.toFixed(2).padStart(6)}  ${pair.minimum.toFixed(1).padStart(5)}  ` +
      `${passed ? 'pass' : 'FAIL'}  ${pair.name}`
  );
}

console.log('\nAdjacent ramp steps under simulated colour vision deficiency\n');
console.log('  pair     normal  deuteranopia             protanopia               dE2000');
for (let step = 0; step < RAMP.length - 1; step += 1) {
  const lower = RAMP[step].hex;
  const upper = RAMP[step + 1].hex;
  const deuter = [formatHex(deuteranopia(lower)), formatHex(deuteranopia(upper))];
  const protan = [formatHex(protanopia(lower)), formatHex(protanopia(upper))];
  const ratios = {
    normal: wcagContrast(lower, upper),
    deuteranopia: wcagContrast(deuter[0], deuter[1]),
    protanopia: wcagContrast(protan[0], protan[1]),
  };
  const difference = deltaE(lower, upper);

  const worst = Math.min(ratios.normal, ratios.deuteranopia, ratios.protanopia);
  const passed = worst >= ADJACENT_STEP_MINIMUM && difference >= ADJACENT_STEP_DELTA_E_MINIMUM;
  if (!passed) failures += 1;

  console.log(
    `  b${step + 1}/b${step + 2}    ${ratios.normal.toFixed(2)}    ` +
      `${deuter.join(' ')} ${ratios.deuteranopia.toFixed(2)}    ` +
      `${protan.join(' ')} ${ratios.protanopia.toFixed(2)}    ` +
      `${difference.toFixed(1).padStart(4)}  ${passed ? 'pass' : 'FAIL'}`
  );
}
console.log(
  `\n  floors: adjacent steps ≥ ${ADJACENT_STEP_MINIMUM}:1 under all three, and dE2000 ≥ ${ADJACENT_STEP_DELTA_E_MINIMUM}`
);

console.log(`\n${failures === 0 ? 'All pairs pass.' : `${failures} pair(s) FAILED.`}\n`);
process.exit(failures === 0 ? 0 : 1);
