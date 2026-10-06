// MapLibre map. Shows the Protomaps basemap plus one vector source for scored entities,
// coloured by colour_class. Click a point → URL sets ?entity=<id>, which opens the panel.
//
// This component owns the raw map lifecycle: it creates a Map on mount, tears it down on
// unmount, and reconciles the entities source URL whenever the lens changes.

import { useEffect, useRef } from 'react';
import maplibregl from 'maplibre-gl';
import 'maplibre-gl/dist/maplibre-gl.css';
import { registerPmtilesProtocol } from './registerPmtiles';
import { buildBaseStyle } from './style/baseStyle';
import {
  circleColorExpression,
  circleRadiusExpression,
  circleStrokeColorExpression,
  circleStrokeWidthExpression,
} from './style/scale';
import { useSelectedEntity } from '@/panel/useSelectedEntity';

const ENTITIES_SOURCE_ID = 'entities-src';
const WATER_POINTS_LAYER_ID = 'water-points';
const ROAD_SEGMENTS_LAYER_ID = 'road-segments';
const CLICKABLE_LAYER_IDS = [WATER_POINTS_LAYER_ID, ROAD_SEGMENTS_LAYER_ID];

// Nairobi centre; the user can pan anywhere.
const INITIAL_CENTER: [number, number] = [36.82, -1.29];
const INITIAL_ZOOM = 11;

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

    map.on('load', () => {
      map.addSource(ENTITIES_SOURCE_ID, {
        type: 'vector',
        tiles: [`${window.location.origin}/tiles/{z}/{x}/{y}.mvt?lens=${encodeURIComponent(lens)}`],
        minzoom: 0,
        maxzoom: 18,
        promoteId: 'id',
      });

      // Road segments go first so points sit on top of lines.
      map.addLayer({
        id: ROAD_SEGMENTS_LAYER_ID,
        type: 'line',
        source: ENTITIES_SOURCE_ID,
        'source-layer': 'entities',
        filter: ['==', ['get', 'entity_type'], 'road_segment'],
        paint: {
          'line-color': circleColorExpression(),
          'line-width': ['interpolate', ['linear'], ['zoom'], 10, 2, 14, 4, 18, 7],
          'line-opacity': 0.9,
        },
        layout: { 'line-cap': 'round', 'line-join': 'round' },
      });

      map.addLayer({
        id: WATER_POINTS_LAYER_ID,
        type: 'circle',
        source: ENTITIES_SOURCE_ID,
        'source-layer': 'entities',
        filter: ['==', ['get', 'entity_type'], 'water_point'],
        paint: {
          'circle-radius': circleRadiusExpression(),
          'circle-color': circleColorExpression(),
          'circle-stroke-color': circleStrokeColorExpression(),
          'circle-stroke-width': circleStrokeWidthExpression(),
          'circle-opacity': 0.95,
        },
      });

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
          layers: CLICKABLE_LAYER_IDS,
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
    const nextTiles = [
      `${window.location.origin}/tiles/{z}/{x}/{y}.mvt?lens=${encodeURIComponent(lens)}`,
    ];
    (source as maplibregl.VectorTileSource).setTiles(nextTiles);
  }, [lens]);

  return <div ref={container} className="h-full w-full" aria-label="Map of Nairobi" />;
}
