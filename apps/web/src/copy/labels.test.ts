// Confidence word thresholds are a user-visible rule and must match CLAUDE.md exactly
// (low < 0.4, medium 0.4–0.7, good ≥ 0.7). Variable labels and panel name must match
// CONTEXT.md verbatim (DEC-20).

import { describe, it, expect } from 'vitest';
import {
  confidenceWord,
  VARIABLE_LABELS,
  VARIABLE_TOOLTIPS,
  PANEL_NAME,
  FINDING_STATUS,
} from './labels';

describe('confidenceWord', () => {
  it('calls 0.0 low', () => expect(confidenceWord(0)).toBe('low'));
  it('calls 0.39 low', () => expect(confidenceWord(0.39)).toBe('low'));
  it('calls 0.40 medium (inclusive on the lower bound)', () =>
    expect(confidenceWord(0.4)).toBe('medium'));
  it('calls 0.69 medium', () => expect(confidenceWord(0.69)).toBe('medium'));
  it('calls 0.70 good (inclusive on the lower bound)', () =>
    expect(confidenceWord(0.7)).toBe('good'));
  it('calls 1.0 good', () => expect(confidenceWord(1)).toBe('good'));
});

describe('CONTEXT.md labels', () => {
  it('variable labels are the CONTEXT.md short noun forms', () => {
    expect(VARIABLE_LABELS.V1).toBe('Activity');
    expect(VARIABLE_LABELS.V2).toBe('Record vs reality');
    expect(VARIABLE_LABELS.V3).toBe('Momentum');
    expect(VARIABLE_LABELS.V4).toBe('Resource security');
    expect(VARIABLE_LABELS.V5).toBe('Access');
  });

  it('variable tooltips are the questions', () => {
    expect(VARIABLE_TOOLTIPS.V1).toMatch(/working/);
    expect(VARIABLE_TOOLTIPS.V2).toMatch(/record/);
    expect(VARIABLE_TOOLTIPS.V5).toMatch(/reach/);
  });

  it('panel is named Passport (DEC-20)', () => {
    expect(PANEL_NAME).toBe('Passport');
  });

  it('held and dismissed finding statuses are explicitly analyst-only', () => {
    expect(FINDING_STATUS.held).toMatch(/analysts only/);
    expect(FINDING_STATUS.dismissed).toMatch(/analysts only/);
  });

  it('contested finding carries CONTEXT.md copy semantically', () => {
    expect(FINDING_STATUS.contested).toBe('Contested');
  });
});
