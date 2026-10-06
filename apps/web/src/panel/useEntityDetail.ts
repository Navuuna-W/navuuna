// TanStack Query hook that pulls one entity's full detail from the API (or the mock).
//
// In production the role is attached to the request by the session cookie — the client
// never sends a `role` query parameter. In fixture mode the mock has no session, so we
// pass the UI role as a query parameter to simulate server-side filtering. The gating
// lives in one place (import.meta.env.VITE_DATA_SOURCE) so there is a single switch to
// flip when A-06 lands.

import { useQuery } from '@tanstack/react-query';
import { apiClient } from '@/api/client';
import { useRole } from '@/ui/useRole';
import type { components } from '@/api/schema';

export type EntityDetail = components['schemas']['EntityDetail'];

const DATA_SOURCE = import.meta.env.VITE_DATA_SOURCE ?? 'fixture';
const IS_FIXTURE = DATA_SOURCE === 'fixture';

export function useEntityDetail(entityId: string | null) {
  // In api mode the role is implicit (session cookie); the hook does not even read it, so
  // changing it in the store cannot invalidate the query. In fixture mode the role is
  // part of the key so switching roles refetches against the mock.
  const role = useRole();
  const roleKey = IS_FIXTURE ? role : null;

  return useQuery({
    queryKey: ['entity', entityId, roleKey],
    enabled: !!entityId,
    queryFn: async (): Promise<EntityDetail> => {
      if (!entityId) throw new Error('entityId required');
      const { data, error, response } = await apiClient.GET('/entities/{id}', {
        params: {
          path: { id: entityId },
          // Fixture mode only. In api mode we send no `role` — the server trusts the
          // session, so a hostile client cannot flip the filter.
          ...(IS_FIXTURE ? { query: { role } as never } : {}),
        },
      });
      if (error || !data) {
        throw new Error(`Failed to load entity ${entityId} (HTTP ${response.status})`);
      }
      return data;
    },
    staleTime: 60_000,
    retry: 1,
  });
}
