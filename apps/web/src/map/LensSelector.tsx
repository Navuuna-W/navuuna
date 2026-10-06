// Lens selector in the header. v1 ships one lens (county_planner); the selector is here
// so the UI pattern is in place when Devyan adds more lens JSONs.

import { useLens } from './useLens';

const LENSES = [{ id: 'county_planner', name: 'County planner' }] as const;

export function LensSelector() {
  const { lensId, setLens } = useLens();
  return (
    <div className="flex items-center gap-2 text-sm">
      <label htmlFor="lens-selector" className="text-neutral-600">
        Lens
      </label>
      <select
        id="lens-selector"
        value={lensId}
        onChange={(e) => setLens(e.target.value)}
        className="rounded border border-neutral-300 bg-white px-2 py-1 text-sm"
      >
        {LENSES.map((l) => (
          <option key={l.id} value={l.id}>
            {l.name}
          </option>
        ))}
      </select>
    </div>
  );
}
