// Fixtures must be deterministic and cover every status the UI renders. These checks run
// in CI and catch an accidental seed change.

import { describe, it, expect } from 'vitest';
import { fixtures } from './index';
import { findingsVisibleTo } from '../src/findings/publicFindingStates';

describe('fixtures', () => {
  it('contains 200 water points plus 3 road segments', () => {
    const { entities } = fixtures();
    const waterPoints = entities.filter((e) => e.detail.entity_type === 'water_point');
    const roads = entities.filter((e) => e.detail.entity_type === 'road_segment');
    expect(waterPoints).toHaveLength(200);
    expect(roads).toHaveLength(3);
  });

  it('roads cover measured, provisional and cannot_assess so no road layer stays empty (B2)', () => {
    const { entities } = fixtures();
    const roads = entities.filter((e) => e.detail.entity_type === 'road_segment');
    const gates = new Set(roads.map((r) => r.detail.gate_status));
    expect(gates).toEqual(new Set(['measured', 'provisional', 'cannot_assess']));
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

  it('has exactly 3 published, 1 held and 1 contested finding', () => {
    const { entities } = fixtures();
    const findings = entities.flatMap((e) => e.detail.findings);
    const published = findings.filter((f) => f.state === 'published');
    const held = findings.filter((f) => f.state === 'held');
    const contested = findings.filter((f) => f.state === 'contested');
    expect(published).toHaveLength(3);
    expect(held).toHaveLength(1);
    expect(contested).toHaveLength(1);
  });

  // C10: the held and contested fixtures exist for the analyst review screens. If either
  // ever survives the viewer filter, a viewer sees a finding production would hide.
  it('puts no analyst-only finding in front of a viewer', () => {
    const { entities } = fixtures();

    const visibleToViewer = entities.flatMap((e) => findingsVisibleTo(e.detail.findings, 'viewer'));

    expect(visibleToViewer).toHaveLength(3);
    expect(visibleToViewer.every((f) => f.state === 'published')).toBe(true);
  });

  it('keeps all five findings for an analyst', () => {
    const { entities } = fixtures();

    const visibleToAnalyst = entities.flatMap((e) =>
      findingsVisibleTo(e.detail.findings, 'analyst')
    );

    expect(visibleToAnalyst).toHaveLength(5);
  });

  it('carries all 29 canonical sub-variables on every entity', () => {
    const { entities } = fixtures();
    for (const e of entities) {
      expect(e.detail.sub_variables).toHaveLength(29);
    }
  });

  it('a water_point entity measures only the eight canonical water sub-variables', () => {
    const { entities } = fixtures();
    const wp = entities.find(
      (e) => e.detail.entity_type === 'water_point' && e.detail.gate_status === 'measured'
    );
    if (!wp) throw new Error('no measured water point');
    const notPartOfWater = wp.detail.sub_variables.filter(
      (s) => s.null_reason === 'not part of the water module yet'
    );
    // 29 total - 8 measured by the water module = 21 unmodelled rows per water entity.
    expect(notPartOfWater).toHaveLength(21);
  });

  it('X-of-Y rolls up only weighted contributors — gates and the guard are excluded', () => {
    const { entities } = fixtures();
    const measured = entities.find(
      (e) => e.detail.entity_type === 'water_point' && e.detail.gate_status === 'measured'
    );
    if (!measured) throw new Error('no measured water point');
    const expected = { V1: 4, V2: 4, V3: 6, V4: 6, V5: 6 } as const;
    for (const v of measured.detail.variables) {
      expect(v.total_count, `${v.variable} total_count`).toBe(expected[v.variable]);
    }
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
