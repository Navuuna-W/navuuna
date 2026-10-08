// On-screen dates and relative times.
//
// PRD §10:
//   - fixed form: "14 Sep 2026" (day · three-letter abbreviation · year)
//   - relative time only when the gap is < 24 h ("3 hours ago", "just now")
//   - time zone: Africa/Nairobi (UTC+03, no DST)
//
// Why we build the string ourselves instead of relying on Intl.DateTimeFormat:
// ICU in Node 22 and current Chromium formats September as "14 Sept 2026" in en-GB,
// which does not match the PRD. We only need twelve month abbreviations in English, so
// we build the three parts (day, month, year) from Intl's parts and look the month up
// in a fixed table. That keeps us independent of ICU locale drift.

const TIME_ZONE = 'Africa/Nairobi';

// Three-letter English abbreviations. Fixed on purpose.
const MONTH_ABBREV = [
  'Jan',
  'Feb',
  'Mar',
  'Apr',
  'May',
  'Jun',
  'Jul',
  'Aug',
  'Sep',
  'Oct',
  'Nov',
  'Dec',
] as const;

// Intl.DateTimeFormat parts type doesn't export its union cleanly; this is the subset
// we read.
interface Part {
  type: string;
  value: string;
}

function parts(iso: string): Part[] {
  const formatter = new Intl.DateTimeFormat('en-GB', {
    timeZone: TIME_ZONE,
    year: 'numeric',
    month: 'numeric',
    day: 'numeric',
  });
  return formatter.formatToParts(new Date(iso));
}

function pick(parts: Part[], type: string): string {
  const p = parts.find((x) => x.type === type);
  if (!p) throw new Error(`formatDate: missing part '${type}'`);
  return p.value;
}

/**
 * "14 Sep 2026" — day · month abbreviation · year, Africa/Nairobi local date.
 * Throws if the ISO string does not parse.
 */
export function formatDate(iso: string): string {
  const p = parts(iso);
  // en-GB pads 'day: numeric' to two digits ("01"). Strip to match PRD §10 "1 Sep 2026".
  const day = String(Number(pick(p, 'day')));
  const month = Number(pick(p, 'month'));
  const year = pick(p, 'year');
  const monthAbbrev = MONTH_ABBREV[month - 1];
  if (!monthAbbrev) throw new Error(`formatDate: month out of range: ${month}`);
  return `${day} ${monthAbbrev} ${year}`;
}

const MS = { second: 1000, minute: 60_000, hour: 3_600_000, day: 86_400_000 } as const;

/**
 * Relative time for the last 24 hours; the fixed-form date otherwise.
 * `now` is a parameter so tests don't depend on the wall clock.
 */
export function formatRelative(iso: string, now: Date = new Date()): string {
  const then = new Date(iso).getTime();
  const diff = now.getTime() - then;

  if (diff < 0) return formatDate(iso); // future dates fall through to fixed form
  if (diff < MS.minute) return 'just now';
  if (diff < MS.hour) {
    const n = Math.floor(diff / MS.minute);
    return n === 1 ? '1 minute ago' : `${n} minutes ago`;
  }
  if (diff < MS.day) {
    const n = Math.floor(diff / MS.hour);
    return n === 1 ? '1 hour ago' : `${n} hours ago`;
  }
  return formatDate(iso);
}
