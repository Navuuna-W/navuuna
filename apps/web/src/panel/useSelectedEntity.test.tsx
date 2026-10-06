// The selected entity must round-trip through the URL: open sets ?entity=; close removes
// it; refresh preserves it. These checks lock the contract that the whole frontend relies
// on (lens switch, deep links, back/forward buttons).

import { describe, it, expect } from 'vitest';
import { renderHook, act } from '@testing-library/react';
import { MemoryRouter } from 'react-router-dom';
import type { ReactNode } from 'react';
import { useSelectedEntity } from './useSelectedEntity';

function wrapper(initial: string) {
  return ({ children }: { children: ReactNode }) => (
    <MemoryRouter initialEntries={[initial]}>{children}</MemoryRouter>
  );
}

describe('useSelectedEntity', () => {
  it('returns null when ?entity is absent', () => {
    const { result } = renderHook(() => useSelectedEntity(), { wrapper: wrapper('/') });
    expect(result.current.entityId).toBeNull();
  });

  it('reads the current entity from ?entity=', () => {
    const { result } = renderHook(() => useSelectedEntity(), {
      wrapper: wrapper('/?entity=wp-042'),
    });
    expect(result.current.entityId).toBe('wp-042');
  });

  it('openEntity sets ?entity=', () => {
    const { result } = renderHook(() => useSelectedEntity(), { wrapper: wrapper('/') });
    act(() => result.current.openEntity('wp-007'));
    expect(result.current.entityId).toBe('wp-007');
  });

  it('closeEntity removes ?entity=', () => {
    const { result } = renderHook(() => useSelectedEntity(), {
      wrapper: wrapper('/?entity=wp-007'),
    });
    act(() => result.current.closeEntity());
    expect(result.current.entityId).toBeNull();
  });
});
