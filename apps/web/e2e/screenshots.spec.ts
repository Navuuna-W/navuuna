// Captures review screenshots into apps/web/e2e-screenshots/ (gitignored). These are
// evidence for human review, not an automated check — the smoke suite is still what runs
// in CI. Run with:  npm run test:e2e -- screenshots.spec.ts
//
// Headless Chromium ships without hardware WebGL; playwright.config.ts launches with
// SwiftShader flags so MapLibre's GL layer renders the entity dots and lines. If a
// screenshot still looks blank, that fallback did not activate and the test fails on the
// "feature count > 0" assertion rather than silently producing an empty map.

import { test, expect } from '@playwright/test';
import { mkdirSync, readFileSync } from 'node:fs';
import path from 'node:path';
import { fileURLToPath } from 'node:url';

const __filename = fileURLToPath(import.meta.url);
const __dirname = path.dirname(__filename);
const DIR = path.resolve(__dirname, '..', 'e2e-screenshots');
mkdirSync(DIR, { recursive: true });

const VIEWPORT = { width: 1280, height: 800 };

// Fixture IDs from fixtures/generate.ts (seed-stable).
const ENTITY_WITH_PUBLISHED = 'wp-002';
const ENTITY_WITH_HELD = 'wp-006';

test.use({ viewport: VIEWPORT });

async function waitForMapPainted(page: import('@playwright/test').Page) {
  // The fixture-only test hook on window.__NAVUUNA_TEST__ is set by MapView.tsx on every
  // MapLibre 'idle' event. We wait for a non-zero feature count — a positive signal that
  // the GL layer actually drew the entity dots, not just a blank canvas.
  await page.waitForFunction(
    () => {
      const h = (globalThis as unknown as { __NAVUUNA_TEST__?: { featureCount?: number } })
        .__NAVUUNA_TEST__;
      return !!h && (h.featureCount ?? 0) > 0;
    },
    undefined,
    { timeout: 15_000 }
  );
}

test('capture: map with basemap and dots', async ({ page }) => {
  await page.goto('/');
  await waitForMapPainted(page);
  await page.screenshot({ path: path.join(DIR, '01-map.png'), fullPage: false });
});

test('capture: panel open with the five variables (Viewer)', async ({ page }) => {
  await page.goto(`/?entity=${ENTITY_WITH_PUBLISHED}`);
  await waitForMapPainted(page);
  await expect(page.getByRole('complementary', { name: /passport/i })).toBeVisible();
  await page.screenshot({ path: path.join(DIR, '02-panel-viewer.png'), fullPage: false });
});

test('capture: Analyst view of the held-finding entity', async ({ page }) => {
  await page.goto(`/?entity=${ENTITY_WITH_HELD}`);
  await waitForMapPainted(page);
  await expect(page.getByRole('complementary', { name: /passport/i })).toBeVisible();

  await page.getByLabel(/Preview role selector/i).selectOption('analyst');
  await expect(page.getByText(/Not visible to other users until published/i)).toBeVisible();
  await page.screenshot({ path: path.join(DIR, '03-analyst-held.png'), fullPage: false });
});

test('capture: Viewer view of the same held-finding entity (no trace)', async ({ page }) => {
  await page.goto(`/?entity=${ENTITY_WITH_HELD}`);
  await waitForMapPainted(page);
  await expect(page.getByRole('complementary', { name: /passport/i })).toBeVisible();
  await page.getByLabel(/Preview role selector/i).selectOption('viewer');
  await expect(page.getByText(/Not visible to other users/i)).not.toBeVisible();
  await page.screenshot({ path: path.join(DIR, '04-viewer-held-silent.png'), fullPage: false });
});

test('capture: lens on vs lens off (county_planner vs demo_access) + pixel diff', async ({
  page,
}) => {
  // "Off" (default lens).
  await page.goto('/?lens=county_planner');
  await waitForMapPainted(page);
  const pathOff = path.join(DIR, '05a-lens-off.png');
  await page.screenshot({ path: pathOff, fullPage: false });

  // "On" = alternate demo lens that re-weights toward V5; the recolour must be visible.
  await page.goto('/?lens=demo_access');
  await waitForMapPainted(page);
  const pathOn = path.join(DIR, '05b-lens-on.png');
  await page.screenshot({ path: pathOn, fullPage: false });

  // Lens recolour must actually change what is drawn. If the two PNGs are byte-identical,
  // the mock mapped both to the same lens or the GL layer did not refresh — fail loudly.
  const bufOff = readFileSync(pathOff);
  const bufOn = readFileSync(pathOn);
  expect(bufOff.equals(bufOn), 'lens-on and lens-off screenshots are identical').toBe(false);
});
