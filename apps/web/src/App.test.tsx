// Smoke test: the app renders without throwing and shows its title.
// Proves Vite + React + jsdom + Testing Library all wire together before any feature code
// lands.

import { render, screen } from '@testing-library/react';
import { describe, it, expect } from 'vitest';
import { App } from '@/App';

describe('App', () => {
  it('renders the Navuuna title', () => {
    render(<App />);

    expect(screen.getByRole('heading', { level: 1, name: /navuuna/i })).toBeInTheDocument();
  });
});
