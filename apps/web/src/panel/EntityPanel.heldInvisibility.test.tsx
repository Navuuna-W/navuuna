// Merge-blocker test: a Viewer must never see any trace of a non-public finding — no row,
// no count, no banner, no amber styling. The mock enforces this server-side by keeping only
// published and resolved findings in the /entities/{id}?role=viewer payload. This test
// proves the client renders only what it was given, and that the finding section disappears
// when every finding has been stripped.
//
// The cases run over every analyst-only state rather than just `held`, because C10 was a
// contested finding reaching a viewer through a filter that only named `held` and
// `explanation_checked` (ADR-013, DEC-08, A-19).
//
// The sibling analyst cases prove the held and contested rows DO render, with their banners.

import { describe, it, expect, beforeEach, vi } from 'vitest';
import { render, screen, waitFor } from '@testing-library/react';
import { QueryClient, QueryClientProvider } from '@tanstack/react-query';
import { MemoryRouter } from 'react-router-dom';
import { EntityPanel } from './EntityPanel';
import { useRoleStore } from '@/ui/useRole';
import {
  findingsVisibleTo,
  isPublicFindingState,
  type FindingState,
} from '@/findings/publicFindingStates';
import type { components } from '@/api/schema';

type EntityDetail = components['schemas']['EntityDetail'];
type FindingSummary = EntityDetail['findings'][number];

const ALL_FINDING_STATES = [
  'held',
  'explanation_checked',
  'published',
  'contested',
  'dismissed',
  'resolved',
] as const satisfies readonly FindingState[];

const NON_PUBLIC_STATES = ALL_FINDING_STATES.filter((state) => !isPublicFindingState(state));

const GAP_SUMMARY = 'Observed status differs from the recorded status.';

function makeFinding(state: FindingState): FindingSummary {
  return {
    id: `wp-999-finding-${state}`,
    sub_variable: '2.4',
    state,
    severity: 'medium',
    detected_at: '2026-10-04T09:00:00Z',
    record_says: 'Register says operational.',
    we_observe: 'Observers report intermittent.',
    gap_summary: GAP_SUMMARY,
    published_at: state === 'published' || state === 'contested' ? '2026-10-04T15:00:00Z' : null,
  };
}

function makeEntity(findings: FindingSummary[]): EntityDetail {
  return {
    id: 'wp-999',
    entity_type: 'water_point',
    module: 'water',
    name: 'Test water point',
    ward: 'Starehe',
    gate_status: 'measured',
    last_observed_at: '2026-10-04T00:00:00Z',
    last_computed_at: '2026-10-05T16:00:00Z',
    variables: (['V1', 'V2', 'V3', 'V4', 'V5'] as const).map((v) => ({
      variable: v,
      name: v,
      score: 60,
      coverage: 0.75,
      confidence: 0.65,
      status: 'measured',
      gate_status: 'measured',
      measured_count: 3,
      total_count: 4,
      computed_at: '2026-10-05T16:00:00Z',
    })),
    sub_variables: [],
    findings,
    attribution: 'synthetic',
  };
}

// The finding state the mocked API will put on the entity before filtering. Set per test so
// the apiClient mock stays a single definition.
let stateUnderTest: FindingState = 'held';

// Mock the apiClient directly so this test doesn't depend on jsdom's fetch availability.
// The mock calls the real findingsVisibleTo, so it cannot drift from the mock server: if the
// filter regresses, these tests fail rather than silently passing against their own copy.
vi.mock('@/api/client', () => ({
  apiClient: {
    GET: vi.fn(async (_path: string, options: { params?: { query?: { role?: string } } }) => {
      const role = options.params?.query?.role === 'analyst' ? 'analyst' : 'viewer';
      const findings = findingsVisibleTo([makeFinding(stateUnderTest)], role);
      return { data: makeEntity(findings), error: null, response: { status: 200 } };
    }),
  },
}));

beforeEach(() => {
  vi.clearAllMocks();
});

function Harness({ queryClient }: { queryClient: QueryClient }) {
  return (
    <QueryClientProvider client={queryClient}>
      <MemoryRouter initialEntries={['/?entity=wp-999']}>
        <EntityPanel />
      </MemoryRouter>
    </QueryClientProvider>
  );
}

function freshClient(): QueryClient {
  return new QueryClient({
    defaultOptions: { queries: { retry: false, gcTime: 0 } },
  });
}

describe('EntityPanel — non-public finding invisibility (A-19, DEC-08)', () => {
  it.each(NON_PUBLIC_STATES)(
    'shows no trace of a %s finding when role is Viewer',
    async (state) => {
      stateUnderTest = state;
      useRoleStore.setState({ role: 'viewer' });

      render(<Harness queryClient={freshClient()} />);
      await waitFor(() => expect(screen.getByText('Test water point')).toBeInTheDocument());

      expect(screen.queryByText(/Not visible to other users/i)).not.toBeInTheDocument();
      expect(screen.queryByText(/has challenged this finding/i)).not.toBeInTheDocument();
      expect(screen.queryByText(GAP_SUMMARY)).not.toBeInTheDocument();
      expect(screen.queryByRole('heading', { name: /Findings/ })).not.toBeInTheDocument();
    }
  );

  it('shows the held finding with its "Not visible" banner when role is Analyst', async () => {
    stateUnderTest = 'held';
    useRoleStore.setState({ role: 'analyst' });

    render(<Harness queryClient={freshClient()} />);
    await waitFor(() => expect(screen.getByText('Test water point')).toBeInTheDocument());

    // FindingDetail is a lazy chunk; use findBy to wait for its Suspense resolution.
    expect(
      await screen.findByText(/Not visible to other users until published/i)
    ).toBeInTheDocument();
    expect(await screen.findByText(GAP_SUMMARY)).toBeInTheDocument();
  });

  it('shows the contested finding to an Analyst, who is the only audience for it', async () => {
    stateUnderTest = 'contested';
    useRoleStore.setState({ role: 'analyst' });

    render(<Harness queryClient={freshClient()} />);
    await waitFor(() => expect(screen.getByText('Test water point')).toBeInTheDocument());

    expect(await screen.findByText(/has challenged this finding/i)).toBeInTheDocument();
    expect(await screen.findByText(GAP_SUMMARY)).toBeInTheDocument();
  });

  it('shows a published finding to a Viewer, so the invisibility cases prove something', async () => {
    stateUnderTest = 'published';
    useRoleStore.setState({ role: 'viewer' });

    render(<Harness queryClient={freshClient()} />);
    await waitFor(() => expect(screen.getByText('Test water point')).toBeInTheDocument());

    expect(await screen.findByText(GAP_SUMMARY)).toBeInTheDocument();
  });
});
