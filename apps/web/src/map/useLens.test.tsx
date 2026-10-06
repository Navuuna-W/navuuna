// Lens state is held on the URL and defaults to 'county_planner'. The reset-to-default
// case strips ?lens= from the URL rather than storing the default explicitly, so a shared
// link looks the same whether the user picked the default or never touched the control.

import { describe, it, expect } from 'vitest';
import { renderHook, act } from '@testing-library/react';
import { MemoryRouter, useLocation } from 'react-router-dom';
import type { ReactNode } from 'react';
import { useLens, DEFAULT_LENS_ID } from './useLens';

function wrapper(initial: string) {
  return ({ children }: { children: ReactNode }) => (
    <MemoryRouter initialEntries={[initial]}>{children}</MemoryRouter>
  );
}

describe('useLens', () => {
  it('defaults to county_planner when ?lens is absent', () => {
    const { result } = renderHook(() => useLens(), { wrapper: wrapper('/') });
    expect(result.current.lensId).toBe(DEFAULT_LENS_ID);
  });

  it('reads a non-default lens from ?lens=', () => {
    const { result } = renderHook(() => useLens(), {
      wrapper: wrapper('/?lens=equity'),
    });
    expect(result.current.lensId).toBe('equity');
  });

  it('setLens(default) removes the lens query param', () => {
    const { result } = renderHook(
      () => {
        const lens = useLens();
        const loc = useLocation();
        return { ...lens, loc };
      },
      { wrapper: wrapper('/?lens=equity') }
    );
    act(() => result.current.setLens(DEFAULT_LENS_ID));
    expect(result.current.lensId).toBe(DEFAULT_LENS_ID);
    expect(result.current.loc.search).not.toContain('lens=');
  });
});
