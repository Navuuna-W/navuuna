// NFR-02 guard: nothing with a score ever reaches the UI without coverage AND confidence
// beside it. If the API ever omits either, we show an error state — never a bare number.
//
// This is a defence-in-depth check: the serializer on the API side has the same rule
// (A-07). The frontend guard protects us when the API is a draft (today's state) or when
// the serializer regresses.

export class ScoredFieldsMissingError extends Error {
  constructor(
    public readonly variable: string,
    public readonly reason: 'missing_coverage' | 'missing_confidence' | 'missing_both'
  ) {
    super(`Variable ${variable}: ${reason.replaceAll('_', ' ')}`);
    this.name = 'ScoredFieldsMissingError';
  }
}

interface ScoredLike {
  variable: string;
  score: number | null;
  coverage: unknown;
  confidence: unknown;
}

/**
 * Throws when `score` is not null but `coverage` or `confidence` is missing.
 * A score of `null` (cannot_assess) is allowed without either — there is nothing to show.
 */
export function assertScored(v: ScoredLike): void {
  if (v.score === null || v.score === undefined) return;

  const coverageOk = typeof v.coverage === 'number';
  const confidenceOk = typeof v.confidence === 'number';

  if (!coverageOk && !confidenceOk) {
    throw new ScoredFieldsMissingError(v.variable, 'missing_both');
  }
  if (!coverageOk) {
    throw new ScoredFieldsMissingError(v.variable, 'missing_coverage');
  }
  if (!confidenceOk) {
    throw new ScoredFieldsMissingError(v.variable, 'missing_confidence');
  }
}
