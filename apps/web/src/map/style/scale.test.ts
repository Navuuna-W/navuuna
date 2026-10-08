// B1 invariants on circle expressions, plus style-spec validation.
// Separated from the road-layer tests (PR 1a-ii) so this PR's diff stays small.

import { describe, it, expect } from 'vitest';
import {
  createExpression,
  validateStyleMin,
  type LayerSpecification,
  type StylePropertySpecification,
  type StyleSpecification,
} from '@maplibre/maplibre-gl-style-spec';
import {
  ALL_COLOUR_CLASSES,
  CA_STROKE_WIDTH,
  COLOUR_FOR,
  MEASURED_STROKE_COLOR,
  circleColorExpression,
  circleRadiusExpression,
  circleStrokeColorExpression,
  circleStrokeWidthExpression,
} from './scale';
import { buildBaseStyle } from './baseStyle';

const UNKNOWN = 'nope-does-not-exist';
const CLASSES_PLUS_UNKNOWN: readonly string[] = [...ALL_COLOUR_CLASSES, UNKNOWN];

const COLOR_SPEC: StylePropertySpecification = {
  type: 'color',
  'property-type': 'data-driven',
  expression: { interpolated: true, parameters: ['zoom', 'feature'] },
  transition: true,
} as unknown as StylePropertySpecification;
const NUMBER_SPEC: StylePropertySpecification = {
  ...(COLOR_SPEC as unknown as Record<string, unknown>),
  type: 'number',
} as unknown as StylePropertySpecification;

function compile(expr: unknown, spec: StylePropertySpecification) {
  const r = createExpression(expr, spec);
  if (r.result !== 'success') throw new Error(r.value.map((e) => e.message).join('; '));
  return r.value;
}
function evalColor(expr: unknown, cc: string): { r: number; g: number; b: number; a: number } {
  return compile(expr, COLOR_SPEC).evaluate({ zoom: 12 }, { properties: { colour_class: cc } }) as {
    r: number;
    g: number;
    b: number;
    a: number;
  };
}
function evalNumber(expr: unknown, cc: string): number {
  return compile(expr, NUMBER_SPEC).evaluate({ zoom: 12 }, { properties: { colour_class: cc } });
}

describe('scale table', () => {
  it('ALL_COLOUR_CLASSES is complete and every colour is a valid hex', () => {
    expect(new Set(ALL_COLOUR_CLASSES)).toEqual(
      new Set(['b1', 'b2', 'b3', 'b4', 'b1p', 'b2p', 'b3p', 'b4p', 'ca'])
    );
    for (const c of ALL_COLOUR_CLASSES) {
      expect(COLOUR_FOR[c]).toMatch(/^#[0-9A-Fa-f]{6}$/);
    }
  });
});

describe('circles — stroke always visible, unknown class falls back to ca (B1)', () => {
  it('stroke alpha > 0 AND stroke width > 0 for every class, including unknown', () => {
    const col = circleStrokeColorExpression();
    const wid = circleStrokeWidthExpression();
    for (const cc of CLASSES_PLUS_UNKNOWN) {
      expect(evalColor(col, cc).a, `stroke alpha for '${cc}'`).toBeGreaterThan(0);
      expect(evalNumber(wid, cc), `stroke width for '${cc}'`).toBeGreaterThan(0);
    }
  });

  it('unknown class uses ca width and ca stroke colour — not a band style (B1)', () => {
    expect(evalNumber(circleStrokeWidthExpression(), UNKNOWN)).toBe(CA_STROKE_WIDTH);
    const r = evalColor(circleStrokeColorExpression(), UNKNOWN).r;
    const measuredR = parseInt(MEASURED_STROKE_COLOR.slice(1, 3), 16) / 0xff;
    expect(Math.abs(r - measuredR)).toBeGreaterThan(0.1);
  });

  it('circleRadiusExpression returns > 0 across zoom', () => {
    const compiled = compile(circleRadiusExpression(), NUMBER_SPEC);
    for (const z of [10, 12, 14, 18]) {
      expect(
        compiled.evaluate({ zoom: z }, { properties: { colour_class: 'b1' } })
      ).toBeGreaterThan(0);
    }
  });
});

describe('style-spec validation', () => {
  it('validateStyleMin accepts the base style + a circle layer using the circle expressions', () => {
    const base = buildBaseStyle() as unknown as StyleSpecification;
    // Inline water-points layer definition proves the three circle expressions pass
    // style-spec validation against a real circle-layer paint block. PR 1a-ii extends
    // this with the three road layers.
    const circleLayer: LayerSpecification = {
      id: 'test-water-points',
      type: 'circle',
      source: 'entities-src',
      'source-layer': 'entities',
      filter: ['==', ['get', 'entity_type'], 'water_point'],
      paint: {
        'circle-radius': circleRadiusExpression(),
        'circle-color': circleColorExpression(),
        'circle-stroke-color': circleStrokeColorExpression(),
        'circle-stroke-width': circleStrokeWidthExpression(),
        'circle-opacity': 0.95,
      },
    };
    const merged: StyleSpecification = {
      ...base,
      sources: {
        ...base.sources,
        'entities-src': {
          type: 'vector',
          tiles: ['http://localhost/tiles/{z}/{x}/{y}.mvt'],
          minzoom: 0,
          maxzoom: 18,
        },
      },
      layers: [...base.layers, circleLayer],
    };
    const errors = validateStyleMin(merged);
    expect(errors, errors.map((e) => `  - ${e.message}`).join('\n')).toHaveLength(0);
  });
});
