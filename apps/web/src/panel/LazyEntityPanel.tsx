// Lazy wrapper around EntityPanel. The first map paint ships no panel code; the panel
// chunk loads on demand when the user clicks a point (?entity= appears on the URL).
// MapLibre stays in the main chunk so the map renders on the first paint.

import { Suspense, lazy } from 'react';
import { useSelectedEntity } from './useSelectedEntity';
import { PANEL_NAME } from '@/copy/labels';

const EntityPanel = lazy(() => import('./EntityPanel').then((m) => ({ default: m.EntityPanel })));

export function LazyEntityPanel() {
  const { entityId } = useSelectedEntity();
  if (!entityId) return null;
  return (
    <Suspense
      fallback={
        <aside
          aria-label={PANEL_NAME}
          aria-busy="true"
          className="flex h-full w-full max-w-md flex-col border-l border-neutral-200 bg-white p-3 text-sm text-neutral-600 shadow-lg"
        >
          Loading…
        </aside>
      }
    >
      <EntityPanel />
    </Suspense>
  );
}
