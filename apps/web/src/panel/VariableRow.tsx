// One of the five variable rows in the entity panel (S2). Shows the plain-language name,
// the integer score, coverage bar + "X of Y", confidence bar + word, and badges when the
// variable is partly verified or cannot assess.
//
// Guards:
//   - The NFR-02 guard runs on every row at the call site (EntityPanel). If it throws, the
//     parent swaps the row for an error state — never a bare score.

import type { components } from '@/api/schema';
import { CoverageBar, ConfidenceBar } from './Bars';
import { CannotAssessBadge, PartlyVerifiedBadge } from './Badges';
import { EMPTY, VARIABLE_LABELS } from '@/copy/labels';
import { useState } from 'react';
import { SubVariableAccordion } from './SubVariableAccordion';

type VariableScore = components['schemas']['VariableScore'];
type SubVariableScore = components['schemas']['SubVariableScore'];

export function VariableRow({
  variable,
  subVariables,
}: {
  variable: VariableScore;
  subVariables: SubVariableScore[];
}) {
  const [expanded, setExpanded] = useState(false);
  const label = VARIABLE_LABELS[variable.variable];

  const isCannotAssess = variable.status === 'cannot_assess' || variable.score === null;
  const isPartlyVerified = variable.status === 'partly_verified';

  return (
    <section
      aria-label={`Variable ${variable.variable} — ${label}`}
      className="rounded border border-neutral-200 bg-white p-3"
    >
      <header className="flex items-start justify-between gap-2">
        <div>
          <div className="text-xs font-semibold uppercase tracking-wide text-neutral-500">
            {variable.variable}
          </div>
          <h3 className="text-sm font-medium">{label}</h3>
        </div>
        {!isCannotAssess && (
          <div className="text-3xl font-semibold tabular-nums" aria-label="Score">
            {Math.round(variable.score ?? 0)}
          </div>
        )}
      </header>

      <div className="mt-2 flex flex-wrap gap-2">
        {isPartlyVerified && (
          <PartlyVerifiedBadge
            reason={
              variable.gate_status === 'provisional'
                ? 'activity gate was not measured — confidence halved'
                : undefined
            }
          />
        )}
        {isCannotAssess && <CannotAssessBadge />}
      </div>

      {isCannotAssess ? (
        <p className="mt-3 text-sm text-neutral-700">{EMPTY.cannot_assess_variable}</p>
      ) : (
        <div className="mt-3 grid grid-cols-2 gap-3">
          <CoverageBar measured={variable.measured_count} total={variable.total_count} />
          <ConfidenceBar value={variable.confidence} />
        </div>
      )}

      {subVariables.length > 0 && (
        <div className="mt-3 border-t border-neutral-200 pt-2">
          <button
            type="button"
            onClick={() => setExpanded((prev) => !prev)}
            aria-expanded={expanded}
            className="text-xs font-medium text-blue-800 hover:underline"
          >
            {expanded ? 'Hide sub-measurements' : 'Show sub-measurements'}
          </button>
          {expanded && (
            <div className="mt-2">
              <SubVariableAccordion items={subVariables} />
            </div>
          )}
        </div>
      )}
    </section>
  );
}
