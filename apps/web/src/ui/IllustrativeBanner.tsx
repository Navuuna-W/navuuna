// Persistent, non-dismissable banner shown whenever the app is wired to fixtures instead
// of a real API. Our product's promise is honesty: if the data is illustrative, every user
// must know, on every screen, without a dismiss button.
//
// Shown whenever VITE_DATA_SOURCE is unset or equals 'fixture'. Set VITE_DATA_SOURCE=api
// (or anything else) when wiring to Laravel to hide it.

export function IllustrativeBanner() {
  const source = import.meta.env.VITE_DATA_SOURCE ?? 'fixture';
  if (source !== 'fixture') return null;

  return (
    <div
      role="status"
      aria-live="polite"
      className="sticky top-0 z-50 w-full bg-amber-200 py-2 text-center text-sm font-medium text-amber-950 shadow-sm"
    >
      <span aria-hidden="true" className="mr-2">
        ⚠
      </span>
      Prototype — illustrative data, not real measurements
    </div>
  );
}
