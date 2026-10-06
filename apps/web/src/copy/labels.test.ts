// Confidence word thresholds are a user-visible rule and must match CLAUDE.md exactly
// (low < 0.4, medium 0.4–0.7, good ≥ 0.7).

import { describe, it, expect } from 'vitest';
import { confidenceWord } from './labels';

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
