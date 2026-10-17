// Reusable status badges for variable rows. Provisional and Cannot assess are the two states
// the panel must distinguish from a plain scored row (CLAUDE.md product rule 5). The badge is
// named for the engine's status; its wording lives in src/copy/labels.ts.

import { BADGE } from '@/copy/labels';

export function ProvisionalBadge({ reason }: { reason?: string }) {
  return (
    <span
      className="rounded-full border border-amber-500 bg-amber-50 px-2 py-0.5 text-xs font-medium text-amber-900"
      title={reason}
    >
      {BADGE.provisional}
    </span>
  );
}

export function CannotAssessBadge() {
  return (
    <span className="rounded-full border border-neutral-400 bg-neutral-50 px-2 py-0.5 text-xs font-medium text-neutral-700">
      {BADGE.cannot_assess}
    </span>
  );
}
