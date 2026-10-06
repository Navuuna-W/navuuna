// Unit test for the NFR-02 guard. Covers:
//   - a well-formed scored row passes
//   - a cannot_assess row (score null) passes without coverage/confidence
//   - missing coverage throws
//   - missing confidence throws
//   - missing both throws

import { describe, it, expect } from 'vitest';
import { assertScored, ScoredFieldsMissingError } from './assertScored';

describe('assertScored', () => {
  it('passes when score, coverage and confidence are all present', () => {
    expect(() =>
      assertScored({ variable: 'V1', score: 72, coverage: 0.75, confidence: 0.6 })
    ).not.toThrow();
  });

  it('passes when score is null (cannot_assess)', () => {
    expect(() =>
      assertScored({ variable: 'V1', score: null, coverage: null, confidence: null })
    ).not.toThrow();
  });

  it('throws when coverage is missing but a score is present', () => {
    expect(() =>
      assertScored({ variable: 'V2', score: 55, coverage: null, confidence: 0.7 })
    ).toThrowError(ScoredFieldsMissingError);
  });

  it('throws when confidence is missing but a score is present', () => {
    expect(() =>
      assertScored({ variable: 'V3', score: 55, coverage: 0.75, confidence: undefined })
    ).toThrowError(ScoredFieldsMissingError);
  });

  it('throws with reason "missing_both" when neither coverage nor confidence is a number', () => {
    try {
      assertScored({ variable: 'V4', score: 40, coverage: null, confidence: 'low' });
      throw new Error('should have thrown');
    } catch (err) {
      expect(err).toBeInstanceOf(ScoredFieldsMissingError);
      expect((err as ScoredFieldsMissingError).reason).toBe('missing_both');
      expect((err as ScoredFieldsMissingError).variable).toBe('V4');
    }
  });
});
