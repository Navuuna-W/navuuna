// Playwright config for the apps/web smoke tests.
//
// The webServer entry builds the production bundle and serves it via `vite preview`,
// which also attaches the dev MVT/API mock (configurePreviewServer in vite-plugins/
// mockServer.ts). The smoke test therefore exercises exactly what `npm run build &&
// npm run preview` does — no dev server, no HMR.

import { defineConfig, devices } from '@playwright/test';

const PORT = 4173;

export default defineConfig({
  testDir: './e2e',
  fullyParallel: true,
  retries: 0,
  reporter: [['list']],
  use: {
    baseURL: `http://localhost:${PORT}`,
    headless: true,
    trace: 'retain-on-failure',
  },
  projects: [
    {
      name: 'chromium',
      use: { ...devices['Desktop Chrome'] },
    },
  ],
  webServer: {
    command: 'npm run build && npm run preview',
    url: `http://localhost:${PORT}`,
    timeout: 180_000,
    reuseExistingServer: !process.env.CI,
  },
});
