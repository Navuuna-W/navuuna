// TanStack Query hook that pulls one entity's full detail from the API (or the mock) and
// hands it to the panel. Keyed on (entityId, role) so a role switch invalidates the view.

import { useQuery } from '@tanstack/react-query';
import { apiClient } from '@/api/client';
import { useRole } from '@/ui/useRole';
import type { components } from '@/api/schema';

export type EntityDetail = components['schemas']['EntityDetail'];

export function useEntityDetail(entityId: string | null) {
  const role = useRole();
  return useQuery({
    queryKey: ['entity', entityId, role],
    enabled: !!entityId,
    queryFn: async (): Promise<EntityDetail> => {
      if (!entityId) throw new Error('entityId required');
      const { data, error, response } = await apiClient.GET('/entities/{id}', {
        params: { path: { id: entityId }, query: { role } as never },
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
