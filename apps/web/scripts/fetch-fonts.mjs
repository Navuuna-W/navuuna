// Downloads the two self-hosted font families as woff2 Latin subsets into
// src/styles/fonts/. Runs before `npm run build` only when a file is missing — the
// subsets are committed (tens of kB each) so the build and the e2e suite work offline.
// Both families are SIL OFL 1.1; see src/styles/fonts/README.md for the notices.

import { mkdir, writeFile, access } from 'node:fs/promises';
import { dirname, join } from 'node:path';
import { fileURLToPath } from 'node:url';

const FONTS_DIR = join(dirname(fileURLToPath(import.meta.url)), '..', 'src', 'styles', 'fonts');

// Variable-weight axes: one file per family covers every weight the design uses
// (Public Sans 400/600/700, Bricolage Grotesque 700) — DESIGN.md §1.4.
const FAMILIES = [
  { fileName: 'public-sans-latin-variable.woff2', query: 'Public+Sans:wght@400..700' },
  {
    fileName: 'bricolage-grotesque-latin-variable.woff2',
    query: 'Bricolage+Grotesque:wght@400..800',
  },
];

// Google's css2 endpoint only serves woff2 to a browser-like UA; with no UA it serves ttf.
const WOFF2_USER_AGENT =
  'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0 Safari/537.36';

/** Returns the gstatic woff2 URL of the `latin` subset in a Google Fonts css2 stylesheet. */
function findLatinSubsetUrl(stylesheet, query) {
  const latinBlock = stylesheet.split('/* latin */')[1];
  if (latinBlock === undefined) throw new Error(`No /* latin */ subset for ${query}`);
  const url = /src:\s*url\((https:[^)]+\.woff2)\)/.exec(latinBlock);
  if (url === null) throw new Error(`No woff2 url in the latin subset for ${query}`);
  return url[1];
}

async function fetchText(url) {
  const response = await fetch(url, { headers: { 'User-Agent': WOFF2_USER_AGENT } });
  if (!response.ok) throw new Error(`${response.status} ${response.statusText} for ${url}`);
  return response.text();
}

async function downloadFamily({ fileName, query }) {
  const target = join(FONTS_DIR, fileName);
  const alreadyPresent = await access(target).then(
    () => true,
    () => false
  );
  if (alreadyPresent) {
    console.log(`skip   ${fileName} (already committed)`);
    return;
  }

  const stylesheet = await fetchText(
    `https://fonts.googleapis.com/css2?family=${query}&display=swap`
  );
  const response = await fetch(findLatinSubsetUrl(stylesheet, query));
  if (!response.ok) throw new Error(`${response.status} downloading ${fileName}`);
  const bytes = new Uint8Array(await response.arrayBuffer());

  await writeFile(target, bytes);
  console.log(`wrote  ${fileName} (${Math.round(bytes.byteLength / 1024)} kB)`);
}

await mkdir(FONTS_DIR, { recursive: true });
for (const family of FAMILIES) await downloadFamily(family);
