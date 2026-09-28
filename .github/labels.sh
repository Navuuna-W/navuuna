#!/usr/bin/env bash
# Creates the issue and PR labels from Bible §14.10 on the GitHub repository.
# Safe to run again: `--force` updates a label that already exists instead of failing.
# Usage: bash .github/labels.sh   (needs the GitHub CLI, logged in with write access)
set -euo pipefail

REPOSITORY="Navuuna-W/navuuna"

# Colours group the labels by kind, so a glance at an issue shows module, layer, priority and owner.
MODULE_COLOUR="1d76db"
LAYER_COLOUR="5319e7"
PRIORITY_COLOUR="d93f0b"
OWNER_COLOUR="0e8a16"

create_label() {
  local name="$1"
  local colour="$2"
  local description="$3"
  gh label create "$name" --repo "$REPOSITORY" --color "$colour" --description "$description" --force
}

# Modules — the same names as the commit scopes in Bible §14.3.
for module in water roads land energy safety; do
  create_label "module:$module" "$MODULE_COLOUR" "Module: $module"
done

# Layers L1–L4 of the scoring model, or the app around it.
for layer in L1 L2 L3 L4 app; do
  create_label "layer:$layer" "$LAYER_COLOUR" "Layer: $layer"
done

# Priority: Must, Should, Could.
create_label "pri:M" "$PRIORITY_COLOUR" "Priority: Must"
create_label "pri:S" "$PRIORITY_COLOUR" "Priority: Should"
create_label "pri:C" "$PRIORITY_COLOUR" "Priority: Could"

# Owner: whose lane the work sits in (CLAUDE.md §3).
create_label "owner:khillon" "$OWNER_COLOUR" "Lane: Core"
create_label "owner:devyan" "$OWNER_COLOUR" "Lane: Signal"
create_label "owner:austine" "$OWNER_COLOUR" "Lane: Access"
