// MapLibre map. Shows the Protomaps basemap plus one vector source for scored entities,
// coloured by colour_class. Click an entity → URL sets ?entity=<id>, which opens the
// panel.
//
// This component owns the raw map lifecycle: it creates a Map on mount, tears it down on
// unmount, and reconciles the entities source URL whenever the lens changes.

import { useEffect, useRef } from 'react';
import maplibregl from 'maplibre-gl';
import 'maplibre-gl/dist/maplibre-gl.css';
import { registerPmtilesProtocol } from './registerPmtiles';
import { buildBaseStyle } from './style/baseStyle';
import { CLICKABLE_LAYER_IDS, ENTITIES_SOURCE_ID, ENTITY_LAYERS } from './style/layers';
import { useSelectedEntity } from '@/panel/useSelectedEntity';

// Nairobi centre; the user can pan anywhere.
const INITIAL_CENTER: [number, number] = [36.82, -1.29];
const INITIAL_ZOOM = 11;

function entitiesTileUrl(lens: string): string {
  return `${window.location.origin}/tiles/{z}/{x}/{y}.mvt?lens=${encodeURIComponent(lens)}`;
}

export function MapView({ lens = 'county_planner' }: { lens?: string }) {
  const container = useRef<HTMLDivElement | null>(null);
  const mapRef = useRef<maplibregl.Map | null>(null);
  const { openEntity, closeEntity } = useSelectedEntity();

  useEffect(() => {
    if (!container.current) return;
    registerPmtilesProtocol();

    const map = new maplibregl.Map({
      container: container.current,
      style: buildBaseStyle(),
      center: INITIAL_CENTER,
      zoom: INITIAL_ZOOM,
      attributionControl: false,
    });
    mapRef.current = map;

    // Fixture-mode-only test hook: installed BEFORE 'load' so Playwright can see 'idle'
    // events even when the basemap style fails to load (e.g. fresh clone without
    // `npm run basemap`). Never exposed in api mode.
    if (import.meta.env.VITE_DATA_SOURCE === 'fixture' || !import.meta.env.VITE_DATA_SOURCE) {
      map.on('idle', () => {
        const feats = map.queryRenderedFeatures(undefined, {
          layers: CLICKABLE_LAYER_IDS.filter((id) => map.getLayer(id)) as string[],
        });
        (window as unknown as Record<string, unknown>).__NAVUUNA_TEST__ = {
          featureCount: feats.length,
          lastIdle: Date.now(),
        };
      });
    }

    map.on('load', () => {
      map.addSource(ENTITIES_SOURCE_ID, {
        type: 'vector',
        tiles: [entitiesTileUrl(lens)],
        minzoom: 0,
        maxzoom: 18,
        promoteId: 'id',
      });

      // Layer order: roads first (lines), points on top. Within roads: solid, then
      // partly-verified dashed, then cannot-assess dashed.
      for (const layer of ENTITY_LAYERS) {
        map.addLayer(layer);
      }

      for (const layerId of CLICKABLE_LAYER_IDS) {
        map.on('click', layerId, (e) => {
          const feature = e.features?.[0];
          if (!feature) return;
          const id = feature.id ?? feature.properties?.id;
          if (typeof id === 'string') openEntity(id);
        });
        map.on('mouseenter', layerId, () => {
          map.getCanvas().style.cursor = 'pointer';
        });
        map.on('mouseleave', layerId, () => {
          map.getCanvas().style.cursor = '';
        });
      }

      // Clicking the map background closes the panel.
      map.on('click', (e) => {
        const hits = map.queryRenderedFeatures(e.point, {
          layers: CLICKABLE_LAYER_IDS as string[],
        });
        if (hits.length === 0) closeEntity();
      });
    });

    return () => {
      map.remove();
      mapRef.current = null;
    };
    // Intentionally omitting openEntity/closeEntity from deps: they are stable refs from
    // the URL state hook, and we only want to construct the map once.
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, []);

  // When the lens changes, swap the tile URL — never rebuild the map (Step 8 contract).
  useEffect(() => {
    const map = mapRef.current;
    if (!map || !map.isStyleLoaded()) return;
    const source = map.getSource(ENTITIES_SOURCE_ID);
    if (!source || source.type !== 'vector') return;
    (source as maplibregl.VectorTileSource).setTiles([entitiesTileUrl(lens)]);
  }, [lens]);

  return <div ref={container} className="h-full w-full" aria-label="Map of Nairobi" />;
}
