// URL state helper. The selected entity lives in the URL (?entity=wp-001), so a pasted
// link restores the view (A-14 rule). Reads/writes the ?entity query param.

import { useCallback, useMemo } from 'react';
import { useSearchParams } from 'react-router-dom';

export function useSelectedEntity(): {
  entityId: string | null;
  openEntity: (id: string) => void;
  closeEntity: () => void;
} {
  const [params, setParams] = useSearchParams();
  const entityId = useMemo(() => params.get('entity'), [params]);

  const openEntity = useCallback(
    (id: string) => {
      const next = new URLSearchParams(params);
      next.set('entity', id);
      setParams(next, { replace: false });
    },
    [params, setParams]
  );

  const closeEntity = useCallback(() => {
    const next = new URLSearchParams(params);
    next.delete('entity');
    setParams(next, { replace: false });
  }, [params, setParams]);

  return { entityId, openEntity, closeEntity };
}
