// Dates are user-visible per PRD §10. These tests pin the format and the Africa/Nairobi
// time zone — a locale change in ICU can't silently drift to "14 Sept 2026" without
// failing here.

import { describe, it, expect } from 'vitest';
import { formatDate, formatRelative } from './formatDate';

describe('formatDate', () => {
  it('formats September as "Sep", not "Sept" (PRD §10)', () => {
    expect(formatDate('2026-09-14T10:00:00Z')).toBe('14 Sep 2026');
    expect(formatDate('2026-09-01T10:00:00Z')).toBe('1 Sep 2026');
  });

  it('covers every month with the fixed three-letter abbreviation', () => {
    const expected = [
      ['2026-01-15T12:00:00Z', '15 Jan 2026'],
      ['2026-02-15T12:00:00Z', '15 Feb 2026'],
      ['2026-03-15T12:00:00Z', '15 Mar 2026'],
      ['2026-04-15T12:00:00Z', '15 Apr 2026'],
      ['2026-05-15T12:00:00Z', '15 May 2026'],
      ['2026-06-15T12:00:00Z', '15 Jun 2026'],
      ['2026-07-15T12:00:00Z', '15 Jul 2026'],
      ['2026-08-15T12:00:00Z', '15 Aug 2026'],
      ['2026-09-15T12:00:00Z', '15 Sep 2026'],
      ['2026-10-15T12:00:00Z', '15 Oct 2026'],
      ['2026-11-15T12:00:00Z', '15 Nov 2026'],
      ['2026-12-15T12:00:00Z', '15 Dec 2026'],
    ] as const;
    for (const [iso, want] of expected) {
      expect(formatDate(iso)).toBe(want);
    }
  });

  it('uses Africa/Nairobi (UTC+03) local date, not UTC', () => {
    // 2026-09-13T22:30:00Z is 2026-09-14 01:30 in Nairobi — expect "14 Sep 2026".
    expect(formatDate('2026-09-13T22:30:00Z')).toBe('14 Sep 2026');
    // 2026-09-14T00:30:00Z is 2026-09-14 03:30 in Nairobi — expect "14 Sep 2026".
    expect(formatDate('2026-09-14T00:30:00Z')).toBe('14 Sep 2026');
  });
});

describe('formatRelative', () => {
  const now = new Date('2026-10-08T12:00:00Z');

  it('"just now" under a minute', () => {
    expect(formatRelative('2026-10-08T11:59:30Z', now)).toBe('just now');
  });

  it('rounds down to whole minutes under an hour', () => {
    expect(formatRelative('2026-10-08T11:59:00Z', now)).toBe('1 minute ago');
    expect(formatRelative('2026-10-08T11:45:00Z', now)).toBe('15 minutes ago');
  });

  it('rounds down to whole hours under 24 h', () => {
    expect(formatRelative('2026-10-08T11:00:00Z', now)).toBe('1 hour ago');
    expect(formatRelative('2026-10-08T08:30:00Z', now)).toBe('3 hours ago');
    expect(formatRelative('2026-10-07T13:30:00Z', now)).toBe('22 hours ago');
  });

  it('falls back to the fixed form at ≥ 24 h', () => {
    // 24 h exactly ⇒ fixed form "7 Oct 2026" (Nairobi: 2026-10-07 15:00 local).
    expect(formatRelative('2026-10-07T12:00:00Z', now)).toBe('7 Oct 2026');
    // 48 h ⇒ fixed form.
    expect(formatRelative('2026-10-06T12:00:00Z', now)).toBe('6 Oct 2026');
  });

  it('future ISO strings fall through to the fixed form', () => {
    expect(formatRelative('2026-11-01T10:00:00Z', now)).toBe('1 Nov 2026');
  });
});
