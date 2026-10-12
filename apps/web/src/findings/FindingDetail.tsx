// Finding detail (S3). One card per finding showing the four story lines from PRD §9.3:
// what the record says, what we observe, the gap, the sources + dates, and the contest
// line. Only published and resolved findings reach a non-analyst (Flag::PUBLIC_STATES);
// every other state arrives here only on an analyst's screen, and the pre-publication
// ones wear a 'Not visible to other users until published' banner (A-19, DEC-08). A
// contested finding is hidden from viewers too while the challenge is looked at (ADR-013),
// so its banner is analyst-facing as well.

import type { components } from '@/api/schema';
import { FINDING_HOVER, FINDING_STATUS } from '@/copy/labels';
import { formatDate } from '@/copy/formatDate';

type FindingSummary = components['schemas']['FindingSummary'];
type FindingState = FindingSummary['state'];

const CONTEST_EMAIL = 'contact@navuuna.example';

type Banner = 'pre_publication' | 'contested' | 'none';

// Which banner each state wears. A Record over the contract's own union, so a state added to
// FindingSummary.state fails to compile until someone picks its banner — a hand-kept list
// would have let it through silently with no banner at all.
//
// `pre_publication` = the engine has raised the finding and no analyst has cleared it for
// publication yet; `detected` is the engine's entry state (FlagState::Detected) and joined
// the contract in C10b. `dismissed` gets no banner even though it is analyst-only: it is
// never going to be published, so "until published" would be the wrong sentence.
const BANNER_BY_STATE: Record<FindingState, Banner> = {
  detected: 'pre_publication',
  held: 'pre_publication',
  explanation_checked: 'pre_publication',
  contested: 'contested',
  dismissed: 'none',
  published: 'none',
  resolved: 'none',
};

export function FindingDetail({ finding }: { finding: FindingSummary }) {
  const banner = BANNER_BY_STATE[finding.state];
  const isPrePublication = banner === 'pre_publication';
  const isContested = banner === 'contested';

  const borderClass = isPrePublication
    ? 'border-amber-500 bg-amber-50'
    : isContested
      ? 'border-sky-500 bg-sky-50'
      : 'border-neutral-200 bg-white';

  return (
    <article
      aria-label={`Finding ${finding.id}`}
      className={'rounded border p-3 text-sm ' + borderClass}
    >
      {isPrePublication && (
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
