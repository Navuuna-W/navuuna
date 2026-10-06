// Root component. The illustrative-data banner is first in the tree so every screen
// inherits it. The map, panel and findings land under the main region in later steps.

import { IllustrativeBanner } from '@/ui/IllustrativeBanner';

export function App() {
  return (
    <div className="flex min-h-screen flex-col">
      <IllustrativeBanner />
      <main className="flex flex-1 items-center justify-center p-8">
        <div className="max-w-xl text-center">
          <h1 className="text-2xl font-semibold">Navuuna</h1>
          <p className="mt-2 text-sm text-neutral-600">
            Spatial intelligence for Nairobi. The map, panel and findings land in the next steps.
          </p>
        </div>
      </main>
    </div>
  );
}
