// Composes the main screen (S1 layout): header, left rail placeholder, the map, the panel
// (when ?entity is set) and the permanent attribution footer. Keeps components simple and
// composable; the real header + rail land in later steps.

import { MapView } from '@/map/MapView';
import { EntityPanel } from '@/panel/EntityPanel';
import { AttributionFooter } from '@/ui/AttributionFooter';
import { useLens } from '@/map/useLens';

export function MapRoute() {
  const { lensId } = useLens();
  return (
    <div className="flex min-h-0 flex-1 flex-col">
      <div className="flex min-h-0 flex-1">
        <nav
          aria-label="Filters and legend"
          className="hidden w-48 shrink-0 border-r border-neutral-200 bg-neutral-50 p-3 text-xs text-neutral-500 md:block"
        >
          <div className="font-semibold">Filters</div>
          <p className="mt-1">Lens: {lensId.replaceAll('_', ' ')}.</p>
          <p className="mt-2 text-[11px]">Module and entity-type filters land with A-14.</p>
        </nav>

        <div className="relative flex-1">
          <MapView lens={lensId} />
        </div>

        <EntityPanel />
      </div>

      <AttributionFooter />
    </div>
  );
}
