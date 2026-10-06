// Merge-blocker: in api mode (VITE_DATA_SOURCE != 'fixture'), the client sends NO `role`
// query parameter on /entities/{id}. The server filters held findings by the session's
// role (A-19, K-11); a client-chosen role would be a hole in that rule.
//
// Note: this test picks up the IS_FIXTURE flag from import.meta.env at module load, so
// we have to isolate the fixture-mode case into its own test file (useEntityDetail.test
// would have cached the module). This file lives alongside and runs independently.

import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { renderHook, waitFor } from '@testing-library/react';
import { QueryClient, QueryClientProvider } from '@tanstack/react-query';
import { MemoryRouter } from 'react-router-dom';
import type { ReactNode } from 'react';
import { useRoleStore } from '@/ui/useRole';

const getSpy = vi.fn(async () => ({
  data: {
    id: 'wp-123',
    entity_type: 'water_point',
    module: 'water',
    name: 'test',
    ward: 'Westlands',
    gate_status: 'measured' as const,
    last_observed_at: null,
    last_computed_at: '2026-10-05T16:00:00Z',
    variables: [],
    sub_variables: [],
    findings: [],
    attribution: 'synthetic',
  },
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
  vi.resetModules();
  vi.stubEnv('VITE_DATA_SOURCE', 'api');
});

afterEach(() => {
  vi.unstubAllEnvs();
});

function wrapper({ children }: { children: ReactNode }) {
  const client = new QueryClient({ defaultOptions: { queries: { retry: false, gcTime: 0 } } });
  return (
    <MemoryRouter>
      <QueryClientProvider client={client}>{children}</QueryClientProvider>
    </MemoryRouter>
  );
}

describe('useEntityDetail — api mode', () => {
  it('never sends `role` as a query parameter', async () => {
    // Dynamic import after stubEnv so the module reads the stub.
    const { useEntityDetail } = await import('./useEntityDetail');
    useRoleStore.setState({ role: 'analyst' });

    const { result } = renderHook(() => useEntityDetail('wp-123'), { wrapper });
    await waitFor(() => expect(result.current.isSuccess).toBe(true));

    expect(getSpy).toHaveBeenCalledTimes(1);
    const [, options] = getSpy.mock.calls[0] as unknown as [
      string,
      { params?: { query?: unknown } },
    ];
    expect(options?.params ?? {}).not.toHaveProperty('query');
  });

  it('does not refetch when the UI role changes (role is session-side in api mode)', async () => {
    const { useEntityDetail } = await import('./useEntityDetail');
    useRoleStore.setState({ role: 'viewer' });

    const { result, rerender } = renderHook(() => useEntityDetail('wp-123'), { wrapper });
    await waitFor(() => expect(result.current.isSuccess).toBe(true));
    const callsBefore = getSpy.mock.calls.length;

    useRoleStore.setState({ role: 'analyst' });
    rerender();

    // Give any pending microtasks a chance to settle.
    await Promise.resolve();
    expect(getSpy.mock.calls.length).toBe(callsBefore);
  });
});
