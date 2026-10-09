# The nightly scoring flow (K-12, NFR-10). The Prefect worker runs it on the `scoring` work
# pool, and it asks the signal service to score every enabled module. The signal service then
# publishes `signals.batch_written` and the Laravel rollup consumer turns the new scores into
# variable scores, so this flow never calls the rollup itself (ADR-004a §3).

import os
import subprocess
import sys
from collections.abc import Mapping

from prefect import flow, task

# Where the signal service lives inside the worker image (infra/docker/flows.Dockerfile).
DEFAULT_SIGNALS_SERVICE_PATH = "/srv/navuuna/services/signals"

# The signal service's own command for "score every enabled module" (K-09b).
SCORE_ALL_ARGUMENTS = ["-m", "engine", "run", "--all"]

# A failed run is tried twice more, a minute apart, before Prefect marks it Failed.
# One minute is long enough for a restarting database to come back.
RETRIES = 2
RETRY_DELAY_SECONDS = 60


def build_engine_command(environment: Mapping[str, str]) -> list[str]:
    """Return the command that scores every enabled module.

    SIGNAL_MODULES_ROOT, when set, points the engine at another modules folder
    (the local stack uses the engine's fake test modules until services/signals/modules/ lands).
    """
    command = [sys.executable, *SCORE_ALL_ARGUMENTS]
    modules_root = environment.get("SIGNAL_MODULES_ROOT")
    if modules_root:
        command += ["--modules-root", modules_root]
    return command


@task(retries=RETRIES, retry_delay_seconds=RETRY_DELAY_SECONDS)
def run_signal_engine(command: list[str], signals_service_path: str) -> None:
    """Run the signal engine and fail the task if it exits with anything but 0.

    Implements NFR-10: a failure shows in the Prefect UI after the retries.
    """
    subprocess.run(command, cwd=signals_service_path, check=True)


@flow(name="score_all")
def score_all() -> None:
    """Score every enabled module for every entity (work pack K-12)."""
    signals_service_path = os.environ.get("SIGNALS_SERVICE_PATH", DEFAULT_SIGNALS_SERVICE_PATH)
    run_signal_engine(build_engine_command(os.environ), signals_service_path)
