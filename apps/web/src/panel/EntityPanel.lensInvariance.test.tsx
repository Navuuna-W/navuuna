// Panel invariance (A-15, US-301 P0 check): switching the lens recolours the map but
// NEVER changes the panel's variable scores. The lens parameter must not appear in the
// useEntityDetail query key, so a lens change does not trigger a refetch, and the UI
// shows the exact same numbers either side of the switch.

import { describe, it, expect, beforeEach, vi } from 'vitest';
import { render, screen, within, waitFor } from '@testing-library/react';
import { QueryClient, QueryClientProvider } from '@tanstack/react-query';
import { MemoryRouter, Route, Routes, useNavigate } from 'react-router-dom';
import { useEffect } from 'react';
import { EntityPanel } from './EntityPanel';
import { useRoleStore } from '@/ui/useRole';
import type { components } from '@/api/schema';

type EntityDetail = components['schemas']['EntityDetail'];

function makeEntity(): EntityDetail {
  return {
    id: 'wp-123',
    entity_type: 'water_point',
    module: 'water',
    name: 'Lens-invariance water point',
    ward: 'Westlands',
    gate_status: 'measured',
    last_observed_at: '2026-10-04T00:00:00Z',
    last_computed_at: '2026-10-05T16:00:00Z',
    variables: [
      {
        variable: 'V1',
        name: 'V1',
        score: 72,
        coverage: 0.75,
        confidence: 0.65,
        status: 'measured',
        gate_status: 'measured',
        measured_count: 3,
        total_count: 4,
        computed_at: '2026-10-05T16:00:00Z',
      },
      {
        variable: 'V2',
        name: 'V2',
        score: 55,
        coverage: 0.75,
        confidence: 0.6,
        status: 'measured',
        gate_status: 'measured',
        measured_count: 3,
        total_count: 4,
        computed_at: '2026-10-05T16:00:00Z',
      },
      {
        variable: 'V3',
        name: 'V3',
        score: 48,
        coverage: 0.5,
        confidence: 0.55,
        status: 'measured',
        gate_status: 'measured',
        measured_count: 3,
        total_count: 6,
        computed_at: '2026-10-05T16:00:00Z',
      },
      {
        variable: 'V4',
        name: 'V4',
        score: 60,
        coverage: 0.83,
        confidence: 0.7,
        status: 'measured',
        gate_status: 'measured',
        measured_count: 5,
        total_count: 6,
        computed_at: '2026-10-05T16:00:00Z',
      },
      {
        variable: 'V5',
        name: 'V5',
        score: 44,
        coverage: 0.5,
        confidence: 0.5,
        status: 'measured',
        gate_status: 'measured',
        measured_count: 3,
        total_count: 6,
        computed_at: '2026-10-05T16:00:00Z',
      },
    ],
    sub_variables: [],
    findings: [],
    attribution: 'synthetic',
  };
}

const getSpy = vi.fn(async () => ({
  data: makeEntity(),
  error: null,
  response: { status: 200 },
}));

vi.mock('@/api/client', () => ({
  apiClient: {
    GET: (...args: unknown[]) => getSpy(...(args as [])),
  },
}));

beforeEach(() => {
  getSpy.mockClear();
  useRoleStore.setState({ role: 'viewer' });
});

function LensSwitcher({ to }: { to: string }) {
  const navigate = useNavigate();
  useEffect(() => {
    navigate(to);
  }, [navigate, to]);
  return null;
}

function Harness({ queryClient, lensPath }: { queryClient: QueryClient; lensPath: string }) {
  return (
    <QueryClientProvider client={queryClient}>
      <MemoryRouter initialEntries={['/?entity=wp-123']}>
        <Routes>
          <Route
            path="*"
            element={
              <>
                <EntityPanel />
                <LensSwitcher to={lensPath} />
              </>
            }
          />
        </Routes>
      </MemoryRouter>
    </QueryClientProvider>
  );
}

describe('EntityPanel — lens-switch invariance (A-15, US-301)', () => {
  it('does not refetch when the lens changes, and panel scores stay identical', async () => {
    const client = new QueryClient({ defaultOptions: { queries: { retry: false, gcTime: 0 } } });
    const { rerender } = render(
      <Harness queryClient={client} lensPath="/?entity=wp-123&lens=county_planner" />
    );

    await waitFor(() =>
      expect(screen.getByText('Lens-invariance water point')).toBeInTheDocument()
    );

    const panel = screen.getByRole('complementary');
    // Capture the five variable scores on first render.
    const scoresBefore = within(panel)
      .getAllByLabelText('Score')
      .map((n) => n.textContent?.trim());
    expect(scoresBefore).toEqual(['72', '55', '48', '60', '44']);

    const getCallsBefore = getSpy.mock.calls.length;

    // Switch to a different lens on the URL.
    rerender(<Harness queryClient={client} lensPath="/?entity=wp-123&lens=another_lens" />);

    // Panel scores should remain identical.
    await waitFor(() =>
      expect(screen.getByText('Lens-invariance water point')).toBeInTheDocument()
    );
    const scoresAfter = within(screen.getByRole('complementary'))
      .getAllByLabelText('Score')
      .map((n) => n.textContent?.trim());
    expect(scoresAfter).toEqual(scoresBefore);

    // And no additional entity fetch should have been triggered by the lens change.
    expect(getSpy.mock.calls.length).toBe(getCallsBefore);
  });
});
