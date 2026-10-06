// Every colour_class the mock emits must have a definite colour and a documented role.
// A missing entry here is a shipping bug.

import { describe, it, expect } from 'vitest';
import {
  COLOUR_FOR,
  circleColorExpression,
  circleStrokeColorExpression,
  isCannotAssess,
  isPartlyVerified,
  type ColourClass,
} from './scale';

const ALL_CLASSES: ColourClass[] = ['b1', 'b2', 'b3', 'b4', 'b1p', 'b2p', 'b3p', 'b4p', 'ca'];

describe('score colour scale', () => {
  it('has a colour for every ColourClass', () => {
    for (const c of ALL_CLASSES) {
      expect(COLOUR_FOR[c]).toMatch(/^#[0-9A-Fa-f]{6}$/);
    }
  });

  it('isPartlyVerified detects every "p"-suffixed class', () => {
    for (const c of ALL_CLASSES) {
      expect(isPartlyVerified(c)).toBe(c.endsWith('p'));
    }
  });

  it('isCannotAssess only matches "ca"', () => {
    for (const c of ALL_CLASSES) {
      expect(isCannotAssess(c)).toBe(c === 'ca');
    }
  });

  it('circleColorExpression lists all nine classes', () => {
    const expr = circleColorExpression();
    const text = JSON.stringify(expr);
    for (const c of ALL_CLASSES) {
      expect(text).toContain(`"${c}"`);
    }
  });

  it('circleStrokeColorExpression distinguishes "p" and "ca" from the solid bands', () => {
    const expr = circleStrokeColorExpression();
    const text = JSON.stringify(expr);
    for (const c of ['b1p', 'b2p', 'b3p', 'b4p', 'ca'] as const) {
      expect(text).toContain(`"${c}"`);
    }
  });
});
