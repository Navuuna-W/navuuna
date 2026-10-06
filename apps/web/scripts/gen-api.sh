#!/usr/bin/env bash
# Regenerates apps/web/src/api/schema.d.ts from the OpenAPI spec.
#
# In dev we read from docs/contracts/openapi.draft.yaml (same repo) so no server is required.
# Later, pointing VITE_OPENAPI_URL at /api/v1/openapi.json (A-06) regenerates from the real
# spec. Either way, the schema is generated and never hand-written (NFR-02, Bible §14.5).

set -euo pipefail

HERE="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
WEB_ROOT="$(cd "$HERE/.." && pwd)"
REPO_ROOT="$(cd "$WEB_ROOT/../.." && pwd)"

SOURCE="${VITE_OPENAPI_URL:-$REPO_ROOT/docs/contracts/openapi.draft.yaml}"
OUT="$WEB_ROOT/src/api/schema.d.ts"

echo "Generating $OUT from $SOURCE"

(cd "$WEB_ROOT" && npx openapi-typescript "$SOURCE" -o "$OUT")

echo "Done."
