// Small presentational bits used by the entity panel: a coverage bar + "X of Y" text, and
// a confidence bar + word. Both are deliberately boring: one visible fill against a track,
// an accessible label, and nothing clickable.

import { confidenceWord } from '@/copy/labels';

export function CoverageBar({ measured, total }: { measured: number; total: number }) {
  const safeTotal = total <= 0 ? 1 : total;
  const pct = Math.round((measured / safeTotal) * 100);
  return (
    <div className="w-full">
      <div
        role="progressbar"
        aria-valuenow={measured}
        aria-valuemin={0}
        aria-valuemax={total}
        aria-label={`Coverage: ${measured} of ${total} contributors measured`}
        className="h-2 w-full overflow-hidden rounded bg-neutral-200"
      >
        <div className="h-full bg-neutral-700" style={{ width: `${pct}%` }} />
      </div>
      <div className="mt-1 text-xs text-neutral-700">
        <span className="font-medium">{measured}</span> of {total}
      </div>
    </div>
  );
}

export function ConfidenceBar({ value }: { value: number }) {
  const pct = Math.round(value * 100);
  const word = confidenceWord(value);
  return (
    <div className="w-full">
      <div
        role="progressbar"
        aria-valuenow={pct}
        aria-valuemin={0}
        aria-valuemax={100}
        aria-label={`Confidence: ${word}`}
        className="h-2 w-full overflow-hidden rounded bg-neutral-200"
      >
        <div className="h-full bg-neutral-700" style={{ width: `${pct}%` }} />
      </div>
      <div className="mt-1 text-xs text-neutral-700">
        confidence <span className="font-medium">{word}</span>
      </div>
    </div>
  );
}
