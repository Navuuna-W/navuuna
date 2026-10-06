// Vite config for apps/web.
// Dev-only plugins (the MVT and API mock, added in Step 4) attach themselves inside
// configureServer / configurePreviewServer. They are never bundled into the production
// output — Vite drops them when `vite build` runs.

/// <reference types="vitest" />
import { defineConfig } from 'vite';
import react from '@vitejs/plugin-react';
import tailwindcss from '@tailwindcss/vite';
import path from 'node:path';
import { mockServer } from './vite-plugins/mockServer';

export default defineConfig({
  plugins: [react(), tailwindcss(), mockServer()],
  resolve: {
    alias: {
      '@': path.resolve(__dirname, 'src'),
    },
  },
  server: {
    port: 5173,
    strictPort: true,
  },
  preview: {
    port: 4173,
    strictPort: true,
  },
  test: {
    globals: false,
    environment: 'jsdom',
    setupFiles: ['./src/test/setup.ts'],
    css: true,
    include: ['src/**/*.test.{ts,tsx}', 'fixtures/**/*.test.ts'],
    exclude: ['e2e/**', 'node_modules/**'],
  },
});
