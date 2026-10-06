// Merge-blocker test: a Viewer must never see any trace of a held finding — no row, no
// count, no banner, no amber styling. The mock enforces this server-side by stripping
// held + explanation_checked findings from the /entities/{id}?role=viewer payload. This
// test proves the client renders only what it was given, and that the finding section
// disappears when all findings have been stripped.
//
// The sibling test with role=analyst proves the held row DOES render with its banner.

import { describe, it, expect, beforeEach, vi } from 'vitest';
import { render, screen, waitFor } from '@testing-library/react';
import { QueryClient, QueryClientProvider } from '@tanstack/react-query';
import { MemoryRouter } from 'react-router-dom';
import { EntityPanel } from './EntityPanel';
import { useRoleStore } from '@/ui/useRole';
import type { components } from '@/api/schema';

type EntityDetail = components['schemas']['EntityDetail'];

const HELD_FINDING: EntityDetail['findings'][number] = {
  id: 'wp-999-finding-held',
  sub_variable: '2.4',
  state: 'held',
  severity: 'medium',
  detected_at: '2026-10-04T09:00:00Z',
  record_says: 'Register says operational.',
  we_observe: 'Observers report intermittent.',
  gap_summary: 'Observed status differs from the recorded status.',
  published_at: null,
};

function makeEntity(withHeld: boolean): EntityDetail {
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
    findings: withHeld ? [HELD_FINDING] : [],
    attribution: 'synthetic',
  };
}

// Mock the apiClient directly so this test doesn't depend on jsdom's fetch availability.
// The mock mirrors the mock-server behaviour: analyst sees held findings, viewer does not.
vi.mock('@/api/client', () => ({
  apiClient: {
    GET: vi.fn(async (_path: string, options: { params?: { query?: { role?: string } } }) => {
      const role = options.params?.query?.role;
      const data = makeEntity(role === 'analyst');
      return { data, error: null, response: { status: 200 } };
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

describe('EntityPanel — held-finding invisibility (A-19)', () => {
  it('shows no trace of a held finding when role is Viewer', async () => {
    useRoleStore.setState({ role: 'viewer' });
    render(<Harness queryClient={freshClient()} />);

    await waitFor(() => expect(screen.getByText('Test water point')).toBeInTheDocument());

    expect(screen.queryByText(/Not visible to other users/i)).not.toBeInTheDocument();
    expect(
      screen.queryByText(/Observed status differs from the recorded status/i)
    ).not.toBeInTheDocument();
    expect(screen.queryByRole('heading', { name: /Findings/ })).not.toBeInTheDocument();
  });

  it('shows the held finding with its "Not visible" banner when role is Analyst', async () => {
    useRoleStore.setState({ role: 'analyst' });
    render(<Harness queryClient={freshClient()} />);

    await waitFor(() => expect(screen.getByText('Test water point')).toBeInTheDocument());

    expect(screen.getByText(/Not visible to other users until published/i)).toBeInTheDocument();
    expect(
      screen.getByText(/Observed status differs from the recorded status/i)
    ).toBeInTheDocument();
  });
});
