// The right-hand entity panel (S2). Opens when ?entity is set on the URL; closes by Esc,
// the × button, or a map click (map wires closeEntity).
//
// Rules enforced here:
//   - NFR-02: every variable passes through assertScored. A failure replaces that row with
//     an error state — never a bare score.
//   - Held and explanation_checked findings must never render for a Viewer: the mock has
//     already filtered them server-side (DEC-08), and the UI only renders what it got.

import { useEffect } from 'react';
import { useSelectedEntity } from './useSelectedEntity';
import { useEntityDetail } from './useEntityDetail';
import { VariableRow } from './VariableRow';
import { assertScored, ScoredFieldsMissingError } from '@/api/assertScored';
import { ERROR, PANEL_NAME } from '@/copy/labels';
import { Suspense, lazy } from 'react';

// Lazy sub-chunk: nothing in the findings/ folder is loaded until the first entity whose
// payload contains published findings reaches the UI. Keeps the panel chunk itself small.
const FindingDetail = lazy(() =>
  import('@/findings/FindingDetail').then((m) => ({ default: m.FindingDetail }))
);

export function EntityPanel() {
  const { entityId, closeEntity } = useSelectedEntity();
  const query = useEntityDetail(entityId);

  // Esc to close.
  useEffect(() => {
    if (!entityId) return;
    const onKey = (e: KeyboardEvent) => {
      if (e.key === 'Escape') closeEntity();
    };
    window.addEventListener('keydown', onKey);
    return () => window.removeEventListener('keydown', onKey);
  }, [entityId, closeEntity]);

  if (!entityId) return null;

  return (
    <aside
      aria-label={PANEL_NAME}
      className="flex h-full w-full max-w-md flex-col overflow-y-auto border-l border-neutral-200 bg-white shadow-lg"
    >
      <header className="flex items-start justify-between gap-2 border-b border-neutral-200 p-3">
        <div>
          <div className="text-xs font-semibold uppercase tracking-wide text-neutral-500">
            {query.data?.entity_type ?? 'Entity'}
          </div>
          <h2 className="text-base font-semibold">
            {query.data?.name ?? (query.isLoading ? 'Loading…' : entityId)}
          </h2>
          {query.data && (
            <div className="text-xs text-neutral-500">
              Ward {query.data.ward} · {query.data.module}
            </div>
          )}
        </div>
        <button
          type="button"
          onClick={closeEntity}
          aria-label="Close panel"
          className="rounded px-2 py-1 text-sm text-neutral-600 hover:bg-neutral-100"
        >
          ×
        </button>
      </header>

      {query.isError && (
        <div className="p-3 text-sm text-red-700" role="alert">
          {ERROR.entity_fetch_failed}
        </div>
      )}

      {query.data && <EntityBody detail={query.data} />}
    </aside>
  );
}

function EntityBody({
  detail,
}: {
  detail: NonNullable<ReturnType<typeof useEntityDetail>['data']>;
}) {
  return (
    <>
      <div className="border-b border-neutral-200 p-3 text-xs text-neutral-600">
        <div>
          Last observed:{' '}
          {detail.last_observed_at
            ? new Date(detail.last_observed_at).toISOString().slice(0, 10)
            : 'Not observed'}
        </div>
        <div>Scored: {new Date(detail.last_computed_at).toISOString().slice(0, 10)}</div>
      </div>

      <div className="space-y-2 p-3">
        {detail.variables.map((v) => (
          <ProtectedVariableRow
            key={v.variable}
            variable={v}
            subVariables={detail.sub_variables.filter((s) => s.variable === v.variable)}
          />
        ))}
      </div>

      {detail.findings.length > 0 && (
        <section className="border-t border-neutral-200 p-3" aria-label="Findings">
          <h3 className="text-sm font-semibold">Findings</h3>
          <Suspense
            fallback={<div className="mt-2 text-xs text-neutral-500">Loading findings…</div>}
          >
            <div className="mt-2 space-y-2">
              {detail.findings.map((f) => (
                <FindingDetail key={f.id} finding={f} />
              ))}
            </div>
          </Suspense>
        </section>
      )}

      <footer className="mt-auto border-t border-neutral-200 bg-neutral-50 p-3 text-[11px] text-neutral-500">
        {detail.attribution}
      </footer>
    </>
  );
}

// Runs the NFR-02 guard on each variable before it reaches the UI. On a failed guard, the
// row renders an error state instead of the number.
function ProtectedVariableRow({
  variable,
  subVariables,
}: {
  variable: Parameters<typeof VariableRow>[0]['variable'];
  subVariables: Parameters<typeof VariableRow>[0]['subVariables'];
}) {
  try {
    assertScored(variable);
  } catch (err) {
    if (err instanceof ScoredFieldsMissingError) {
      return (
        <div
          role="alert"
          className="rounded border border-red-300 bg-red-50 p-3 text-sm text-red-800"
        >
          <div className="font-medium">{variable.variable}</div>
          <div className="mt-1 text-xs">{ERROR.missing_coverage_or_confidence}</div>
        </div>
      );
    }
    throw err;
  }
  return <VariableRow variable={variable} subVariables={subVariables} />;
}
