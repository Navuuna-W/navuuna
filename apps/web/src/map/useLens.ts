// Lens state lives on the URL (`?lens=`), so a lens choice survives refresh and share.
// Default is 'county_planner' — the only lens v1 ships.

import { useCallback, useMemo } from 'react';
import { useSearchParams } from 'react-router-dom';

export const DEFAULT_LENS_ID = 'county_planner';

export function useLens(): {
  lensId: string;
  setLens: (id: string) => void;
} {
  const [params, setParams] = useSearchParams();
  const lensId = useMemo(() => params.get('lens') ?? DEFAULT_LENS_ID, [params]);

  const setLens = useCallback(
    (id: string) => {
      const next = new URLSearchParams(params);
      if (id === DEFAULT_LENS_ID) {
        next.delete('lens');
      } else {
        next.set('lens', id);
      }
      setParams(next, { replace: false });
    },
    [params, setParams]
  );

  return { lensId, setLens };
}
