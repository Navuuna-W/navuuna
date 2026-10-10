# Tests for the water 1.1 Presence adapter: every rule and null reason in
# docs/modules/water.md "1.1 Presence", plus the registry finding the file.

from datetime import UTC, datetime, timedelta
from pathlib import Path
from uuid import UUID

import pytest

from engine.adapter_inputs import AdapterInputs, InputRow
from engine.registry import discover_adapters
from engine.score_result import ScoreStatus
from modules.water.adapters.presence import score

AS_OF = datetime(2026, 10, 8, 12, 0, tzinfo=UTC)
EXTRACT_DATE = datetime(2026, 9, 20, tzinfo=UTC)
MAP_SOURCE_ID = UUID("00000000-0000-7000-8000-000000000001")
REPORT_SOURCE_ID = UUID("00000000-0000-7000-8000-000000000002")
MODULES_ROOT = Path(__file__).resolve().parents[3] / "modules"


def make_entity(external_ref: str = "osm:n123", retired_at: str | None = None) -> InputRow:
    return {
        "external_ref": external_ref,
        "retired_at": retired_at,
        "metadata": {"source_id": str(MAP_SOURCE_ID), "observed_at": EXTRACT_DATE.isoformat()},
    }


def make_report(existence: str, days_ago: int, contributor_id: str | None) -> InputRow:
    return {
        "source_id": str(REPORT_SOURCE_ID),
        "observed_at": (AS_OF - timedelta(days=days_ago)).isoformat(),
        "contributor_id": contributor_id,
        "payload": {"existence": existence},
    }


def make_inputs(entity: InputRow, observations: list[InputRow] | None = None) -> AdapterInputs:
    return AdapterInputs(as_of=AS_OF, entity=entity, observations=tuple(observations or []))


def test_entity_in_osm_extract_is_present_with_extract_as_source() -> None:
    inputs = make_inputs(make_entity())

    result = score(inputs)

    assert result.status is ScoreStatus.MEASURED
    assert (result.value, result.score, result.confidence) == ("present", 100.0, 0.7)
    assert result.source_ids == (MAP_SOURCE_ID,)
    assert result.observed_at == EXTRACT_DATE


def test_recent_exists_report_raises_confidence_to_point_nine() -> None:
    inputs = make_inputs(make_entity(), [make_report("exists", 5, "person-a")])

    result = score(inputs)

    assert (result.value, result.confidence) == ("present", 0.9)
    assert result.source_ids == (MAP_SOURCE_ID, REPORT_SOURCE_ID)
    assert result.observed_at == AS_OF - timedelta(days=5)


def test_one_absence_report_keeps_present_but_lowers_confidence() -> None:
    inputs = make_inputs(make_entity(), [make_report("does_not_exist_here", 3, "person-a")])

    result = score(inputs)

    assert result.value == "present"
    assert result.confidence == pytest.approx(0.7 * 0.6)


def test_two_different_people_reporting_absence_make_it_absent() -> None:
    reports = [
        make_report("does_not_exist_here", 10, "person-a"),
        make_report("does_not_exist_here", 2, "person-b"),
    ]
    inputs = make_inputs(make_entity(), reports)

    result = score(inputs)

    assert (result.value, result.score, result.confidence) == ("absent", 0.0, 0.7)
    assert result.observed_at == AS_OF - timedelta(days=2)


def test_two_absence_reports_from_the_same_person_do_not_make_it_absent() -> None:
    reports = [
        make_report("does_not_exist_here", 10, "person-a"),
        make_report("does_not_exist_here", 2, "person-a"),
    ]
    inputs = make_inputs(make_entity(), reports)

    result = score(inputs)

    assert result.value == "present"


def test_anonymous_absence_reports_do_not_count_as_different_people() -> None:
    reports = [
        make_report("does_not_exist_here", 10, None),
        make_report("does_not_exist_here", 2, None),
    ]
    inputs = make_inputs(make_entity(), reports)

    result = score(inputs)

    assert result.value == "present"


def test_exists_report_after_the_absence_reports_keeps_it_present() -> None:
    reports = [
        make_report("does_not_exist_here", 10, "person-a"),
        make_report("does_not_exist_here", 8, "person-b"),
        make_report("exists", 1, "person-c"),
    ]
    inputs = make_inputs(make_entity(), reports)

    result = score(inputs)

    assert result.value == "present"
    assert result.confidence == pytest.approx(0.9 * 0.6)


def test_reports_older_than_ninety_days_are_ignored() -> None:
    reports = [
        make_report("does_not_exist_here", 120, "person-a"),
        make_report("does_not_exist_here", 100, "person-b"),
    ]
    inputs = make_inputs(make_entity(), reports)

    result = score(inputs)

    assert (result.value, result.confidence) == ("present", 0.7)


def test_reports_dated_after_as_of_are_ignored() -> None:
    inputs = make_inputs(make_entity(), [make_report("does_not_exist_here", -1, "person-a")])

    result = score(inputs)

    assert result.confidence == 0.7


def test_register_only_entity_without_reports_is_not_measured() -> None:
    inputs = make_inputs(make_entity(external_ref="register:ws-17"))

    result = score(inputs)

    assert result.status is ScoreStatus.NULL_NOT_MEASURED
    assert result.null_reason == "No ground observation of this water point yet"
    assert result.score is None


def test_retired_osm_entity_without_reports_is_not_measured() -> None:
    inputs = make_inputs(make_entity(retired_at="2026-10-01T00:00:00+00:00"))

    result = score(inputs)

    assert result.null_reason == "No ground observation of this water point yet"


def test_osm_entity_without_recorded_source_is_not_measured_not_guessed() -> None:
    entity = make_entity()
    entity["metadata"] = {"observed_at": EXTRACT_DATE.isoformat()}
    inputs = make_inputs(entity)

    result = score(inputs)

    assert result.status is ScoreStatus.NULL_NOT_MEASURED
    assert result.null_reason == "Map source of this water point not recorded"


def test_zoneless_extract_date_counts_as_not_recorded() -> None:
    entity = make_entity()
    entity["metadata"] = {"source_id": str(MAP_SOURCE_ID), "observed_at": "2026-09-20T00:00:00"}
    inputs = make_inputs(entity)

    result = score(inputs)

    assert result.null_reason == "Map source of this water point not recorded"


def test_registry_finds_the_presence_adapter_in_the_water_module() -> None:
    environment = {"MODULES_ENABLED": "water"}

    adapters = discover_adapters(MODULES_ROOT, environment)

    code_ref_by_sub_id = {adapter.spec.sub_id: adapter.code_ref for adapter in adapters}
    assert code_ref_by_sub_id["1.1"] == "modules/water/adapters/presence.py"
