// Memoised, process-wide fixture bundle. Both the dev mock and any Node-side script that
// needs the data call fixtures() and get the same values back.

import { generateFixtures } from './generate';
import type { FixtureEntity } from './types';

let cached: ReturnType<typeof generateFixtures> | null = null;

export function fixtures(): { entities: FixtureEntity[]; byId: Map<string, FixtureEntity> } {
  if (!cached) cached = generateFixtures();
  return cached;
}

export type { FixtureEntity } from './types';
export type { EntityDetail, FindingSummary, VariableScore, SubVariableScore } from './types';
