// Finding detail (S3). One card per finding showing the four story lines from PRD §9.3:
// what the record says, what we observe, the gap, the sources + dates, and the contest
// line. Held findings only appear here when the caller is an Analyst, and they wear a
// 'Not visible to other users until published' banner (A-19, DEC-08). Contested findings
// are visible to everyone and wear the CONTEXT.md contest banner.

import type { components } from '@/api/schema';
import { FINDING_HOVER, FINDING_STATUS } from '@/copy/labels';
import { formatDate } from '@/copy/formatDate';

type FindingSummary = components['schemas']['FindingSummary'];

const CONTEST_EMAIL = 'contact@navuuna.example';

export function FindingDetail({ finding }: { finding: FindingSummary }) {
  const isHeld = finding.state === 'held' || finding.state === 'explanation_checked';
  const isContested = finding.state === 'contested';

  const borderClass = isHeld
    ? 'border-amber-500 bg-amber-50'
    : isContested
      ? 'border-sky-500 bg-sky-50'
      : 'border-neutral-200 bg-white';

  return (
    <article
      aria-label={`Finding ${finding.id}`}
      className={'rounded border p-3 text-sm ' + borderClass}
    >
      {isHeld && (
        <div
          role="status"
          className="mb-2 rounded bg-amber-100 px-2 py-1 text-xs font-medium text-amber-900"
        >
          Not visible to other users until published
        </div>
      )}
      {isContested && (
        <div
          role="status"
          className="mb-2 rounded bg-sky-100 px-2 py-1 text-xs font-medium text-sky-900"
        >
          {FINDING_HOVER.contested}
        </div>
      )}

      <header className="mb-2">
        <div className="text-xs font-semibold uppercase tracking-wide text-neutral-500">
          {FINDING_STATUS[finding.state]} · sub-variable {finding.sub_variable} · {finding.severity}{' '}
          severity
        </div>
        <h4 className="text-sm font-semibold">{finding.gap_summary}</h4>
      </header>

      <dl className="grid grid-cols-1 gap-2">
        <StoryRow label="Record says" value={finding.record_says} />
        <StoryRow label="We observe" value={finding.we_observe} />
      </dl>

      <footer className="mt-3 border-t border-neutral-200 pt-2 text-xs text-neutral-600">
        <div>Detected {formatDate(finding.detected_at)}</div>
        {finding.published_at && <div>Published {formatDate(finding.published_at)}</div>}
        <div className="mt-1">
          If this is wrong, tell us —{' '}
          <a href={`mailto:${CONTEST_EMAIL}`} className="text-blue-800 hover:underline">
            {CONTEST_EMAIL}
          </a>
        </div>
      </footer>
    </article>
  );
}

function StoryRow({ label, value }: { label: string; value: string }) {
  return (
    <div>
      <dt className="text-[11px] font-medium uppercase tracking-wide text-neutral-500">{label}</dt>
      <dd className="text-sm text-neutral-900">{value}</dd>
    </div>
  );
}
