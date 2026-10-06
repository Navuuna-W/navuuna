// Root component. For now this is a scaffolding placeholder — the real screens S1–S8 land
// in later steps (map, panel, findings, review). It is deliberately plain so a new developer
// can see where things attach.

export function App() {
  return (
    <main className="flex min-h-screen items-center justify-center p-8">
      <div className="max-w-xl text-center">
        <h1 className="text-2xl font-semibold">Navuuna</h1>
        <p className="mt-2 text-sm text-neutral-600">
          Spatial intelligence for Nairobi. The map, panel and findings land in the next steps.
        </p>
      </div>
    </main>
  );
}
