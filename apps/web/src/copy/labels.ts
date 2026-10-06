// Every on-screen string that must agree with docs/CONTEXT.md (DEC-20) lives here.
// A wording change is a one-file change — never scatter copy across components.

export const VARIABLE_LABELS = {
  V1: 'Is it working?',
  V2: 'Does the record match?',
  V3: 'Which way is it going?',
  V4: 'Will its inputs hold?',
  V5: 'Can people reach it?',
} as const;

export const CONFIDENCE_LOW = 0.4;
export const CONFIDENCE_GOOD = 0.7;

export function confidenceWord(confidence: number): 'low' | 'medium' | 'good' {
  if (confidence < CONFIDENCE_LOW) return 'low';
  if (confidence < CONFIDENCE_GOOD) return 'medium';
  return 'good';
}

export const BADGE = {
  partly_verified: 'Partly verified',
  cannot_assess: 'Cannot assess',
} as const;

export const EMPTY = {
  cannot_assess_variable: 'Not enough data to assess this yet',
  not_measured_prefix: 'Not measured — ',
  no_findings_provisional: 'No findings are raised for a location we could not verify.',
} as const;

export const ERROR = {
  missing_coverage_or_confidence:
    'This score could not be shown because its coverage or confidence is missing.',
  entity_fetch_failed: 'The entity could not be loaded.',
} as const;

export const ATTRIBUTION =
  'Contains modified Copernicus Sentinel data 2026 · © OpenStreetMap contributors · Digital Earth Africa · GRID3';
