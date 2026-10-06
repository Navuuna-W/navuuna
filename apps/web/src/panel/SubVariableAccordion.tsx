// Lists sub-variable rows under one variable. Each row shows plain label, value + unit,
// confidence to 2 decimals, and the sources. A null (not measured) row shows the reason
// in place of the value — never 0, never "—" (CLAUDE.md product rule 2).

import type { components } from '@/api/schema';
import { EMPTY, confidenceWord } from '@/copy/labels';
import { roleOf, SUB_VARIABLES } from '@/copy/subVariables';

type SubVariableScore = components['schemas']['SubVariableScore'];

function tooltipFor(id: string): string | undefined {
  return SUB_VARIABLES.find((s) => s.id === id)?.tooltip;
}

export function SubVariableAccordion({ items }: { items: SubVariableScore[] }) {
  if (items.length === 0) return null;
  return (
    <ul className="space-y-2">
      {items.map((item) => (
        <li key={item.sub_variable} className="rounded bg-neutral-50 p-2 text-sm">
          <SubVariableRow item={item} />
        </li>
      ))}
    </ul>
  );
}

function SubVariableRow({ item }: { item: SubVariableScore }) {
  const isMeasured = item.status === 'measured' && item.score !== null;
  const role = roleOf(item.sub_variable);
  const tooltip = tooltipFor(item.sub_variable);
  const roleLabel = role === 'gate' ? 'gate' : role === 'guard' ? 'guard' : null;

  return (
    <div>
      <div className="flex items-start justify-between gap-2">
        <div className="min-w-0">
          <div className="flex items-center gap-1 text-sm font-medium">
            <span title={tooltip}>{item.label}</span>
            {roleLabel && (
              <span className="rounded border border-neutral-300 bg-white px-1 py-0.5 text-[9px] uppercase tracking-wide text-neutral-600">
                {roleLabel}
              </span>
            )}
          </div>
          <div className="text-[11px] text-neutral-500" title={tooltip ?? item.sub_variable}>
            {item.sub_variable}
          </div>
        </div>
        {isMeasured ? (
          <div className="text-right">
            <div className="font-mono text-sm font-semibold tabular-nums">
              {formatValue(item.value, item.unit)}
            </div>
          </div>
        ) : (
          <div className="max-w-[16rem] text-right text-xs italic text-neutral-700">
            {EMPTY.not_measured_prefix}
            {item.null_reason ?? 'reason unknown'}
          </div>
        )}
      </div>

      {isMeasured && item.confidence !== null && (
        <div className="mt-1 text-[11px] text-neutral-600">
          confidence {item.confidence.toFixed(2)} ({confidenceWord(item.confidence)})
        </div>
      )}

      {item.sources.length > 0 && (
        <ul className="mt-1 text-[11px] text-neutral-600">
          {item.sources.map((s, i) => (
            <li key={i}>
              {s.name}, {s.date}
            </li>
          ))}
        </ul>
      )}
    </div>
  );
}

function formatValue(value: number | null, unit: string | null): string {
  if (value === null) return '—';
  const formatted = Number.isInteger(value) ? value.toString() : value.toFixed(2);
  return unit ? `${formatted} ${unit}` : formatted;
}
