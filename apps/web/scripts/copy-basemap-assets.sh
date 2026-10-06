#!/usr/bin/env bash
# Copies the Protomaps glyphs (fonts) and sprites into apps/web/public/basemap/ so the
# runtime pulls them from our own origin (no third-party font or sprite host).
#
# Source: https://github.com/protomaps/basemaps-assets (NPM-published too, but a shallow
# clone keeps the files out of node_modules and makes the licence obvious).
#
# Safety story. On Windows Git Bash a previous "update" branch did
#   (cd "$ASSETS_SRC" && git fetch && git reset --hard origin/HEAD)
# When the clone's .git was in an unexpected state, Git walked up to the parent and
# reset the Navuuna repo instead, wiping the current branch. The fresh-clone follow-up
# removed the reset but kept the clone under apps/web/tools/, i.e. inside the repo —
# which is still a risk if a later edit reintroduces an `rm -rf` or a `git` call that
# resolves to the Navuuna repo. This version is paranoid:
#   - set -euo pipefail: abort on any error, unset var, or pipeline failure.
#   - clones into a brand-new `mktemp -d` directory outside the repo on every run.
#   - uses `git -C "$TMP" <subcommand>` everywhere, never `cd`, so a Git bug in a
#     subshell cannot escape the clone directory.
#   - verifies the resolved temp dir is outside the Navuuna repo before writing.
#   - traps EXIT so the scratch dir is removed on success or failure.

set -euo pipefail

HERE="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
WEB_ROOT="$(cd "$HERE/.." && pwd)"
REPO_ROOT="$(cd "$WEB_ROOT/../.." && pwd)"
OUT_DIR="$WEB_ROOT/public/basemap"

mkdir -p "$OUT_DIR"

# Fresh scratch dir in the OS temp area. Never inside the repo.
TMP="$(mktemp -d -t navuuna-basemap-assets-XXXXXXXX)"
trap 'rm -rf "$TMP"' EXIT

# Guard: refuse to proceed if the resolved scratch dir is anywhere inside the Navuuna
# repo (defence against surprising mktemp behaviour or symlinks).
case "$TMP" in
  "$REPO_ROOT"|"$REPO_ROOT"/*)
    echo "ABORT: scratch dir $TMP is inside the repo at $REPO_ROOT" >&2
    exit 1
    ;;
esac

CLONE_DIR="$TMP/basemaps-assets"

echo "Cloning protomaps/basemaps-assets (shallow) into $CLONE_DIR ..."
# `git clone` is the only git call that creates its own working tree; it cannot escape.
# From here on we use `git -C` to pin the working directory explicitly.
git clone --depth 1 https://github.com/protomaps/basemaps-assets.git "$CLONE_DIR"

# Sanity check: the clone must have its own .git, so any later git call stays scoped.
if [ ! -d "$CLONE_DIR/.git" ]; then
  echo "ABORT: clone did not produce a .git in $CLONE_DIR" >&2
  exit 1
fi

# Pin a known-good HEAD using git -C, never cd. Not strictly required for a fresh
# shallow clone, but it demonstrates the pattern a future edit must follow.
git -C "$CLONE_DIR" rev-parse --is-inside-work-tree >/dev/null

# Fonts: /basemap/fonts/{fontstack}/{range}.pbf  (MapLibre glyphs URL template)
if [ -d "$CLONE_DIR/fonts" ]; then
  echo "Copying fonts..."
  rm -rf "$OUT_DIR/fonts"
  cp -R "$CLONE_DIR/fonts" "$OUT_DIR/fonts"
else
  echo "WARN: no fonts/ dir in basemaps-assets — labels will not render." >&2
fi

# Sprites: /basemap/sprites/light.{json,png} and @2x variants
if [ -d "$CLONE_DIR/sprites" ]; then
  echo "Copying sprites..."
  rm -rf "$OUT_DIR/sprites"
  cp -R "$CLONE_DIR/sprites" "$OUT_DIR/sprites"
else
  echo "WARN: no sprites/ dir in basemaps-assets." >&2
fi

echo
echo "Done. Served at /basemap/fonts/ and /basemap/sprites/."
