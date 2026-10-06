#!/usr/bin/env bash
# Copies the Protomaps glyphs (fonts) and sprites into apps/web/public/basemap/ so the
# runtime pulls them from our own origin (no third-party font or sprite host).
#
# Source: https://github.com/protomaps/basemaps-assets (NPM-published too, but a shallow
# clone keeps the files out of node_modules and makes the licence obvious).

set -euo pipefail

HERE="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
WEB_ROOT="$(cd "$HERE/.." && pwd)"
TOOLS_DIR="$WEB_ROOT/tools"
ASSETS_SRC="$TOOLS_DIR/basemaps-assets"
OUT_DIR="$WEB_ROOT/public/basemap"

mkdir -p "$TOOLS_DIR" "$OUT_DIR"

# Always clone fresh. The previous "update" branch used `cd && git reset --hard` which
# on Windows Git Bash with a missing .git/ inside the clone directory left the reset
# running against the parent repo (wrecking the current branch). The clone is small
# (fonts + sprites, ~tens of MB) so a fresh download is fine.
echo "Cloning protomaps/basemaps-assets (shallow, fresh)..."
rm -rf "$ASSETS_SRC"
git clone --depth 1 https://github.com/protomaps/basemaps-assets.git "$ASSETS_SRC"

# Fonts: /basemap/fonts/{fontstack}/{range}.pbf  (MapLibre glyphs URL template)
if [ -d "$ASSETS_SRC/fonts" ]; then
  echo "Copying fonts..."
  rm -rf "$OUT_DIR/fonts"
  cp -R "$ASSETS_SRC/fonts" "$OUT_DIR/fonts"
else
  echo "WARN: no fonts/ dir in basemaps-assets — labels will not render." >&2
fi

# Sprites: /basemap/sprites/light.{json,png} and @2x variants
if [ -d "$ASSETS_SRC/sprites" ]; then
  echo "Copying sprites..."
  rm -rf "$OUT_DIR/sprites"
  cp -R "$ASSETS_SRC/sprites" "$OUT_DIR/sprites"
else
  echo "WARN: no sprites/ dir in basemaps-assets." >&2
fi

echo
echo "Done. Served at /basemap/fonts/ and /basemap/sprites/."
