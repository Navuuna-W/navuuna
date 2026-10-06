// Role for the current viewer. Default is 'viewer'; Step 7 adds a header switcher that
// stores the choice in a tiny Zustand store (session-only, no persistence).
//
// Why a store instead of context: it keeps the panel invariance test (Step 8) honest —
// the panel re-queries when role changes but not when lens changes.

import { create } from 'zustand';

export type Role = 'viewer' | 'analyst';

interface RoleStore {
  role: Role;
  setRole: (role: Role) => void;
}

export const useRoleStore = create<RoleStore>((set) => ({
  role: 'viewer',
  setRole: (role) => set({ role }),
}));

export function useRole(): Role {
  return useRoleStore((s) => s.role);
}
