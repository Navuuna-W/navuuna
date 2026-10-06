// Fixture-mode-only "Preview as:" switcher. In production the role comes from the
// signed-in session (K-11) and the client never asks for one — the API filters findings
// server-side by role (A-19, DEC-08). To make that impossible to misuse, this switcher
// renders nothing when VITE_DATA_SOURCE is not 'fixture'.

import { useRoleStore } from './useRole';

export function RoleSwitch() {
  const role = useRoleStore((s) => s.role);
  const setRole = useRoleStore((s) => s.setRole);

  const source = import.meta.env.VITE_DATA_SOURCE ?? 'fixture';
  if (source !== 'fixture') return null;

  return (
    <div className="flex items-center gap-2 text-sm">
      <label htmlFor="role-switch" className="text-neutral-600">
        Preview as:
      </label>
      <select
        id="role-switch"
        value={role}
        onChange={(e) => setRole(e.target.value === 'analyst' ? 'analyst' : 'viewer')}
        className="rounded border border-neutral-300 bg-white px-2 py-1 text-sm"
        aria-label="Preview role selector"
      >
        <option value="viewer">Viewer</option>
        <option value="analyst">Analyst</option>
      </select>
    </div>
  );
}
