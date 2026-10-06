// Viewer / Analyst switch in the header. Used in the prototype to prove A-19 — a Viewer
// never sees a held finding, in any form (row, count, badge, colour). In production this
// comes from the signed-in user's role (K-11); the switch here is a demo-only convenience.

import { useRoleStore } from './useRole';

export function RoleSwitch() {
  const role = useRoleStore((s) => s.role);
  const setRole = useRoleStore((s) => s.setRole);

  return (
    <div className="flex items-center gap-2 text-sm">
      <label htmlFor="role-switch" className="text-neutral-600">
        Role
      </label>
      <select
        id="role-switch"
        value={role}
        onChange={(e) => setRole(e.target.value === 'analyst' ? 'analyst' : 'viewer')}
        className="rounded border border-neutral-300 bg-white px-2 py-1 text-sm"
      >
        <option value="viewer">Viewer</option>
        <option value="analyst">Analyst</option>
      </select>
    </div>
  );
}
