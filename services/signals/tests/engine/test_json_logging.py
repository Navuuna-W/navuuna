# Tests for the JSON log formatter (engine/json_logging.py, K-15). Each test builds a log
# record by hand and reads back the one line of JSON the formatter makes. No database.

import json
import logging
import sys
from typing import Any
from uuid import UUID

from engine.json_logging import JsonFormatter, configure_logging

ENTITY_ID = UUID("01920000-0000-7000-8000-000000000001")


def format_as_json(record: logging.LogRecord) -> dict[str, Any]:
    line = JsonFormatter().format(record)
    parsed: dict[str, Any] = json.loads(line)
    return parsed


def make_record(message: str, *arguments: object, **extra: object) -> logging.LogRecord:
    record = logging.makeLogRecord(
        {"name": "engine.runner", "levelno": logging.INFO, "levelname": "INFO", "msg": message}
    )
    record.args = arguments
    record.__dict__.update(extra)
    return record


def test_a_record_becomes_one_line_with_the_same_field_names_as_laravel() -> None:
    record = make_record("scored a chunk")

    line = JsonFormatter().format(record)

    assert "\n" not in line
    assert set(json.loads(line)) == {"datetime", "level_name", "channel", "message", "context"}


def test_level_logger_and_message_are_written() -> None:
    record = make_record("scored a chunk")

    fields = format_as_json(record)

    assert fields["level_name"] == "INFO"
    assert fields["channel"] == "engine.runner"
    assert fields["message"] == "scored a chunk"
    assert fields["context"] == {}


def test_the_time_is_written_in_utc() -> None:
    record = make_record("scored a chunk")
    record.created = 0

    fields = format_as_json(record)

    assert fields["datetime"] == "1970-01-01T00:00:00+00:00"


def test_message_arguments_are_filled_in() -> None:
    record = make_record("module %s: %d entities", "water", 3)

    fields = format_as_json(record)

    assert fields["message"] == "module water: 3 entities"


def test_extra_fields_go_into_context_and_a_uuid_is_written_as_text() -> None:
    record = make_record("adapter failed", entity_id=ENTITY_ID, sub_id="2.2")

    fields = format_as_json(record)

    assert fields["context"] == {"entity_id": str(ENTITY_ID), "sub_id": "2.2"}


def test_an_exception_adds_its_traceback() -> None:
    record = make_record("adapter failed")
    try:
        raise ValueError("bad row")
    except ValueError:
        record.exc_info = sys.exc_info()

    fields = format_as_json(record)

    assert "ValueError: bad row" in fields["exception"]


def test_configure_logging_writes_json_to_stderr_when_logging_is_not_set_up(
    capsys: Any,
) -> None:
    root_logger = logging.getLogger()
    saved_handlers = root_logger.handlers[:]
    saved_level = root_logger.level
    root_logger.handlers.clear()
    try:
        configure_logging()
        logging.getLogger("engine.cli").info("started")
    finally:
        root_logger.handlers[:] = saved_handlers
        root_logger.setLevel(saved_level)

    line = capsys.readouterr().err.strip()
    assert json.loads(line)["message"] == "started"
