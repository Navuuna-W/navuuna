#!/usr/bin/env bash
# Fetches the pmtiles CLI (if missing) and extracts a Nairobi-only PMTiles file from the
# most recent Protomaps daily build. Output: apps/web/public/basemap/nairobi.pmtiles.
#
# Why self-hosted: Bible §8 and ADR-002. At runtime no third-party host is contacted; the
# .pmtiles file is served by Vite in dev and by nginx in production (K-07 uses HTTP range
# requests). The file is > 1 MB, so it is .gitignored (Bible §14.3) and rebuilt on demand.
#
# Lookup: the Protomaps builds live at https://build.protomaps.com/YYYYMMDD.pmtiles with no
# directory listing. We probe today and the preceding days with HEAD requests until the
# first 200 wins. Override with PMTILES_BUILD_URL to pin a specific build.

set -euo pipefail

HERE="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
WEB_ROOT="$(cd "$HERE/.." && pwd)"
TOOLS_DIR="$WEB_ROOT/tools"
OUT_DIR="$WEB_ROOT/public/basemap"
OUT_FILE="$OUT_DIR/nairobi.pmtiles"

# Nairobi County bbox plus a margin. Tightened later from the IEBC boundary.
NAIROBI_BBOX="36.60,-1.50,37.15,-1.10"
MAX_ZOOM="15"

# pmtiles CLI version pinned so builds are reproducible.
PMTILES_VERSION="v1.31.2"
PMTILES_LOOKBACK_DAYS="14"

mkdir -p "$TOOLS_DIR" "$OUT_DIR"

# ---------- 1. Make sure the pmtiles CLI is available ----------

uname_s="$(uname -s)"
uname_m="$(uname -m)"
case "$uname_s" in
  Linux*)   PMTILES_OS="Linux";;
  Darwin*)  PMTILES_OS="Darwin";;
  MINGW*|MSYS*|CYGWIN*) PMTILES_OS="Windows";;
  *) echo "Unsupported OS: $uname_s" >&2; exit 1;;
esac
case "$uname_m" in
  x86_64|amd64) PMTILES_ARCH="x86_64";;
  arm64|aarch64) PMTILES_ARCH="arm64";;
  *) echo "Unsupported arch: $uname_m" >&2; exit 1;;
esac

if [ "$PMTILES_OS" = "Windows" ]; then
  PMTILES_BIN="$TOOLS_DIR/pmtiles.exe"
  PMTILES_ASSET="go-pmtiles_${PMTILES_VERSION#v}_${PMTILES_OS}_${PMTILES_ARCH}.zip"
else
  PMTILES_BIN="$TOOLS_DIR/pmtiles"
  PMTILES_ASSET="go-pmtiles_${PMTILES_VERSION#v}_${PMTILES_OS}_${PMTILES_ARCH}.tar.gz"
fi

if [ ! -x "$PMTILES_BIN" ]; then
  echo "pmtiles CLI not found. Downloading $PMTILES_VERSION ($PMTILES_OS/$PMTILES_ARCH)..."
  PMTILES_URL="https://github.com/protomaps/go-pmtiles/releases/download/${PMTILES_VERSION}/${PMTILES_ASSET}"
  TMP="$TOOLS_DIR/_pmtiles_dl"
  rm -rf "$TMP"
  mkdir -p "$TMP"
  curl -fsSL "$PMTILES_URL" -o "$TMP/$PMTILES_ASSET"
  if [ "$PMTILES_OS" = "Windows" ]; then
    (cd "$TMP" && unzip -q "$PMTILES_ASSET")
  else
    (cd "$TMP" && tar -xzf "$PMTILES_ASSET")
  fi
  # The archive flattens into the tmp dir; copy the binary into tools/.
  if [ "$PMTILES_OS" = "Windows" ]; then
    cp "$TMP/pmtiles.exe" "$PMTILES_BIN"
  else
    cp "$TMP/pmtiles" "$PMTILES_BIN"
    chmod +x "$PMTILES_BIN"
  fi
  rm -rf "$TMP"
fi

echo "Using $($PMTILES_BIN version 2>&1 | head -n 1)"

# ---------- 2. Pick the Protomaps build URL ----------

if [ -n "${PMTILES_BUILD_URL:-}" ]; then
  BUILD_URL="$PMTILES_BUILD_URL"
  echo "Using PMTILES_BUILD_URL override: $BUILD_URL"
else
  BUILD_URL=""
  echo "Probing recent Protomaps daily builds..."
  for i in $(seq 0 "$PMTILES_LOOKBACK_DAYS"); do
    # GNU date and BSD date differ; try both.
    if date -d "today -$i days" +%Y%m%d >/dev/null 2>&1; then
      D="$(date -d "today -$i days" +%Y%m%d)"
    else
      D="$(date -v "-${i}d" +%Y%m%d)"
    fi
    CANDIDATE="https://build.protomaps.com/${D}.pmtiles"
    status="$(curl -s -o /dev/null -w '%{http_code}' -I --max-time 15 "$CANDIDATE" || true)"
    echo "  $D -> HTTP $status"
    if [ "$status" = "200" ]; then
      BUILD_URL="$CANDIDATE"
      break
    fi
  done
  if [ -z "$BUILD_URL" ]; then
    echo "FAILED: no Protomaps build responded in the last $PMTILES_LOOKBACK_DAYS days." >&2
    echo "Set PMTILES_BUILD_URL to a known-good URL and rerun." >&2
    exit 1
  fi
fi

echo "Extracting Nairobi from $BUILD_URL -> $OUT_FILE"
echo "  bbox=$NAIROBI_BBOX maxzoom=$MAX_ZOOM"

# ---------- 3. Extract ----------

"$PMTILES_BIN" extract "$BUILD_URL" "$OUT_FILE" \
  --bbox="$NAIROBI_BBOX" \
  --maxzoom="$MAX_ZOOM"

# ---------- 4. Record the URL used so the README stays truthful ----------

RECORD_FILE="$OUT_DIR/BUILD_INFO.txt"
{
  echo "nairobi.pmtiles built $(date -u +%Y-%m-%dT%H:%M:%SZ)"
  echo "source=$BUILD_URL"
  echo "bbox=$NAIROBI_BBOX"
  echo "maxzoom=$MAX_ZOOM"
  echo "pmtiles_cli=$PMTILES_VERSION"
} > "$RECORD_FILE"

echo
echo "Done."
echo "  Output: $OUT_FILE"
echo "  Record: $RECORD_FILE"
