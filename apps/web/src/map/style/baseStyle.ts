// Builds the MapLibre style object for the base map.
//
// Uses @protomaps/basemaps to produce the light flavour style, rewrites it so glyphs,
// sprites and the PMTiles source all point at our own /basemap/ path. Nothing in this file
// contacts a third-party host at runtime.

import { layers as protomapsLayers, namedFlavor } from '@protomaps/basemaps';
import type { StyleSpecification } from 'maplibre-gl';

export const BASEMAP_PMTILES_URL = '/basemap/nairobi.pmtiles';
export const BASEMAP_GLYPHS_URL = '/basemap/fonts/{fontstack}/{range}.pbf';
export const BASEMAP_SPRITE_URL = '/basemap/sprites/light';

const BASEMAP_SOURCE_ID = 'protomaps';

export function buildBaseStyle(): StyleSpecification {
  return {
    version: 8,
    glyphs: BASEMAP_GLYPHS_URL,
    sprite: BASEMAP_SPRITE_URL,
    sources: {
      [BASEMAP_SOURCE_ID]: {
        type: 'vector',
        url: `pmtiles://${BASEMAP_PMTILES_URL}`,
        attribution:
          '© <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors',
      },
    },
    layers: protomapsLayers(BASEMAP_SOURCE_ID, namedFlavor('light'), { lang: 'en' }),
  };
}
