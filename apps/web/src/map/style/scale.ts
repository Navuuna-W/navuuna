// The sequential, colour-blind-safe scale for entity scores. Never red-amber-green.
// Keep this file the one place that maps colour_class → paint; the test file catches
// dependents. (Palette review itself is PR 1b.)

import type { ExpressionSpecification } from 'maplibre-gl';

export type ColourClass = 'b1' | 'b2' | 'b3' | 'b4' | 'b1p' | 'b2p' | 'b3p' | 'b4p' | 'ca';

export const ALL_COLOUR_CLASSES: readonly ColourClass[] = [
  'b1',
  'b2',
  'b3',
  'b4',
  'b1p',
  'b2p',
  'b3p',
  'b4p',
  'ca',
];

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

export const MEASURED_STROKE_COLOR = '#333333';
export const MEASURED_STROKE_WIDTH = 0.5;
export const PARTLY_VERIFIED_STROKE_WIDTH = 2;
export const CA_STROKE_WIDTH = 1.5;

// Hatch-like effect for partly verified: hollow fill, coloured ring.
export function isPartlyVerified(c: ColourClass): boolean {
  return c.endsWith('p');
}
export function isCannotAssess(c: ColourClass): boolean {
  return c === 'ca';
}

// Circle fill: solid for b1–b4; p + ca are hollow (ring carries the signal). The
// fallback branch of every expression goes to the ca style — an unknown colour_class
// must never render as a band fill (B1).
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
    'transparent',
  ];
}

export function circleStrokeColorExpression(): ExpressionSpecification {
  return [
    'match',
    ['get', 'colour_class'],
    'b1',
    MEASURED_STROKE_COLOR,
    'b2',
    MEASURED_STROKE_COLOR,
    'b3',
    MEASURED_STROKE_COLOR,
    'b4',
    MEASURED_STROKE_COLOR,
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
    COLOUR_FOR.ca,
  ];
}

export function circleStrokeWidthExpression(): ExpressionSpecification {
  return [
    'match',
    ['get', 'colour_class'],
    'b1',
    MEASURED_STROKE_WIDTH,
    'b2',
    MEASURED_STROKE_WIDTH,
    'b3',
    MEASURED_STROKE_WIDTH,
    'b4',
    MEASURED_STROKE_WIDTH,
    'b1p',
    PARTLY_VERIFIED_STROKE_WIDTH,
    'b2p',
    PARTLY_VERIFIED_STROKE_WIDTH,
    'b3p',
    PARTLY_VERIFIED_STROKE_WIDTH,
    'b4p',
    PARTLY_VERIFIED_STROKE_WIDTH,
    'ca',
    CA_STROKE_WIDTH,
    CA_STROKE_WIDTH,
  ];
}

export function circleRadiusExpression(): ExpressionSpecification {
  return ['interpolate', ['linear'], ['zoom'], 10, 3, 14, 6, 18, 10];
}
