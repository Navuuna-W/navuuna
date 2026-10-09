# Tests for the score_all flow (K-12). Each test points the flow at a fake signal service: a
# temporary folder whose `engine` package records its arguments and exits with a chosen code,
# so the flow runs the real command line without a database.

from collections.abc import Iterator
from pathlib import Path

import pytest
from prefect.testing.utilities import prefect_test_harness

import score_all as score_all_module
from score_all import build_engine_command, score_all

FAKE_ENGINE_TEMPLATE = """
import sys
with open({calls_path!r}, "a") as calls:
    calls.write(" ".join(sys.argv[1:]) + "\\n")
sys.exit({exit_code})
"""


@pytest.fixture(scope="session", autouse=True)
def prefect_server() -> Iterator[None]:
    """Run every test against a throwaway Prefect database."""
    with prefect_test_harness():
        yield


@pytest.fixture(autouse=True)
def no_retry_delay(monkeypatch: pytest.MonkeyPatch) -> None:
    """Keep the real retry count but skip the minute's wait between tries."""
    fast_task = score_all_module.run_signal_engine.with_options(retry_delay_seconds=0)
    monkeypatch.setattr(score_all_module, "run_signal_engine", fast_task)


def make_fake_signal_service(folder: Path, exit_code: int) -> Path:
    """Write a fake `engine` package into folder and return the file its calls go to."""
    calls_path = folder / "calls.txt"
    (folder / "engine").mkdir()
    (folder / "engine" / "__main__.py").write_text(
        FAKE_ENGINE_TEMPLATE.format(calls_path=str(calls_path), exit_code=exit_code)
    )
    return calls_path


def read_calls(calls_path: Path) -> list[str]:
    """Return one line per time the fake engine ran: the arguments it got."""
    return calls_path.read_text().splitlines()


def test_flow_completes_when_the_engine_exits_with_zero(
    tmp_path: Path, monkeypatch: pytest.MonkeyPatch
) -> None:
    calls_path = make_fake_signal_service(tmp_path, exit_code=0)
    monkeypatch.setenv("SIGNALS_SERVICE_PATH", str(tmp_path))
    monkeypatch.delenv("SIGNAL_MODULES_ROOT", raising=False)

    final_state = score_all(return_state=True)

    assert final_state.is_completed()
    assert read_calls(calls_path) == ["run --all"]


def test_flow_fails_after_the_retries_when_the_engine_keeps_failing(
    tmp_path: Path, monkeypatch: pytest.MonkeyPatch
) -> None:
    calls_path = make_fake_signal_service(tmp_path, exit_code=1)
    monkeypatch.setenv("SIGNALS_SERVICE_PATH", str(tmp_path))

    final_state = score_all(return_state=True)

    assert final_state.is_failed()
    assert len(read_calls(calls_path)) == 1 + score_all_module.RETRIES


def test_engine_command_scores_every_enabled_module() -> None:
    command = build_engine_command({})

    assert command[1:] == ["-m", "engine", "run", "--all"]


def test_engine_command_uses_the_modules_root_when_one_is_set() -> None:
    command = build_engine_command({"SIGNAL_MODULES_ROOT": "tests/fixtures/modules"})

    assert command[-2:] == ["--modules-root", "tests/fixtures/modules"]
