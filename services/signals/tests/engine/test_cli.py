# Tests for the command line (`python -m engine run`). Error cases need no database; the one
# success case commits, because the CLI commits — it uses a module with no entities, so it
# only leaves its own core.adapters row behind (in a test database).

import logging
import os
import runpy
import sys
from pathlib import Path

import pytest

from engine.cli import FAILURE_EXIT_CODE, SUCCESS_EXIT_CODE, main
from engine.redis_connection import RedisSettingsError, connect_to_redis
from tests.database_helpers import TEST_DATABASE_URL_VARIABLE, TEST_REDIS_URL_VARIABLE

FIXTURE_MODULES_ROOT = Path(__file__).parent.parent / "fixtures" / "modules"
FAKE_MODULE_ONLY = {"MODULES_ENABLED": "fake"}
EMPTY_MODULE_ADAPTER = """
from engine.adapter_spec import AdapterSpec
from engine.score_result import ScoreResult, ScoreStatus

SPEC = AdapterSpec(
    module="cli_test",
    sub_id="1.1",
    entity_types=["point"],
    version="0.0.1",
    requires=["entity"],
    signal_description="CLI test adapter.",
)


def score(inputs):
    return ScoreResult(status=ScoreStatus.NULL_NOT_MEASURED, null_reason="Test")
"""


def run_arguments(*module_arguments: str, modules_root: Path = FIXTURE_MODULES_ROOT) -> list[str]:
    return ["run", *module_arguments, "--modules-root", str(modules_root)]


def test_a_missing_modules_folder_fails(tmp_path: Path, caplog: pytest.LogCaptureFixture) -> None:
    missing_folder = tmp_path / "modules"

    exit_code = main(run_arguments("--all", modules_root=missing_folder), FAKE_MODULE_ONLY)

    assert exit_code == FAILURE_EXIT_CODE
    assert "does not exist" in caplog.text


def test_an_unknown_module_fails(caplog: pytest.LogCaptureFixture) -> None:
    exit_code = main(run_arguments("--module", "watr"), FAKE_MODULE_ONLY)

    assert exit_code == FAILURE_EXIT_CODE
    assert "no enabled adapters for module(s) ['watr']" in caplog.text


def test_a_missing_database_setting_fails(caplog: pytest.LogCaptureFixture) -> None:
    exit_code = main(run_arguments("--module", "fake"), FAKE_MODULE_ONLY)

    assert exit_code == FAILURE_EXIT_CODE
    assert "NV_SIGNALS_DATABASE_URL" in caplog.text


def test_run_needs_module_or_all() -> None:
    with pytest.raises(SystemExit) as raised:
        main(["run"], FAKE_MODULE_ONLY)

    assert raised.value.code == 2  # argparse's exit code for a usage error


def test_a_missing_redis_setting_is_reported() -> None:
    environment: dict[str, str] = {}

    with pytest.raises(RedisSettingsError, match="NV_REDIS_URL"):
        connect_to_redis(environment)


def test_python_dash_m_engine_runs_the_cli(monkeypatch: pytest.MonkeyPatch) -> None:
    monkeypatch.setattr(sys, "argv", ["engine", "--help"])

    with pytest.raises(SystemExit) as raised:
        runpy.run_module("engine", run_name="__main__")

    assert raised.value.code == 0


def test_all_scores_every_enabled_module(tmp_path: Path, caplog: pytest.LogCaptureFixture) -> None:
    database_url = os.environ.get(TEST_DATABASE_URL_VARIABLE, "")
    redis_url = os.environ.get(TEST_REDIS_URL_VARIABLE, "")
    if not database_url or not redis_url:
        pytest.skip("needs NV_TEST_DATABASE_URL and NV_TEST_REDIS_URL")
    adapters_folder = tmp_path / "modules" / "cli_test" / "adapters"
    adapters_folder.mkdir(parents=True)
    (adapters_folder / "presence.py").write_text(EMPTY_MODULE_ADAPTER)
    environment = {"NV_SIGNALS_DATABASE_URL": database_url, "NV_REDIS_URL": redis_url}
    caplog.set_level(logging.INFO, logger="engine.cli")  # the summary line is INFO

    exit_code = main(run_arguments("--all", modules_root=tmp_path / "modules"), environment)

    assert exit_code == SUCCESS_EXIT_CODE
    assert "module cli_test: 0 entities scored" in caplog.text
