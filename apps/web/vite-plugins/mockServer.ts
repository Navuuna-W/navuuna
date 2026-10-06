// Dev-only Vite plugin. Serves two routes against the deterministic fixtures:
//
//   GET /tiles/{z}/{x}/{y}.mvt?lens=county_planner
//     Vector tiles encoded with vt-pbf; layer name "entities".
//     Per-feature properties: id, entity_type, module, name, lens_score,
//     colour_class, coverage, confidence.
//
//   GET /api/v1/entities/{id}?role=viewer|analyst
//     Returns the EntityDetail JSON. For role=viewer (the default), held and
//     explanation_checked findings are stripped (DEC-08, A-19).
//
// This file lives under vite-plugins/ and is imported by vite.config.ts. Vite only loads
// config files in Node, so the mock, the fixtures and vt-pbf never enter the client bundle
// built by `vite build`. The hooks are attached in both configureServer and
// configurePreviewServer so `npm run preview` works offline too (Step 10).

import type { Connect, Plugin, PreviewServer, ViteDevServer } from 'vite';
import type { ServerResponse } from 'node:http';
import GeoJSONVT from 'geojson-vt';
import vtpbf from 'vt-pbf';
import { fixtures, type EntityDetail, type FixtureEntity } from '../fixtures';
import {
  applyLens,
  COUNTY_PLANNER_LENS,
  type ColourClass,
  type LensConfig,
} from '../fixtures/lensApply';

interface MockFeature {
  type: 'Feature';
  id: string;
  geometry:
    | { type: 'Point'; coordinates: [number, number] }
    | { type: 'LineString'; coordinates: [number, number][] };
  properties: {
    id: string;
    entity_type: string;
    module: string;
    name: string;
    lens_score: number | null;
    colour_class: ColourClass;
    coverage: number;
    confidence: number;
  };
}

interface FeatureCollection {
  type: 'FeatureCollection';
  features: MockFeature[];
}

const tileIndexCache = new Map<string, GeoJSONVT>();

function buildFeatureCollection(
  entities: readonly FixtureEntity[],
  lens: LensConfig
): FeatureCollection {
  return {
    type: 'FeatureCollection',
    features: entities.map((e) => {
      const isPartlyVerified =
        e.detail.gate_status === 'provisional' ||
        e.detail.variables.some((v) => v.status === 'partly_verified');
      const { lens_score, colour_class } = applyLens(e.detail.variables, lens, isPartlyVerified);

      const coverage = round(
        e.detail.variables.reduce((s, v) => s + v.coverage, 0) / e.detail.variables.length,
        2
      );
      const confidence = round(
        e.detail.variables.reduce((s, v) => s + v.confidence, 0) / e.detail.variables.length,
        2
      );

      return {
        type: 'Feature',
        id: e.detail.id,
        geometry: e.geometry as MockFeature['geometry'],
        properties: {
          id: e.detail.id,
          entity_type: e.detail.entity_type,
          module: e.detail.module,
          name: e.detail.name,
          lens_score,
          colour_class,
          coverage,
          confidence,
        },
      };
    }),
  };
}

function round(n: number, places: number): number {
  const f = Math.pow(10, places);
  return Math.round(n * f) / f;
}

function getTileIndex(lensId: string): GeoJSONVT {
  const cached = tileIndexCache.get(lensId);
  if (cached) return cached;

  const lens = lensId === COUNTY_PLANNER_LENS.id ? COUNTY_PLANNER_LENS : COUNTY_PLANNER_LENS;
  const { entities } = fixtures();
  const fc = buildFeatureCollection(entities, lens);
  const index = new GeoJSONVT(fc as never, {
    maxZoom: 18,
    tolerance: 3,
    extent: 4096,
    buffer: 64,
    debug: 0,
    indexMaxZoom: 5,
    indexMaxPoints: 100000,
    generateId: false,
    promoteId: 'id',
  });
  tileIndexCache.set(lensId, index);
  return index;
}

function stripHeldFindings(detail: EntityDetail): EntityDetail {
  return {
    ...detail,
    findings: detail.findings.filter(
      (f) => f.state !== 'held' && f.state !== 'explanation_checked'
    ),
  };
}

function handleEntityRequest(res: ServerResponse, id: string, roleParam: string | null): void {
  const { byId } = fixtures();
  const entity = byId.get(id);
  if (!entity) {
    res.statusCode = 404;
    res.setHeader('Content-Type', 'application/json');
    res.end(JSON.stringify({ title: 'Not Found', status: 404 }));
    return;
  }

  const role = roleParam === 'analyst' || roleParam === 'admin' ? roleParam : 'viewer';
  const payload: EntityDetail =
    role === 'viewer' ? stripHeldFindings(entity.detail) : entity.detail;

  res.statusCode = 200;
  res.setHeader('Content-Type', 'application/json; charset=utf-8');
  res.setHeader('Cache-Control', 'no-store');
  res.end(JSON.stringify(payload));
}

function handleTileRequest(
  res: ServerResponse,
  z: number,
  x: number,
  y: number,
  lensId: string
): void {
  const index = getTileIndex(lensId);
  const tile = index.getTile(z, x, y);
  if (!tile) {
    res.statusCode = 204;
    res.end();
    return;
  }
  const buffer = vtpbf.fromGeojsonVt({ entities: tile });
  res.statusCode = 200;
  res.setHeader('Content-Type', 'application/vnd.mapbox-vector-tile');
  res.setHeader('Cache-Control', 'no-store');
  res.setHeader('Content-Length', String(buffer.byteLength));
  res.end(Buffer.from(buffer));
}

const ENTITY_RE = /^\/api\/v1\/entities\/([a-zA-Z0-9_-]+)\/?$/;
const TILE_RE = /^\/tiles\/(\d+)\/(\d+)\/(\d+)\.mvt$/;

const middleware: Connect.NextHandleFunction = (req, res, next) => {
  if (!req.url) {
    next();
    return;
  }
  const url = new URL(req.url, 'http://local');

  const entityMatch = ENTITY_RE.exec(url.pathname);
  if (entityMatch && req.method === 'GET') {
    const [, id] = entityMatch;
    if (!id) {
      next();
      return;
    }
    const role = url.searchParams.get('role');
    handleEntityRequest(res, id, role);
    return;
  }

  const tileMatch = TILE_RE.exec(url.pathname);
  if (tileMatch && req.method === 'GET') {
    const z = Number(tileMatch[1]);
    const x = Number(tileMatch[2]);
    const y = Number(tileMatch[3]);
    const lens = url.searchParams.get('lens') ?? COUNTY_PLANNER_LENS.id;
    handleTileRequest(res, z, x, y, lens);
    return;
  }

  next();
};

export function mockServer(): Plugin {
  return {
    name: 'navuuna:mock-server',
    configureServer(server: ViteDevServer) {
      server.middlewares.use(middleware);
    },
    configurePreviewServer(server: PreviewServer) {
      server.middlewares.use(middleware);
    },
  };
}
