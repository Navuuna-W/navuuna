// Captures review screenshots into apps/web/e2e-screenshots/ (gitignored). These are
// evidence for human review, not an automated check — the smoke suite is still what runs
// in CI. Run with:  npm run test:e2e -- screenshots.spec.ts

import { test, expect } from '@playwright/test';
import { mkdirSync } from 'node:fs';
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

async function waitForMap(page: import('@playwright/test').Page) {
  // Fresh clones without `npm run basemap` cannot resolve the Protomaps vector source,
  // which starves MapLibre's tile fetch loop. These screenshots are evidence, not
  // assertions, so we settle on the map canvas being in the DOM and give MapLibre a
  // moment to paint whichever layers it can.
  await page.locator('[aria-label="Map of Nairobi"]').waitFor({ state: 'visible' });
  await page.waitForTimeout(800);
}

test('capture: map with basemap and dots', async ({ page }) => {
  await page.goto('/');
  await waitForMap(page);
  await page.waitForTimeout(400); // let MapLibre settle the circle layer
  await page.screenshot({ path: path.join(DIR, '01-map.png'), fullPage: false });
});

test('capture: panel open with the five variables (Viewer)', async ({ page }) => {
  await page.goto(`/?entity=${ENTITY_WITH_PUBLISHED}`);
  await expect(page.getByRole('complementary', { name: /entity details/i })).toBeVisible();
  await page.waitForTimeout(400);
  await page.screenshot({ path: path.join(DIR, '02-panel-viewer.png'), fullPage: false });
});

test('capture: Analyst view of the held-finding entity', async ({ page }) => {
  await page.goto(`/?entity=${ENTITY_WITH_HELD}`);
  await expect(page.getByRole('complementary', { name: /entity details/i })).toBeVisible();

  // Switch role to Analyst via the header dropdown.
  await page.getByLabel(/Preview role selector/i).selectOption('analyst');
  // Wait for the lazy FindingDetail chunk to render the "Not visible" banner.
  await expect(page.getByText(/Not visible to other users until published/i)).toBeVisible();
  await page.waitForTimeout(300);
  await page.screenshot({ path: path.join(DIR, '03-analyst-held.png'), fullPage: false });
});

test('capture: Viewer view of the same held-finding entity (no trace)', async ({ page }) => {
  await page.goto(`/?entity=${ENTITY_WITH_HELD}`);
  await expect(page.getByRole('complementary', { name: /entity details/i })).toBeVisible();
  // Ensure role is Viewer (default).
  await page.getByLabel(/Preview role selector/i).selectOption('viewer');
  await page.waitForTimeout(400);
  await expect(page.getByText(/Not visible to other users/i)).not.toBeVisible();
  await page.screenshot({ path: path.join(DIR, '04-viewer-held-silent.png'), fullPage: false });
});

test('capture: lens on vs lens off (default vs ?lens=county_planner)', async ({ page }) => {
  // "Off" = default (county_planner stripped from URL; lens dropdown shows the default).
  await page.goto('/');
  await waitForMap(page);
  await page.waitForTimeout(400);
  await page.screenshot({ path: path.join(DIR, '05a-lens-off.png'), fullPage: false });

  // "On" = explicit lens on URL. (v1 only has county_planner; a future lens would use a
  // different id here. The visible shift is in the Filters rail text and the tile URL.)
  await page.goto('/?lens=county_planner');
  await waitForMap(page);
  await page.waitForTimeout(400);
  await page.screenshot({ path: path.join(DIR, '05b-lens-on.png'), fullPage: false });
});
