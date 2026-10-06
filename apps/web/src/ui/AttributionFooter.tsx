// Permanent, non-dismissable attribution block (NFR-09 / CLAUDE.md product rule 6). The
// exact wording is locked in src/copy/labels.ts.

import { ATTRIBUTION } from '@/copy/labels';

export function AttributionFooter() {
  return (
    <footer
      role="contentinfo"
      className="border-t border-neutral-200 bg-neutral-50 px-3 py-1 text-[11px] text-neutral-600"
    >
      {ATTRIBUTION}
    </footer>
  );
}
