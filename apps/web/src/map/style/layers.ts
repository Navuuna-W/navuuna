// MapLibre layer specs for the entities vector source. Exported as pure data so the
// style-spec validator in scale.test.ts can prove they parse before CI (and the browser)
// ever sees them. Road rendering splits across three layers because line-dasharray
// cannot be data-driven in MapLibre 4.x's style spec 20.x; each layer has a constant
// dasharray and a filter that selects the colour_class values it owns.

import type { LayerSpecification } from 'maplibre-gl';
import {
  COLOUR_FOR,
  circleColorExpression,
  circleRadiusExpression,
  circleStrokeColorExpression,
  circleStrokeWidthExpression,
  partlyVerifiedLineColorExpression,
  solidLineColorExpression,
} from './scale';

export const ENTITIES_SOURCE_ID = 'entities-src';
const SRC_LAYER = 'entities';

export const WATER_POINTS_LAYER_ID = 'water-points';
export const ROAD_SEGMENTS_SOLID_LAYER_ID = 'road-segments-solid';
export const ROAD_SEGMENTS_PARTLY_VERIFIED_LAYER_ID = 'road-segments-partly-verified';
export const ROAD_SEGMENTS_CANNOT_ASSESS_LAYER_ID = 'road-segments-cannot-assess';

// Every entity layer the user can click. Drives click-to-open, map-click-closes-panel,
// and the __NAVUUNA_TEST__ feature-count hook in MapView.
export const CLICKABLE_LAYER_IDS: readonly string[] = [
  ROAD_SEGMENTS_SOLID_LAYER_ID,
  ROAD_SEGMENTS_PARTLY_VERIFIED_LAYER_ID,
  ROAD_SEGMENTS_CANNOT_ASSESS_LAYER_ID,
  WATER_POINTS_LAYER_ID,
];

const SOLID_BANDS = ['b1', 'b2', 'b3', 'b4'];
const PARTLY_VERIFIED_BANDS = ['b1p', 'b2p', 'b3p', 'b4p'];
const ALL_KNOWN = [...SOLID_BANDS, ...PARTLY_VERIFIED_BANDS];

// Roads first (lines), points on top. Within roads: solid > partly-verified > cannot-assess.
export const ROAD_SEGMENT_LAYERS: LayerSpecification[] = [
  {
    id: ROAD_SEGMENTS_SOLID_LAYER_ID,
    type: 'line',
    source: ENTITIES_SOURCE_ID,
    'source-layer': SRC_LAYER,
    filter: [
      'all',
      ['==', ['get', 'entity_type'], 'road_segment'],
      ['in', ['get', 'colour_class'], ['literal', SOLID_BANDS]],
    ],
    paint: {
      'line-color': solidLineColorExpression(),
      'line-width': ['interpolate', ['linear'], ['zoom'], 10, 2, 14, 4, 18, 7],
      'line-opacity': 0.95,
    },
    layout: { 'line-cap': 'round', 'line-join': 'round' },
  },
  {
    id: ROAD_SEGMENTS_PARTLY_VERIFIED_LAYER_ID,
    type: 'line',
    source: ENTITIES_SOURCE_ID,
    'source-layer': SRC_LAYER,
    filter: [
      'all',
      ['==', ['get', 'entity_type'], 'road_segment'],
      ['in', ['get', 'colour_class'], ['literal', PARTLY_VERIFIED_BANDS]],
    ],
    paint: {
      'line-color': partlyVerifiedLineColorExpression(),
      'line-width': ['interpolate', ['linear'], ['zoom'], 10, 2, 14, 4, 18, 7],
      'line-opacity': 0.95,
      'line-dasharray': [2, 2],
    },
    layout: { 'line-cap': 'butt', 'line-join': 'round' },
  },
  {
    // Explicit `ca` OR any class the solid / partly-verified filters do not own, so an
    // unknown or future colour_class never leaves a road invisible.
    id: ROAD_SEGMENTS_CANNOT_ASSESS_LAYER_ID,
    type: 'line',
    source: ENTITIES_SOURCE_ID,
    'source-layer': SRC_LAYER,
    filter: [
      'all',
      ['==', ['get', 'entity_type'], 'road_segment'],
      [
        'any',
        ['==', ['get', 'colour_class'], 'ca'],
        ['!', ['in', ['get', 'colour_class'], ['literal', ALL_KNOWN]]],
      ],
    ],
    paint: {
      'line-color': COLOUR_FOR.ca,
      'line-width': ['interpolate', ['linear'], ['zoom'], 10, 2, 14, 3, 18, 5],
      'line-opacity': 0.9,
      'line-dasharray': [1, 2],
    },
    layout: { 'line-cap': 'butt', 'line-join': 'round' },
  },
];

export const WATER_POINTS_LAYER: LayerSpecification = {
  id: WATER_POINTS_LAYER_ID,
  type: 'circle',
  source: ENTITIES_SOURCE_ID,
  'source-layer': SRC_LAYER,
  filter: ['==', ['get', 'entity_type'], 'water_point'],
  paint: {
    'circle-radius': circleRadiusExpression(),
    'circle-color': circleColorExpression(),
    'circle-stroke-color': circleStrokeColorExpression(),
    'circle-stroke-width': circleStrokeWidthExpression(),
    'circle-opacity': 0.95,
  },
};

export const ENTITY_LAYERS: LayerSpecification[] = [...ROAD_SEGMENT_LAYERS, WATER_POINTS_LAYER];
