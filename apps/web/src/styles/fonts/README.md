# Self-hosted fonts

The two families `apps/web` renders in, as woff2 Latin subsets (DESIGN.md §1.4, §2.7).
Committed on purpose: 26 kB + 40 kB, so the build, the test run and the Playwright suite all
work with no network and we make zero third-party runtime requests.

| File                                       | Family              | Axes           | Used for                       |
| ------------------------------------------ | ------------------- | -------------- | ------------------------------ |
| `public-sans-latin-variable.woff2`         | Public Sans         | `wght 400–700` | Body, UI, SVG text             |
| `bricolage-grotesque-latin-variable.woff2` | Bricolage Grotesque | `wght 400–800` | `h1`–`h3`, big numerals, brand |

Re-fetch with `npm run fonts` (skips any file already present). The `@font-face` rules that load
them are in `../fonts.css`.

## Licence

Both are SIL Open Font License 1.1 — the full text is in `OFL.txt`, which also carries both
copyright notices. Reserved Font Names: "Public Sans", "Bricolage Grotesque". We redistribute the
subsets unmodified under their original name, which the licence allows; if we ever modify a font
we must rename it.
