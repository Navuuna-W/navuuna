// The sequential, colour-blind-safe scale for entity scores. Never red-amber-green.
//
// Nine classes exist. The four bands (b1–b4) are the fill colours; the 'p' suffix means
// partly verified (hatch is approximated for circles by a hollow ring); 'ca' is a neutral
// grey, drawn as a hollow circle so a user never reads it as "zero".
//
// Keep this file the one place that maps colour_class → paint. If a visual rule changes,
// the test file catches the dependents.

import type { ExpressionSpecification } from 'maplibre-gl';

export type ColourClass = 'b1' | 'b2' | 'b3' | 'b4' | 'b1p' | 'b2p' | 'b3p' | 'b4p' | 'ca';

// Dark → light sequential ramp.
export const COLOUR_FOR: Record<ColourClass, string> = {
  b1: '#0D3B66',
  b2: '#3E85B0',
  b3: '#93C5D8',
  b4: '#E6E6EA',
  b1p: '#0D3B66',
  b2p: '#3E85B0',
  b3p: '#93C5D8',
  b4p: '#E6E6EA',
  ca: '#BFBFBF',
};

// Hatch-like effect for partly verified: hollow fill, coloured ring.
export function isPartlyVerified(c: ColourClass): boolean {
  return c.endsWith('p');
}

export function isCannotAssess(c: ColourClass): boolean {
  return c === 'ca';
}

// MapLibre expression: colour_class → fill colour. Includes a safe fallback.
export function circleColorExpression(): ExpressionSpecification {
  return [
    'match',
    ['get', 'colour_class'],
    'b1',
    COLOUR_FOR.b1,
    'b2',
    COLOUR_FOR.b2,
    'b3',
    COLOUR_FOR.b3,
    'b4',
    COLOUR_FOR.b4,
    'b1p',
    'transparent',
    'b2p',
    'transparent',
    'b3p',
    'transparent',
    'b4p',
    'transparent',
    'ca',
    'transparent',
    COLOUR_FOR.b4,
  ];
}

export function circleStrokeColorExpression(): ExpressionSpecification {
  return [
    'match',
    ['get', 'colour_class'],
    'b1p',
    COLOUR_FOR.b1p,
    'b2p',
    COLOUR_FOR.b2p,
    'b3p',
    COLOUR_FOR.b3p,
    'b4p',
    COLOUR_FOR.b4p,
    'ca',
    COLOUR_FOR.ca,
    '#333333',
  ];
}

export function circleStrokeWidthExpression(): ExpressionSpecification {
  return ['match', ['get', 'colour_class'], 'b1p', 2, 'b2p', 2, 'b3p', 2, 'b4p', 2, 'ca', 1.5, 0.5];
}

export function circleRadiusExpression(): ExpressionSpecification {
  return ['interpolate', ['linear'], ['zoom'], 10, 3, 14, 6, 18, 10];
}
