// Reusable status badges for variable rows. Partly verified and Cannot assess are the two
// states the panel must distinguish from a plain measured row (CLAUDE.md product rule 5).

import { BADGE } from '@/copy/labels';

export function PartlyVerifiedBadge({ reason }: { reason?: string }) {
  return (
    <span
      className="rounded-full border border-amber-500 bg-amber-50 px-2 py-0.5 text-xs font-medium text-amber-900"
      title={reason}
    >
      {BADGE.partly_verified}
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
