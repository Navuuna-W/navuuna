// Finding detail (S3). One card per finding showing the four story lines from PRD §9.3:
// what the record says, what we observe, the gap, the sources + dates, and the contest
// line. Held findings only appear here when the caller is an Analyst, and they wear a
// 'Not visible to other users until published' banner (A-19, DEC-08).

import type { components } from '@/api/schema';

type FindingSummary = components['schemas']['FindingSummary'];

const CONTEST_EMAIL = 'contact@navuuna.example';

export function FindingDetail({ finding }: { finding: FindingSummary }) {
  const isHeld = finding.state === 'held' || finding.state === 'explanation_checked';

  return (
    <article
      aria-label={`Finding ${finding.id}`}
      className={
        'rounded border p-3 text-sm ' +
        (isHeld ? 'border-amber-500 bg-amber-50' : 'border-neutral-200 bg-white')
      }
    >
      {isHeld && (
        <div
          role="status"
          className="mb-2 rounded bg-amber-100 px-2 py-1 text-xs font-medium text-amber-900"
        >
          Not visible to other users until published
        </div>
      )}

      <header className="mb-2">
        <div className="text-xs font-semibold uppercase tracking-wide text-neutral-500">
          Sub-variable {finding.sub_variable} · {finding.severity} severity
        </div>
        <h4 className="text-sm font-semibold">{finding.gap_summary}</h4>
      </header>

      <dl className="grid grid-cols-1 gap-2">
        <StoryRow label="Record says" value={finding.record_says} />
        <StoryRow label="We observe" value={finding.we_observe} />
      </dl>

      <footer className="mt-3 border-t border-neutral-200 pt-2 text-xs text-neutral-600">
        <div>Detected {toDate(finding.detected_at)}</div>
        {finding.published_at && <div>Published {toDate(finding.published_at)}</div>}
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

function toDate(iso: string): string {
  return new Date(iso).toISOString().slice(0, 10);
}
