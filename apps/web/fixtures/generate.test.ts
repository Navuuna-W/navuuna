// Fixtures must be deterministic and cover every status the UI renders. These checks run
// in CI and catch an accidental seed change.

import { describe, it, expect } from 'vitest';
import { fixtures } from './index';

describe('fixtures', () => {
  it('contains 200 water points plus 1 road segment', () => {
    const { entities } = fixtures();
    const waterPoints = entities.filter((e) => e.detail.entity_type === 'water_point');
    const roads = entities.filter((e) => e.detail.entity_type === 'road_segment');
    expect(waterPoints).toHaveLength(200);
    expect(roads).toHaveLength(1);
  });

  it('includes 20 cannot_assess entities and 30 provisional entities', () => {
    const { entities } = fixtures();
    const waterPoints = entities.filter((e) => e.detail.entity_type === 'water_point');
    const cannotAssess = waterPoints.filter((e) => e.detail.gate_status === 'cannot_assess');
    const provisional = waterPoints.filter((e) => e.detail.gate_status === 'provisional');
    expect(cannotAssess).toHaveLength(20);
    expect(provisional).toHaveLength(30);
  });

  it('has at least one sub-variable with status null_not_measured + a reason', () => {
    const { entities } = fixtures();
    const anyNull = entities
      .flatMap((e) => e.detail.sub_variables)
      .find((s) => s.status === 'null_not_measured');
    expect(anyNull).toBeDefined();
    expect(anyNull?.null_reason).toBeTruthy();
    expect(anyNull?.score).toBeNull();
  });

  it('has exactly 3 published findings and exactly 1 held finding', () => {
    const { entities } = fixtures();
    const findings = entities.flatMap((e) => e.detail.findings);
    const published = findings.filter((f) => f.state === 'published');
    const held = findings.filter((f) => f.state === 'held');
    expect(published).toHaveLength(3);
    expect(held).toHaveLength(1);
  });

  it('is deterministic — two calls return the same identities and gate statuses', () => {
    const { entities: a } = fixtures();
    const { entities: b } = fixtures();
    expect(b).toHaveLength(a.length);
    for (let i = 0; i < a.length; i++) {
      const ai = a[i];
      const bi = b[i];
      if (!ai || !bi) throw new Error('index out of range');
      expect(bi.detail.id).toBe(ai.detail.id);
      expect(bi.detail.gate_status).toBe(ai.detail.gate_status);
    }
  });
});
