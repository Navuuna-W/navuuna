// Smoke test: the app renders without throwing and shows its title.
// Proves Vite + React + jsdom + Testing Library + the QueryClient and Router providers
// all wire together before any feature code lands. The MapView uses MapLibre, which
// jsdom cannot run — the test environment stubs its canvas away.

import { render, screen } from '@testing-library/react';
import { describe, it, expect, vi } from 'vitest';
import { App } from '@/App';

vi.mock('maplibre-gl', () => {
  const noop = () => undefined;
  const StubMap = function (): unknown {
    return {
      on: noop,
      off: noop,
      remove: noop,
      addSource: noop,
      addLayer: noop,
      getSource: () => null,
      isStyleLoaded: () => false,
      queryRenderedFeatures: () => [],
      getCanvas: () => document.createElement('canvas'),
    };
  };
  return { default: { Map: StubMap, addProtocol: noop }, Map: StubMap, addProtocol: noop };
});

vi.mock('pmtiles', () => {
  const Protocol = function (): unknown {
    return { tile: () => Promise.resolve() };
  };
  return { Protocol };
});

describe('App', () => {
  it('renders the Navuuna title and the illustrative-data banner', () => {
    render(<App />);
    expect(screen.getByRole('heading', { level: 1, name: /navuuna/i })).toBeInTheDocument();
    expect(screen.getByRole('status')).toHaveTextContent(/illustrative data/i);
  });

  it('renders the permanent attribution footer', () => {
    render(<App />);
    expect(screen.getByRole('contentinfo')).toHaveTextContent(/OpenStreetMap/);
  });
});
