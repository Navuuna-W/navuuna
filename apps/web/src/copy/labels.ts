// Every on-screen string that must agree with docs/CONTEXT.md (DEC-20) lives here.
// A wording change is a one-file change — never scatter copy across components.
//
// Source of truth for variable labels, tooltips, panel name, and finding status words:
//   origin/docs/docs-context-glossary:docs/CONTEXT.md
//   commit 31d682e54e35836d46ae7dedc58893c9df629b49
//   (owner: Devyan; D-09; DEC-20)
//
// Deviations kept until the open questions in apps/web/README.md close:
//   - BADGE.partly_verified stays "Partly verified" (PRD §19.1 / CLAUDE.md) rather than
//     switching to CONTEXT.md's "Provisional" — open question (b) in README.

export const VARIABLE_LABELS = {
  V1: 'Activity',
  V2: 'Record vs reality',
  V3: 'Momentum',
  V4: 'Resource security',
  V5: 'Access',
} as const;

// Tooltip wording for each variable — the question CONTEXT.md stores alongside the label.
// Rendered on hover and read aloud by screen readers; never a bare number.
export const VARIABLE_TOOLTIPS = {
  V1: 'Is this entity working, and how hard?',
  V2: 'Does the record match what we observe?',
  V3: 'Which direction, how fast, how reliably?',
  V4: 'Are the inputs it depends on dependable?',
  V5: 'Can people reach it, dependably, at what cost?',
} as const;

// Panel name — DEC-20 says one name for the entity panel across all four entity types.
export const PANEL_NAME = 'Passport';

export const CONFIDENCE_LOW = 0.4;
export const CONFIDENCE_GOOD = 0.7;

export function confidenceWord(confidence: number): 'low' | 'medium' | 'good' {
  if (confidence < CONFIDENCE_LOW) return 'low';
  if (confidence < CONFIDENCE_GOOD) return 'medium';
  return 'good';
}

export const BADGE = {
  // Kept as 'Partly verified' pending README open question (b) — CONTEXT.md uses
  // 'Provisional'.
  partly_verified: 'Partly verified',
  cannot_assess: 'Cannot assess',
} as const;

// Finding status words — analyst-only labels where marked. Viewers never see these for
// held / explanation_checked rows because the server strips them (A-19, DEC-08).
export const FINDING_STATUS = {
  held: 'Under review (analysts only)',
  explanation_checked: 'Explanation checked (analysts only)',
  published: 'Published',
  dismissed: 'Dismissed (analysts only)',
  contested: 'Contested',
  resolved: 'Resolved',
} as const;

// Hover text for each finding state, from CONTEXT.md § 4.
export const FINDING_HOVER = {
  held: 'Never shown to a non-analyst in any form',
  contested: 'Someone has challenged this finding.',
  resolved: 'The resolution note',
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
