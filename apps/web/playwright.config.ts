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
  // Screenshot capture needs sequential role toggles against the same preview server.
  // Smoke is single-file; parallelism gains us nothing and loses determinism.
  fullyParallel: false,
  workers: 1,
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
      use: {
        ...devices['Desktop Chrome'],
        // Headless Chromium ships without hardware WebGL. Force SwiftShader so MapLibre's
        // GL layer actually renders the entity dots and lines — otherwise the map canvas
        // stays blank and screenshots end up at the empty-canvas file size.
        launchOptions: {
          args: [
            '--enable-unsafe-swiftshader',
            '--use-gl=swiftshader',
            '--use-angle=swiftshader',
            '--enable-webgl',
            '--ignore-gpu-blocklist',
          ],
        },
      },
    },
  ],
  webServer: {
    command: 'npm run build && npm run preview',
    url: `http://localhost:${PORT}`,
    timeout: 180_000,
    reuseExistingServer: !process.env.CI,
  },
});
