# Tests for the frozen sub-variable list (Bible §6.8) and its agreement with weights.yml (§6.7).
# If someone edits one without the other, these fail before the rollup ever runs.

from pathlib import Path

import yaml

from engine.entity_type import EntityType
from engine.sub_variables import SUB_VARIABLE_IDS, is_known_sub_variable

WEIGHTS_FILE = Path(__file__).parents[2] / "engine" / "weights.yml"

# Gates and the guard veto instead of averaging, so weights.yml gives them no weight (§6.3).
UNWEIGHTED_IDS = {"1.1", "2.1", "2.6"}

SUB_VARIABLES_PER_VARIABLE = {"1": 5, "2": 6, "3": 6, "4": 6, "5": 6}


def read_weighted_ids() -> set[str]:
    weights = yaml.safe_load(WEIGHTS_FILE.read_text())
    weighted_ids: set[str] = set()
    for variable_name, sub_weights in weights.items():
        if variable_name == "weight_version":
            continue
        weighted_ids.update(sub_weights)
    return weighted_ids


def test_each_variable_has_the_sub_variables_listed_in_the_bible() -> None:
    counts: dict[str, int] = {}

    for sub_id in SUB_VARIABLE_IDS:
        variable = sub_id.split(".")[0]
        counts[variable] = counts.get(variable, 0) + 1

    assert counts == SUB_VARIABLES_PER_VARIABLE


def test_a_listed_id_is_known() -> None:
    assert is_known_sub_variable("1.2")


def test_an_invented_id_is_not_known() -> None:
    assert not is_known_sub_variable("6.1")
    assert not is_known_sub_variable("1.7")
    assert not is_known_sub_variable("")


def test_every_weighted_id_in_weights_yml_is_a_known_sub_variable() -> None:
    weighted_ids = read_weighted_ids()

    unknown_ids = weighted_ids - SUB_VARIABLE_IDS

    assert unknown_ids == set()


def test_every_sub_variable_except_gates_and_guard_has_a_weight() -> None:
    weighted_ids = read_weighted_ids()

    missing_ids = SUB_VARIABLE_IDS - UNWEIGHTED_IDS - weighted_ids

    assert missing_ids == set()


def test_entity_types_match_the_core_entities_column_values() -> None:
    values = {entity_type.value for entity_type in EntityType}

    assert values == {"point", "parcel", "segment", "area"}
