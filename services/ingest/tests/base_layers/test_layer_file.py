# Checks that a GeoJSON-lines layer file is read feature by feature, and that a damaged
# file stops the load instead of being half read.

from pathlib import Path

import pytest
from pydantic import ValidationError

from base_layers.layer_file import read_layer_file

WARDS_FIXTURE_PATH = Path(__file__).parent / "fixtures" / "wards.geojsonl"


def test_reading_a_layer_file_returns_one_feature_per_line_and_skips_blank_lines() -> None:
    features = read_layer_file(WARDS_FIXTURE_PATH)

    assert len(features) == 2
    assert features[0].properties["IEBC_WARDS"] == "Airbase"
    assert features[1].geometry["type"] == "Polygon"


def test_a_line_without_a_geometry_stops_the_read(tmp_path: Path) -> None:
    damaged_file_path = tmp_path / "damaged.geojsonl"
    damaged_file_path.write_text('{"type": "Feature", "properties": {"PCODE": "KE0017"}}\n')

    with pytest.raises(ValidationError):
        read_layer_file(damaged_file_path)
