// Which finding states may appear in front of a non-analyst, in one place.
//
// The real API decides this server-side (FlagPolicy reading Flag::PUBLIC_STATES); the dev
// mock in vite-plugins/mockServer.ts calls findingsVisibleTo so fixture mode strips exactly
// the same rows as api mode. The client never re-filters: it renders what it was given.
//
// Why this lives in src/ and not fixtures/: fixtures are Node-only and src/ cannot import
// from them (project boundary), but both the mock and the panel tests must read one list.
// The fixture generator and the mock import it from here, as fixtures/subVariables.ts does.

import type { components } from '../api/schema';

export type FindingState = components['schemas']['FindingSummary']['state'];

/** Who is asking. An admin sees everything an analyst sees. */
export type FindingAudience = 'viewer' | 'analyst' | 'admin';

// Public = safe in front of a non-analyst. Mirrors Flag::PUBLIC_STATES in apps/api.
// `contested` is deliberately absent: while a contest is open the finding is hidden from
// viewers, exactly as `held` is (ADR-013). Every other state is analyst-only (DEC-08, A-19).
export const PUBLIC_FINDING_STATES = [
  'published',
  'resolved',
] as const satisfies readonly FindingState[];

/**
 * True when a non-analyst may see a finding in this state.
 * Implements ADR-013 and DEC-08 — only published and resolved findings are public.
 */
export function isPublicFindingState(state: FindingState): boolean {
  const publicStates: readonly string[] = PUBLIC_FINDING_STATES;
  return publicStates.includes(state);
}

/**
 * Drops every finding the audience may not see. An analyst or admin gets the list back
 * unchanged; a viewer gets the public states only.
 *
 * This is an allow-list, not a deny-list: a state added to the contract later stays hidden
 * from viewers until someone deliberately adds it to PUBLIC_FINDING_STATES. The C10 leak
 * happened the other way round — the mock named the states to hide and missed `contested`.
 */
export function findingsVisibleTo<FindingShape extends { state: FindingState }>(
  findings: readonly FindingShape[],
  audience: FindingAudience
): FindingShape[] {
  if (audience !== 'viewer') return [...findings];
  return findings.filter((finding) => isPublicFindingState(finding.state));
}
