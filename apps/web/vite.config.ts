// Vite config for apps/web.
//
// Two modes, selected by the VITE_DATA_SOURCE env var (resolved via loadEnv so .env.local
// is picked up — this file runs in Node, where import.meta.env is not available and
// process.env misses .env files):
//   VITE_DATA_SOURCE unset or 'fixture' (default)
//     - the mock plugin answers /api/v1/entities/{id} and /tiles/{z}/{x}/{y}.mvt from
//       the deterministic fixtures;
//     - no proxy — nothing hits a real backend.
//   VITE_DATA_SOURCE=api
//     - the mock plugin is a no-op (middleware not registered);
//     - server.proxy and preview.proxy forward /api, /tiles and /sanctum to
//       http://localhost:8000 so the local Laravel (apps/api) answers them.
//       Change VITE_API_PROXY_TARGET to redirect the proxy at a different port.
// The proxy is attached in both modes so a mistake in VITE_DATA_SOURCE still fails loud
// (the mock takes precedence in fixture mode because it registers middleware first).

/// <reference types="vitest" />
import { defineConfig, loadEnv } from 'vite';
import react from '@vitejs/plugin-react';
import tailwindcss from '@tailwindcss/vite';
import path from 'node:path';
import { mockServer } from './vite-plugins/mockServer';

export default defineConfig(({ mode }) => {
  const env = loadEnv(mode, process.cwd(), 'VITE_');
  const dataSource = env.VITE_DATA_SOURCE ?? 'fixture';
  const apiMode = dataSource === 'api';
  const proxyTarget = env.VITE_API_PROXY_TARGET ?? 'http://localhost:8000';

  const proxy = {
    '/api': { target: proxyTarget, changeOrigin: true, secure: false },
    '/tiles': { target: proxyTarget, changeOrigin: true, secure: false },
    '/sanctum': { target: proxyTarget, changeOrigin: true, secure: false },
  };

  return {
    plugins: [react(), tailwindcss(), mockServer({ enabled: !apiMode })],
    resolve: {
      alias: {
        '@': path.resolve(__dirname, 'src'),
      },
    },
    server: {
      port: 5173,
      strictPort: true,
      proxy,
    },
    preview: {
      port: 4173,
      strictPort: true,
      proxy,
    },
    test: {
      globals: false,
      environment: 'jsdom',
      setupFiles: ['./src/test/setup.ts'],
      css: true,
      include: ['src/**/*.test.{ts,tsx}', 'fixtures/**/*.test.ts', 'vite-plugins/**/*.test.ts'],
      exclude: ['e2e/**', 'node_modules/**'],
    },
  };
});
