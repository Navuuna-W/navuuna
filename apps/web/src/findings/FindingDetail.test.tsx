// FindingDetail: the panel S3 content and the held-finding invisibility rule (A-19).
//
// Two assertions matter:
//   1. When a published finding is passed, nothing suggests "held" state — no amber banner,
//      no "Not visible" text.
//   2. When a held finding is passed, the "Not visible to other users until published"
//      banner is rendered. In the full app, a Viewer never receives a held finding at all
//      (the mock strips them server-side); this test documents what the UI does when it
//      does receive one.

import { describe, it, expect } from 'vitest';
import { render, screen } from '@testing-library/react';
import { FindingDetail } from './FindingDetail';
import type { components } from '@/api/schema';

type FindingSummary = components['schemas']['FindingSummary'];

const base: FindingSummary = {
  id: 'test-finding-1',
  sub_variable: '2.4',
  state: 'published',
  severity: 'medium',
  detected_at: '2026-10-04T09:00:00Z',
  record_says: 'Register says operational.',
  we_observe: 'Observers report intermittent.',
  gap_summary: 'Observed status differs from the recorded status.',
  published_at: '2026-10-04T15:00:00Z',
};

describe('FindingDetail', () => {
  it('renders record_says and we_observe for a published finding', () => {
    render(<FindingDetail finding={base} />);
    expect(screen.getByText(base.record_says)).toBeInTheDocument();
    expect(screen.getByText(base.we_observe)).toBeInTheDocument();
    expect(screen.getByText(base.gap_summary)).toBeInTheDocument();
  });

  it('does NOT render the "Not visible" banner for a published finding', () => {
    render(<FindingDetail finding={base} />);
    expect(screen.queryByText(/Not visible to other users/i)).not.toBeInTheDocument();
  });

  it('renders the "Not visible to other users until published" banner for a held finding', () => {
    render(<FindingDetail finding={{ ...base, state: 'held', published_at: null }} />);
    expect(screen.getByText(/Not visible to other users until published/i)).toBeInTheDocument();
  });

  it('also shows the "Not visible" banner for explanation_checked (still held)', () => {
    render(
      <FindingDetail finding={{ ...base, state: 'explanation_checked', published_at: null }} />
    );
    expect(screen.getByText(/Not visible to other users until published/i)).toBeInTheDocument();
  });

  it('exposes a mailto contest link', () => {
    render(<FindingDetail finding={base} />);
    const link = screen.getByRole('link');
    expect(link.getAttribute('href')).toMatch(/^mailto:/);
  });
});
