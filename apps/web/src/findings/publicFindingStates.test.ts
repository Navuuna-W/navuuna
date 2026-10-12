// Merge-blocker test: the viewer-visible finding states, enumerated.
//
// The C10 leak was a deny-list that named `held` and `explanation_checked` and forgot
// `contested`. These tests walk every state in the contract, so the next state someone adds
// fails here rather than reaching a viewer.

import { describe, it, expect } from 'vitest';
import {
  PUBLIC_FINDING_STATES,
  findingsVisibleTo,
  isPublicFindingState,
  type FindingState,
} from './publicFindingStates';

// Every state in components.schemas.FindingSummary.state — all seven of FlagState. A
// generated type cannot be iterated at runtime, so the states have to be written out; making
// it a Record over the contract union means the contract gaining an eighth state fails to
// compile here instead of quietly passing every test below.
const EVERY_CONTRACT_STATE: Record<FindingState, true> = {
  detected: true,
  held: true,
  explanation_checked: true,
  published: true,
  contested: true,
  dismissed: true,
  resolved: true,
};

// Key order is insertion order for string keys, so this list is stable to assert against.
const ALL_FINDING_STATES = Object.keys(EVERY_CONTRACT_STATE) as FindingState[];

const NON_PUBLIC_STATES = ALL_FINDING_STATES.filter((state) => !isPublicFindingState(state));

function finding(state: FindingState) {
  return { id: `finding-${state}`, state };
}

describe('public finding states (ADR-013, DEC-08, A-19)', () => {
  it('treats only published and resolved as public', () => {
    expect([...PUBLIC_FINDING_STATES]).toEqual(['published', 'resolved']);
  });

  it('treats contested as analyst-only, because a contest hides the finding again', () => {
    expect(isPublicFindingState('contested')).toBe(false);
  });

  it('treats detected as analyst-only, the state the findings engine raises into', () => {
    expect(isPublicFindingState('detected')).toBe(false);
  });

  it('leaves five of the seven contract states analyst-only', () => {
    expect(NON_PUBLIC_STATES).toEqual([
      'detected',
      'held',
      'explanation_checked',
      'contested',
      'dismissed',
    ]);
  });
});

describe('findingsVisibleTo', () => {
  it.each(NON_PUBLIC_STATES)('hides a %s finding from a viewer', (state) => {
    const visible = findingsVisibleTo([finding(state)], 'viewer');

    expect(visible).toEqual([]);
  });

  it.each(PUBLIC_FINDING_STATES)('shows a %s finding to a viewer', (state) => {
    const visible = findingsVisibleTo([finding(state)], 'viewer');

    expect(visible).toEqual([finding(state)]);
  });

  it.each(ALL_FINDING_STATES)('shows a %s finding to an analyst', (state) => {
    const visible = findingsVisibleTo([finding(state)], 'analyst');

    expect(visible).toEqual([finding(state)]);
  });

  it('shows an admin everything an analyst sees', () => {
    const all = ALL_FINDING_STATES.map(finding);

    expect(findingsVisibleTo(all, 'admin')).toEqual(all);
  });

  it('keeps the public rows when a mixed list is filtered for a viewer', () => {
    const mixed = ALL_FINDING_STATES.map(finding);

    const visible = findingsVisibleTo(mixed, 'viewer');

    expect(visible.map((f) => f.state)).toEqual(['published', 'resolved']);
  });
});
