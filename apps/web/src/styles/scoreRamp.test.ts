// Proves the band boundaries in DEC-15 and that tokens.css and scoreRamp.ts agree on the
// four hexes — the one place the ramp could silently fork into two.

import { describe, it, expect } from 'vitest';
// Vite's ?raw gives us the stylesheet as text, so the assertion reads the committed
// tokens.css rather than a copy of its values.
import tokensCss from './tokens.css?raw';
import {
  CANNOT_ASSESS_HEX,
  SCORE_PATTERN_HEX,
  SCORE_RAMP_HEX,
  scoreBandFor,
  type ScoreBand,
} from './scoreRamp';

function hexOfToken(tokenName: string): string | undefined {
  return new RegExp(`--${tokenName}:\\s*(#[0-9a-f]{6})`, 'i').exec(tokensCss)?.[1];
}

describe('scoreBandFor', () => {
  it.each<[number, ScoreBand]>([
    [100, 'b1'],
    [80, 'b1'],
    [79, 'b2'],
    [60, 'b2'],
    [59, 'b3'],
    [40, 'b3'],
    [39, 'b4'],
    [0, 'b4'],
  ])('maps a score of %i to band %s', (score, expectedBand) => {
    expect(scoreBandFor(score)).toBe(expectedBand);
  });

  it('throws rather than guess a band for a score outside 0-100', () => {
    expect(() => scoreBandFor(-1)).toThrow(RangeError);
    expect(() => scoreBandFor(101)).toThrow(RangeError);
    expect(() => scoreBandFor(Number.NaN)).toThrow(RangeError);
  });
});

describe('ramp hexes', () => {
  it('matches the hex declared for the same band in tokens.css', () => {
    const bands: ScoreBand[] = ['b1', 'b2', 'b3', 'b4'];

    for (const band of bands) {
      expect(hexOfToken(`color-score-${band}`), `tokens.css --color-score-${band}`).toBe(
        SCORE_RAMP_HEX[band]
      );
    }
  });

  it('matches the pattern and cannot-assess hexes declared in tokens.css', () => {
    expect(hexOfToken('color-score-pattern')).toBe(SCORE_PATTERN_HEX);

    expect(hexOfToken('color-score-cannot-assess')).toBe(CANNOT_ASSESS_HEX);
  });
});
