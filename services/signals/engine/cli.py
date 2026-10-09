# The command line for the signal service. `python -m engine run --module water` (or `--all`)
# starts a scoring run — a person or Prefect (K-12) calls it: find adapters → record them in
# core.adapters → score each module → announce each chunk. `python -m engine consume` runs the
# reader that rescores single entities when Laravel asks (Supervisor keeps it running).

import argparse
import logging
import signal
import socket
from collections.abc import Mapping
from datetime import UTC, datetime
from pathlib import Path

from engine.adapter_sync import sync_adapters
from engine.database import DatabaseSettingsError, connect_to_database
from engine.json_logging import configure_logging
from engine.module_run import run_module
from engine.recompute_consumer import RecomputeConsumer
from engine.redis_connection import RedisSettingsError, connect_to_redis
from engine.registered_adapter import RegisteredAdapter
from engine.registry import AdapterRegistryError, discover_adapters

# services/signals/modules — where Devyan's module folders live (Bible §14.1).
DEFAULT_MODULES_ROOT = Path(__file__).resolve().parent.parent / "modules"
SUCCESS_EXIT_CODE = 0
FAILURE_EXIT_CODE = 1

logger = logging.getLogger(__name__)


class CommandError(Exception):
    """A problem with what the command was asked to do; reported without a traceback."""


def main(argv: list[str], environment: Mapping[str, str]) -> int:
    """Run the command in `argv` and return the process exit code.

    Inputs: the arguments after the program name and the process environment
    (NV_SIGNALS_DATABASE_URL, NV_REDIS_URL, MODULES_ENABLED). Output: 0 on success, 1 when a
    setting is missing, the modules folder or a module is not found, or an adapter is broken.
    Implements the K-09 work pack — batch runs by module.
    """
    configure_logging()
    arguments = build_argument_parser().parse_args(argv)
    try:
        run_command(arguments, environment)
    except (CommandError, AdapterRegistryError, DatabaseSettingsError, RedisSettingsError) as error:
        logger.error("%s", error)
        return FAILURE_EXIT_CODE
    return SUCCESS_EXIT_CODE


def build_argument_parser() -> argparse.ArgumentParser:
    """Describe the `run` command: which modules to score and where their adapters live."""
    parser = argparse.ArgumentParser(prog="python -m engine", description="Navuuna signal service")
    commands = parser.add_subparsers(dest="command", required=True)
    run_parser = commands.add_parser("run", help="score the entities of one or more modules")
    which_modules = run_parser.add_mutually_exclusive_group(required=True)
    which_modules.add_argument(
        "--module", action="append", dest="modules", metavar="NAME", help="a module to score"
    )
    which_modules.add_argument(
        "--all", action="store_true", dest="is_all_modules", help="score every enabled module"
    )
    consume_parser = commands.add_parser(
        "consume", help="rescore single entities requested on signals.recompute_requested"
    )
    consume_parser.add_argument(
        "--consumer-name", default=socket.gethostname(), help="unique per running reader"
    )
    for command_parser in (run_parser, consume_parser):
        command_parser.add_argument(
            "--modules-root", type=Path, default=DEFAULT_MODULES_ROOT, help="folder of modules"
        )
    return parser


def run_command(arguments: argparse.Namespace, environment: Mapping[str, str]) -> None:
    """Find the adapters, then start the chosen command."""
    modules_root: Path = arguments.modules_root
    if not modules_root.is_dir():
        raise CommandError(f"modules folder {modules_root} does not exist")
    adapters = discover_adapters(modules_root, environment)
    if arguments.command == "consume":
        consume_requests(arguments, environment, adapters)
    else:
        score_modules(arguments, environment, adapters)


def score_modules(
    arguments: argparse.Namespace,
    environment: Mapping[str, str],
    adapters: list[RegisteredAdapter],
) -> None:
    """Score the chosen modules, all with one as_of, and log one summary line per module."""
    modules = choose_modules(arguments, adapters)
    as_of = datetime.now(UTC)
    with connect_to_database(environment) as connection, connect_to_redis(environment) as redis:
        adapter_ids = sync_adapters(connection, adapters)
        for module in modules:
            summary = run_module(connection, redis, module, adapters, adapter_ids, as_of)
            logger.info(
                "module %s: %d entities scored in batch %s",
                module,
                summary.scored_entity_count,
                summary.batch_id,
            )


def consume_requests(
    arguments: argparse.Namespace,
    environment: Mapping[str, str],
    adapters: list[RegisteredAdapter],
) -> None:
    """Read signals.recompute_requested until stopped, rescoring each requested entity."""
    signal.signal(signal.SIGTERM, stop_on_terminate)
    with connect_to_database(environment) as connection, connect_to_redis(environment) as redis:
        adapter_ids = sync_adapters(connection, adapters)
        consumer = RecomputeConsumer(
            connection, redis, adapters, adapter_ids, consumer_name=arguments.consumer_name
        )
        consumer.ensure_consumer_group()
        logger.info("recompute consumer %s started", arguments.consumer_name)
        consumer.consume_forever()


def stop_on_terminate(signal_number: int, frame: object) -> None:
    """Turn SIGTERM (Supervisor stopping us) into the same clean stop as Ctrl-C."""
    raise KeyboardInterrupt


def choose_modules(arguments: argparse.Namespace, adapters: list[RegisteredAdapter]) -> list[str]:
    """Return the modules to score: every enabled one for --all, else the --module names.

    Raises CommandError for a --module name with no enabled adapters, so a typo or a
    switched-off module is reported instead of silently scoring nothing.
    """
    enabled_modules = sorted({adapter.spec.module for adapter in adapters})
    if arguments.is_all_modules:
        return enabled_modules
    requested_modules: list[str] = arguments.modules
    unknown_modules = sorted(set(requested_modules) - set(enabled_modules))
    if unknown_modules:
        raise CommandError(f"no enabled adapters for module(s) {unknown_modules}")
    return requested_modules
