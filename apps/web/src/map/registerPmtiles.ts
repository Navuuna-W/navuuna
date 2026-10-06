// Registers the pmtiles:// protocol with MapLibre, exactly once per page load.
// Any map that reads tiles from a .pmtiles file must call this before mounting.
//
// Why a module-level flag: StrictMode mounts components twice in dev, and MapLibre errors
// if the same protocol handler is added more than once.

import maplibregl from 'maplibre-gl';
import { Protocol } from 'pmtiles';

let registered = false;

export function registerPmtilesProtocol(): void {
  if (registered) return;
  const protocol = new Protocol();
  maplibregl.addProtocol('pmtiles', protocol.tile);
  registered = true;
}
