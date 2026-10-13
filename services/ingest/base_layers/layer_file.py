# Reads one base-layer file from disk. The extract script in data/ writes each layer as
# GeoJSON lines (one map feature per line); the mapping files turn those features into entities.

import json
from pathlib import Path
from typing import Any

from pydantic import BaseModel, ConfigDict, Field


class LayerFeature(BaseModel):
    """One map feature from a layer file: its attributes and its GeoJSON geometry."""

    model_config = ConfigDict(frozen=True)

    properties: dict[str, Any] = Field(default_factory=dict)
    geometry: dict[str, Any]


def read_layer_file(layer_file_path: Path) -> list[LayerFeature]:
    """Read every feature of a GeoJSON-lines file.

    Input: the path of a file with one GeoJSON feature per line. Output: the features in
    file order. Blank lines are skipped. A line that is not a feature with a geometry raises
    a pydantic ValidationError, so a damaged file stops the load instead of loading part of it.
    """
    features: list[LayerFeature] = []
    with layer_file_path.open(encoding="utf-8") as layer_file:
        for line in layer_file:
            if not line.strip():
                continue
            features.append(LayerFeature.model_validate(json.loads(line)))
    return features
